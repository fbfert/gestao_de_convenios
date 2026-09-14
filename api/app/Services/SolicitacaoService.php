<?php

namespace App\Services;

use App\Exceptions\SolicitacaoStatusInvalidoException;
use App\Models\Convenio;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Models\PacienteArquivo;
use App\Models\Solicitacao;
use App\Models\SolicitacaoItem;
use App\Support\GuiaStatus;
use App\Support\SolicitacaoStatus;
use App\Support\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Support\OrdenaListagem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SolicitacaoService
{
    /**
     * Transições que um humano pode disparar manualmente (botões da tela).
     * 'approved' fica de fora de propósito: agora só o sistema grava esse
     * valor, quando confirma que a operadora aprovou a(s) guia(s) — ver
     * sincronizarStatusComGuias().
     */
    private const STATUS_PERMITIDOS = ['under_review', 'ready_for_automation', 'denied'];

    /**
     * 'historico' também fica fora de propósito: é gravado só por backfill
     * direto (rastro de guias antigas migradas sem Solicitação de origem),
     * nunca por uma transição manual da tela. listar() já exclui esse status
     * por padrão (ver filtro `mostrar_historico`), então nenhuma automação e
     * nenhuma listagem comum precisa saber que ele existe.
     */

    /** Status de Guia que contam como "aprovada pela operadora" pro sync. */
    private const GUIA_STATUS_APROVADA = ['approved', 'finalized'];

    /**
     * Tipo de terapia que a guia recebe ao nascer, e pelo qual a regra vigente
     * do convenio e procurada.
     *
     * Fixo porque o item de solicitacao nao guarda tipo de terapia — e
     * `convenio_regras` e por `(convenio_id, tipo_terapia)`. Limitacao
     * conhecida: convenio cujas regras diferem entre especializada e
     * convencional so tem a primeira consultada aqui.
     */
    private const TIPO_TERAPIA_PADRAO = 'especializada';

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        return Solicitacao::query()
            ->with([
                'paciente',
                'profissional',
                'especialidade',
                'convenio',
                'medico',
                'cidCadastros',
                'itens.especialidade.convenioMapeamentos',
                'itens.profissional',
                'itens.documentos.arquivo',
                'itens.guia',
                'itens.automacaoExecucoes',
                'documentos.arquivo',
            ])
            ->when(
                Arr::get($filtros, 'mostrar_historico'),
                fn ($query) => $query->where('status', 'historico'),
                fn ($query) => $query->where('status', '!=', 'historico'),
            )
            ->when(Arr::get($filtros, 'id'), fn ($query, $id) => $query->where('solicitacoes.id', $id))
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($filtros, 'convenio_id'), fn ($query, $convenioId) => $query->where('convenio_id', $convenioId))
            ->when(Arr::get($filtros, 'medico_id'), fn ($query, $medicoId) => $query->where('medico_id', $medicoId))
            ->when(Arr::get($filtros, 'paciente'), fn ($query, $nome) => $query->whereHas(
                'paciente',
                fn ($q) => $q->where('nome', 'like', $this->curingaLike($nome)),
            ))
            ->when(Arr::get($filtros, 'medico'), fn ($query, $nome) => $query->whereHas(
                'medico',
                fn ($q) => $q->where('nome', 'like', $this->curingaLike($nome)),
            ))
            ->when(Arr::get($filtros, 'profissional'), fn ($query, $nome) => $query->where(
                fn ($q) => $q
                    ->whereHas('itens.profissional', fn ($qq) => $qq->where('nome', 'like', $this->curingaLike($nome)))
                    ->orWhereHas('profissional', fn ($qq) => $qq->where('nome', 'like', $this->curingaLike($nome))),
            ))
            ->tap(fn ($query) => OrdenaListagem::aplicar(
                $query->select('solicitacoes.*'),
                $filtros,
                [
                    'id' => 'solicitacoes.id',
                    'status' => 'solicitacoes.status',
                    'paciente' => fn ($query, $direcao) => $query
                        ->leftJoin('pacientes', 'pacientes.id', '=', 'solicitacoes.paciente_id')
                        ->orderBy('pacientes.nome', $direcao),
                    'convenio' => fn ($query, $direcao) => $query
                        ->leftJoin('convenios', 'convenios.id', '=', 'solicitacoes.convenio_id')
                        ->orderBy('convenios.nome', $direcao),
                    'medico' => fn ($query, $direcao) => $query
                        ->leftJoin('medicos', 'medicos.id', '=', 'solicitacoes.medico_id')
                        ->orderBy('medicos.nome', $direcao),
                ],
                padrao: 'solicitacoes.id',
                direcaoPadrao: 'desc',
                desempate: 'solicitacoes.id',
            ))
            ->paginate($perPage);
    }

    /** Escapa `%` e `_` do termo digitado antes de envolvê-lo em curingas de LIKE. */
    private function curingaLike(string $termo): string
    {
        return '%'.addcslashes($termo, '%_\\').'%';
    }

    public function criar(array $dados): Solicitacao
    {
        $tenantId = $this->tenantId();
        $pedidoMedico = $this->resolverPedidoMedico($dados, $tenantId);

        return DB::transaction(function () use ($dados, $pedidoMedico, $tenantId) {
            $itens = $this->normalizarItens($dados);
            $primeiroItem = $itens[0];

            $solicitacao = Solicitacao::query()->create([
                'tenant_id' => $tenantId,
                'paciente_id' => $dados['paciente_id'],
                'profissional_id' => $dados['profissional_id'] ?? $primeiroItem['profissional_id'],
                'especialidade_id' => $dados['especialidade_id'] ?? $primeiroItem['especialidade_id'],
                'convenio_id' => $dados['convenio_id'],
                'medico_id' => $dados['medico_id'],
                'status' => 'under_review',
                'solicitado_em' => $dados['solicitado_em'],
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            $solicitacao->cidCadastros()->sync($dados['cid_ids'] ?? []);

            foreach ($itens as $item) {
                $solicitacao->itens()->create([
                    'tenant_id' => $tenantId,
                    'especialidade_id' => $item['especialidade_id'],
                    'profissional_id' => $item['profissional_id'],
                    'quantidade' => $this->quantidadeDoItem($item, (int) $dados['convenio_id']),
                    'status_operacional' => 'pending',
                    'observacoes' => $item['observacoes'] ?? null,
                ]);
            }

            if ($pedidoMedico) {
                $arquivo = PacienteArquivo::query()->create($pedidoMedico + [
                    'tenant_id' => $tenantId,
                    'paciente_id' => $dados['paciente_id'],
                ]);

                $solicitacao->documentos()->create([
                    'tenant_id' => $tenantId,
                    'solicitacao_item_id' => null,
                    'paciente_arquivo_id' => $arquivo->id,
                ]);
            }

            return $solicitacao->refresh();
        });
    }

    private function resolverPedidoMedico(array $dados, int $tenantId): ?array
    {
        $uploadId = $dados['pedido_medico_upload_id'] ?? null;

        if (! $uploadId) {
            return null;
        }

        /*
         * O caminho vem do cliente (é o `upload_id` devolvido por
         * `analisarPedidoMedico`), então a forma é conferida, e não só o começo
         * da string.
         *
         * Antes a checagem era `str_starts_with($uploadId, $prefix)` sobre a
         * string crua. Prefixo literal cai com um `../`: o Flysystem colapsa o
         * `..` ao verificar a existência, enquanto `Storage::path()` entrega a
         * string sem normalizar na hora de servir — dava para alcançar arquivo
         * de outra clínica dentro do disco `local`, onde ficam documento de
         * paciente, carteirinha e os CSV de expurgo da auditoria.
         *
         * O que a API gera é sempre `.../{tenant}/{uuid}.{ext}`: um único
         * segmento de nome. Exigir exatamente isso fecha a porta sem depender
         * de normalização de caminho.
         */
        $prefix = "pedidos-medicos/pendentes/{$tenantId}/";

        if (! str_starts_with($uploadId, $prefix)) {
            return null;
        }

        $nomeArquivo = substr($uploadId, strlen($prefix));

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $nomeArquivo) !== 1) {
            return null;
        }

        if (! Storage::disk('local')->exists($uploadId)) {
            return null;
        }

        $target = str_replace('/pendentes/', '/solicitacoes/', $uploadId);
        Storage::disk('local')->move($uploadId, $target);

        // `basename` no nome exibido: ele vai para o `Content-Disposition` do
        // download, e é dado do cliente como qualquer outro.
        $nomeOriginal = basename((string) ($dados['pedido_medico_nome_original'] ?? $target));

        /*
         * MIME derivado do arquivo já gravado, nunca o que o cliente mandou.
         * O valor é devolvido cru como `Content-Type` no download; aceitar
         * `text/html` do cliente fazia o anexo abrir como página. E o front
         * abre o download com `URL.createObjectURL`, cuja `blob:` herda a
         * origem do SPA — onde o token de sessão vive no `localStorage`.
         */
        $mime = Storage::disk('local')->mimeType($target) ?: 'application/octet-stream';

        return [
            'tipo' => 'pedido_medico',
            'nome_original' => $nomeOriginal,
            'mime' => $mime,
            'path' => $target,
            'metadata' => $dados['pedido_medico_ai_result'] ?? null,
        ];
    }

    private function normalizarItens(array $dados): array
    {
        $itens = $dados['itens'] ?? [];

        if ($itens !== []) {
            return array_values(array_map(fn (array $item) => [
                'especialidade_id' => $item['especialidade_id'],
                'profissional_id' => $item['profissional_id'],
                // Sem `?? 10`: a quantidade padrão é regra de convênio, e sai
                // daqui resolvida por `quantidadeDoItem()`. Chave ausente
                // continua ausente até lá.
                'quantidade' => $item['quantidade'] ?? null,
                'observacoes' => $item['observacoes'] ?? null,
            ], $itens));
        }

        return [[
            'especialidade_id' => $dados['especialidade_id'],
            'profissional_id' => $dados['profissional_id'],
            'quantidade' => null,
            'observacoes' => null,
        ]];
    }

    /**
     * Edicao manual (admin): so medico/cid/data/observacoes — ver
     * UpdateSolicitacaoRequest. paciente_id/convenio_id ficam de fora de
     * proposito: sao identidade com dados gravados em Guia/Lancamento/
     * Conciliacao ja geradas a partir do valor original.
     */
    public function atualizar(Solicitacao $solicitacao, array $dados): Solicitacao
    {
        $solicitacao->fill(array_filter([
            'medico_id' => $dados['medico_id'] ?? null,
            'solicitado_em' => $dados['solicitado_em'] ?? null,
            'observacoes' => array_key_exists('observacoes', $dados) ? $dados['observacoes'] : null,
        ], fn ($value) => $value !== null));

        $solicitacao->save();

        if (array_key_exists('cid_ids', $dados)) {
            $solicitacao->cidCadastros()->sync($dados['cid_ids']);
        }

        return $solicitacao->refresh();
    }

    public function aprovar(Solicitacao $solicitacao): Solicitacao
    {
        return $this->alterarStatus($solicitacao, 'ready_for_automation');
    }

    public function negar(Solicitacao $solicitacao): Solicitacao
    {
        return $this->alterarStatus($solicitacao, 'denied');
    }

    public function alterarStatus(Solicitacao $solicitacao, string $destino): Solicitacao
    {
        if (! in_array($destino, self::STATUS_PERMITIDOS, true)) {
            throw SolicitacaoStatusInvalidoException::transicaoInvalida($solicitacao->status, $destino);
        }

        return DB::transaction(function () use ($solicitacao, $destino) {
            $solicitacao->update(['status' => $destino]);

            if ($destino === 'ready_for_automation') {
                $this->sincronizarGuiaDaSolicitacao($solicitacao->refresh());
            }

            return $solicitacao->refresh();
        });
    }

    /**
     * REFLETE a Solicitação no estado das guias dos seus itens — chamada depois
     * de qualquer escrita em Guia.status (criação, finalizar manual, ou o job
     * automático de consulta na Unimed) e depois de acrescentar item:
     *   - Algum item sem guia -> 'ready_for_automation'.
     *   - Todo item já tem guia, mas nem todas aprovadas -> 'guia_gerada'.
     *   - Todo item com guia aprovada/finalizada pela operadora -> 'approved'.
     *
     * Reflete, e não só evolui: pode REGREDIR de 'approved'/'guia_gerada' para
     * 'ready_for_automation' quando aparece item sem guia. Sem isso, o caso de
     * uso de "Adicionar sessões" nasce inútil — o item novo entra numa
     * solicitação aprovada e o botão de enviar nunca habilita.
     *
     * `under_review`, `denied` e `historico` ficam de fora: são decisão humana
     * registrada ou rastro de migração, e derivar por cima delas pularia a
     * análise ou reabriria uma negativa.
     *
     * ESTA FUNÇÃO É PURA DE STATUS, e tem que continuar sendo. Ela grava com
     * `update()` direto e NÃO chama `alterarStatus()` nem
     * `sincronizarGuiaDaSolicitacao()`. O motivo é recursão:
     * `alterarStatus('ready_for_automation')` dispara a criação de guias, que
     * mexe em status de guia, que chamaria esta sincronização de novo. A
     * criação de guia em convênio manual é disparada EXPLICITAMENTE por quem
     * altera o status, nunca por efeito colateral daqui.
     *
     * A guarda do `!==` não é otimização: `Solicitacao` é Auditable, e gravar o
     * mesmo valor a cada sincronização encheria a trilha de auditoria de linhas
     * que não registram mudança nenhuma.
     */
    public function sincronizarStatusComGuias(Solicitacao $solicitacao): void
    {
        if (! SolicitacaoStatus::derivaDosItens($solicitacao->status)) {
            return;
        }

        $itens = $solicitacao->itens()->with('guia')->get();

        // Sem item não há de que derivar: manter o status como está é mais
        // honesto que inventar um.
        if ($itens->isEmpty()) {
            return;
        }

        $destino = match (true) {
            $itens->contains(fn ($item) => $item->guia === null) => SolicitacaoStatus::READY_FOR_AUTOMATION,
            $itens->every(fn ($item) => in_array($item->guia->status, self::GUIA_STATUS_APROVADA, true)) => SolicitacaoStatus::APPROVED,
            default => SolicitacaoStatus::GUIA_GERADA,
        };

        if ($solicitacao->status !== $destino) {
            $solicitacao->update(['status' => $destino]);
        }
    }

    /**
     * Acrescenta um item a uma solicitação que já existe.
     *
     * Repetir especialidade e profissional é PERMITIDO, e de propósito: é o
     * caso de uso principal — a Unimed libera 10 sessões por guia, e quem
     * precisa de 20 pede duas vezes o mesmo par sob o mesmo pedido médico.
     * Não há unique nem validação de duplicata, e não deve haver.
     */
    public function adicionarItem(Solicitacao $solicitacao, array $dados): SolicitacaoItem
    {
        if (SolicitacaoStatus::bloqueiaAdicao($solicitacao->status)) {
            throw new SolicitacaoStatusInvalidoException(
                'Não é possível acrescentar itens a uma solicitação negada ou em histórico.'
            );
        }

        return DB::transaction(function () use ($solicitacao, $dados) {
            $origem = $this->origemDaCadeia($solicitacao, $dados['renovacao_de_item_id'] ?? null);

            $item = $solicitacao->itens()->create([
                'tenant_id' => $solicitacao->tenant_id,
                'especialidade_id' => $dados['especialidade_id'],
                'profissional_id' => $dados['profissional_id'],
                'renovacao_de_item_id' => $origem?->id,
                'quantidade' => $this->quantidadeDoItem($dados, (int) $solicitacao->convenio_id),
                'status_operacional' => 'pending',
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            // Criação de guia EXPLÍCITA, e não por efeito colateral:
            // `sincronizarStatusComGuias()` é pura de status de propósito (ver
            // o comentário lá). Sem esta chamada, em convênio manual o status
            // regrediria para `ready_for_automation` e a guia do item novo
            // nunca nasceria.
            //
            // Só a partir de `derivaDosItens`: gerar guia para item de uma
            // solicitação ainda `under_review` pularia a análise, que é
            // exatamente a etapa que aquele status representa.
            if (SolicitacaoStatus::derivaDosItens($solicitacao->status)) {
                $this->sincronizarGuiaDaSolicitacao($solicitacao->fresh(['convenio']));
            }

            $this->sincronizarStatusComGuias($solicitacao->fresh());

            return $item->refresh();
        });
    }

    /**
     * Remove um item que ainda não gerou Guia — caso de uso de cadastro
     * errado, não de "desistir de uma sessão já autorizada". Item com Guia
     * (mesmo negada) nunca é removível por aqui: a Guia é o registro do que
     * de fato aconteceu na operadora, apagar o item apagaria esse rastro.
     * Tentativas de automação sem sucesso não bloqueiam — são exatamente o
     * cenário "cadastrei errado, a Unimed rejeitou, quero tirar daqui".
     */
    public function removerItem(Solicitacao $solicitacao, SolicitacaoItem $item): void
    {
        if ((int) $item->solicitacao_id !== (int) $solicitacao->id) {
            throw new SolicitacaoStatusInvalidoException('Este item não pertence a esta solicitação.');
        }

        if (SolicitacaoStatus::bloqueiaAdicao($solicitacao->status)) {
            throw new SolicitacaoStatusInvalidoException(
                'Não é possível remover itens de uma solicitação negada ou em histórico.'
            );
        }

        $item->loadMissing('guia');
        if ($item->guia) {
            throw new SolicitacaoStatusInvalidoException(
                'Este item já tem Guia gerada e não pode ser excluído.'
            );
        }

        if ($solicitacao->itens()->count() <= 1) {
            throw new SolicitacaoStatusInvalidoException(
                'A solicitação precisa manter pelo menos um item — negue ou cancele a solicitação inteira em vez de excluir o último.'
            );
        }

        DB::transaction(function () use ($solicitacao, $item) {
            $item->delete();

            if (SolicitacaoStatus::derivaDosItens($solicitacao->status)) {
                $this->sincronizarStatusComGuias($solicitacao->fresh());
            }
        });
    }

    /**
     * Os dados que a tela usa para avisar antes de confirmar — calculados aqui,
     * e não no front.
     *
     * `sessoes_por_guia` é regra de convênio, e regra de convênio
     * reimplementada em TypeScript é regra que diverge do backend em seis meses
     * (openspec/config.yaml). Os outros três acompanham para a tela não precisar
     * montar meia resposta no servidor e meia no navegador.
     *
     * Nenhum destes valores bloqueia coisa alguma: são aviso, e quem decide é
     * quem está cadastrando.
     */
    public function contextoDeAdicao(
        Solicitacao $solicitacao,
        ?int $especialidadeId = null,
        ?int $profissionalId = null,
        ?int $renovacaoDeItemId = null,
    ): array {
        $origem = $this->origemDaCadeia($solicitacao, $renovacaoDeItemId);

        return [
            // Idade do pedido médico em dias — a tela decide como dizer isso.
            'pedido_medico_dias' => $solicitacao->solicitado_em?->diffInDays(today()),
            'sessoes_por_guia' => $this->quantidadePadrao((int) $solicitacao->convenio_id),
            'quantidade_padrao' => $this->quantidadePadrao((int) $solicitacao->convenio_id),
            'ja_existe_item_igual' => $especialidadeId !== null && $profissionalId !== null
                && $solicitacao->itens()
                    ->where('especialidade_id', $especialidadeId)
                    ->where('profissional_id', $profissionalId)
                    ->exists(),
            // Soma da cadeia, e não do par especialidade+profissional: sem o
            // vínculo, duas terapias legitimamente separadas contariam junto.
            'sessoes_na_cadeia' => $origem === null ? null : (int) $solicitacao->itens()
                ->where(fn ($query) => $query
                    ->whereKey($origem->id)
                    ->orWhere('renovacao_de_item_id', $origem->id))
                ->sum('quantidade'),
        ];
    }

    /**
     * Resolve o vínculo para a ORIGEM da cadeia, sempre.
     *
     * O cliente pode mandar o id de um item que já é renovação; gravar aquilo
     * cru montaria uma árvore, e somar o ciclo viraria recursão. Aqui a cadeia
     * fica plana: todos apontam para o primeiro.
     */
    private function origemDaCadeia(Solicitacao $solicitacao, int|string|null $renovacaoDeItemId): ?SolicitacaoItem
    {
        if (! $renovacaoDeItemId) {
            return null;
        }

        $item = $solicitacao->itens()->whereKey($renovacaoDeItemId)->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'renovacao_de_item_id' => 'O item de origem precisa ser da mesma solicitação.',
            ]);
        }

        return $item->origemDaCadeia();
    }

    /**
     * A quantidade que o item recebe: a informada, ou a da regra do convênio.
     *
     * Quando não há nem uma nem outra, RECUSA em vez de arbitrar. Era isso que
     * o `?? 10` fazia — e dez é o limite da Unimed, uma regra de convênio
     * escrita em Service, contra a regra de ouro do projeto.
     */
    private function quantidadeDoItem(array $item, int $convenioId): int
    {
        $informada = $item['quantidade'] ?? null;

        if ($informada !== null && $informada !== '') {
            return (int) $informada;
        }

        $padrao = $this->quantidadePadrao($convenioId);

        if ($padrao === null) {
            throw ValidationException::withMessages([
                'quantidade' => self::mensagemSemSessoesPorGuia($convenioId),
            ]);
        }

        return $padrao;
    }

    /**
     * O erro tem que dizer o PRÓXIMO PASSO, não só o que faltou.
     *
     * "Informe a quantidade" mandava a pessoa contornar o problema para sempre,
     * uma solicitação por vez. Nomear o convênio e a tela onde se resolve
     * transforma o mesmo 422 em conserto definitivo — e evita o chamado de
     * suporte que a primeira versão geraria.
     */
    public static function mensagemSemSessoesPorGuia(int $convenioId): string
    {
        $nome = Convenio::query()->whereKey($convenioId)->value('nome') ?? "#{$convenioId}";

        return "O convênio {$nome} não tem \"Sessões por guia\" cadastrada na regra vigente, "
            .'então não há quantidade padrão. Informe a quantidade nesta solicitação, ou '
            ."cadastre o valor em Convênios > {$nome} > Regras para valer daqui em diante.";
    }

    /**
     * Sessões por guia da regra vigente — nulo quando não há regra cadastrada,
     * e nulo aqui significa "quem preenche é a pessoa".
     *
     * O tipo de terapia é o mesmo que a guia recebe ao nascer
     * (`TIPO_TERAPIA_PADRAO`). É uma limitação conhecida: `convenio_regras` é
     * por `(convenio_id, tipo_terapia)`, e o item de solicitação não guarda
     * tipo de terapia nenhum.
     */
    public function quantidadePadrao(int $convenioId): ?int
    {
        return app(ConvenioRegraService::class)
            ->vigente($convenioId, self::TIPO_TERAPIA_PADRAO)
            ?->sessoes_por_guia;
    }

    /**
     * Gera a guia que falta para CADA item, em convênio sem automação.
     *
     * Era `itens()->orderBy('id')->first()`: uma guia só, para o primeiro item.
     * Desde a multi-especialidade isso deixava os demais itens sem guia nenhuma
     * — defeito silencioso, porque a solicitação seguia parecendo processada.
     *
     * Item que já tem guia é PULADO, e isso é deliberado. A versão anterior
     * atualizava a guia encontrada e a devolvia para `under_review`; repetir
     * aquilo dentro de um laço faria uma re-sincronização resetar guias já
     * aprovadas dos outros itens. Aqui a operação só cria o que falta, e por
     * isso é segura de repetir.
     *
     * Solicitação sem item nenhum não gera guia: a guia pertence ao item
     * (ADR-27), e não há a que vinculá-la.
     */
    private function sincronizarGuiaDaSolicitacao(Solicitacao $solicitacao): void
    {
        if ($solicitacao->convenio?->connector_driver === 'unimed_rda') {
            return;
        }

        // O status sai do `fill` e entra por registrarTransicao — ponto único de
        // escrita (ver openspec/changes/guia-status-historico). `app()` em vez de
        // injeção no construtor porque GuiaService já depende deste serviço.
        $guias = app(GuiaService::class);

        foreach ($solicitacao->itens()->with('guia')->orderBy('id')->get() as $item) {
            if ($item->guia) {
                continue;
            }

            $nova = new Guia([
                'tenant_id' => $solicitacao->tenant_id,
                'solicitacao_id' => $solicitacao->id,
                'solicitacao_item_id' => $item->id,
                'convenio_id' => $solicitacao->convenio_id,
                'paciente_id' => $solicitacao->paciente_id,
                'profissional_id' => $item->profissional_id ?? $solicitacao->profissional_id,
                'especialidade_id' => $item->especialidade_id ?? $solicitacao->especialidade_id,
                'data_solicitacao' => $solicitacao->solicitado_em?->toDateString() ?? today()->toDateString(),
                'observacoes' => $solicitacao->observacoes,
                'numero_guia' => $this->numeroGuiaDoItem($solicitacao, $item),
                'tipo_terapia' => self::TIPO_TERAPIA_PADRAO,
                'data_finalizacao' => null,
                'senha' => null,
                'validade_senha' => null,
            ]);

            $guias->registrarTransicao($nova, GuiaStatus::UNDER_REVIEW, [
                'origem' => GuiaStatusHistorico::ORIGEM_MANUAL,
            ]);
        }
    }

    /**
     * Valor de preenchimento para convenio manual, que nao tem numero de
     * operadora.
     *
     * Por ITEM, e nao por solicitacao. Com uma guia por item, um numero por
     * solicitacao faria as N guias nascerem com o mesmo valor — e o indice
     * `(convenio_id, numero_guia)` NAO e unique, entao nada estouraria: qualquer
     * busca por numero passaria a devolver uma guia arbitraria entre as N. Seria
     * trocar um defeito visivel por um invisivel.
     *
     * O prefixo mora em GuiaStatus para a tela poder reconhece-lo como ausencia
     * de numero em vez de exibi-lo como se fosse a guia.
     */
    private function numeroGuiaDoItem(Solicitacao $solicitacao, SolicitacaoItem $item): string
    {
        return GuiaStatus::PREFIXO_NUMERO_PLACEHOLDER.$solicitacao->id.'-'.$item->id;
    }

    private function tenantId(): int
    {
        $tenantId = TenantContext::get() ?? auth()->user()?->tenant_id;

        if (! $tenantId) {
            throw new RuntimeException('Tenant não resolvido para criar solicitação.');
        }

        return (int) $tenantId;
    }
}

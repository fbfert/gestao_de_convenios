<?php

namespace App\Services\Automation;

use App\Exceptions\AutomationConcurrencyException;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\ConvenioEspecialidadeMapeamento;
use App\Models\ConvenioProfissionalMapeamento;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Models\SolicitacaoItem;
use App\Repositories\ConvenioCredencialRepository;
use App\Services\GuiaService;
use App\Services\SolicitacaoService;
use App\Support\SolicitacaoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GerarGuiaUnimedService
{
    private const ACTIVE_STATUSES = ['queued', 'running', 'uncertain'];

    public function __construct(
        private readonly AutomacaoService $automacoes,
        private readonly SolicitacaoService $solicitacoes,
        private readonly ConvenioCredencialRepository $credenciais,
    ) {}

    public function avaliar(SolicitacaoItem $item): array
    {
        $item->loadMissing([
            'solicitacao.convenio',
            'solicitacao.paciente',
            'solicitacao.documentos.arquivo',
            'especialidade',
            'profissional',
            'guia',
            'automacaoExecucoes',
        ]);

        $motivos = [];
        $solicitacao = $item->solicitacao;
        $credential = $this->credenciais->ativa((int) $item->tenant_id, $solicitacao?->convenio_id);

        // O gate é do ITEM, não da solicitação inteira: uma solicitação já
        // aprovada pode receber um item novo (caso de uso de "Adicionar
        // sessões"), e exigir `ready_for_automation` aqui faria o item novo
        // nunca poder ser enviado. O que continua barrado é o que a lista de
        // SolicitacaoStatus::BLOQUEIAM_ENVIO diz — a MESMA lista que a tela usa
        // para decidir se mostra o botão.
        if (SolicitacaoStatus::bloqueiaEnvio($solicitacao?->status)) {
            $motivos[] = 'A Solicitação precisa estar liberada para automatização.';
        }

        if ($solicitacao?->convenio?->connector_driver !== 'unimed_rda') {
            $motivos[] = 'O Convênio não está configurado como Unimed RDA.';
        }

        // A credencial e a DO CONVENIO do item, nao a do tenant. `ativa()` ja
        // cobre os tres casos que dao no mesmo aqui: nao existe, esta pausada,
        // ou falta campo obrigatorio.
        if (! $credential) {
            $motivos[] = 'A credencial Unimed ativa não está configurada.';
        }

        if (! $this->pedidoMedico($item)) {
            $motivos[] = 'Pedido Médico obrigatório não encontrado.';
        }

        if (! $item->profissional || ! $item->especialidade) {
            $motivos[] = 'Profissional e especialidade do item são obrigatórios.';
        }

        if ($solicitacao?->convenio_id && $item->especialidade_id && ! $this->mapeamentoEspecialidade($item)) {
            $motivos[] = 'Mapeamento Especialidade x Convênio não configurado.';
        }

        if ($solicitacao?->convenio_id && $item->profissional_id && ! $this->mapeamentoProfissional($item)) {
            $motivos[] = 'Mapeamento Profissional x Convênio não configurado.';
        }

        if ($item->guia) {
            $motivos[] = 'O item já possui Guia local vinculada.';
        }

        if ($item->automacaoExecucoes->whereIn('status', self::ACTIVE_STATUSES)->isNotEmpty()) {
            $motivos[] = 'Já existe execução Unimed ativa ou incerta para este item.';
        }

        return [
            'eligible' => $motivos === [],
            'motivos' => $motivos,
        ];
    }

    public function enviar(SolicitacaoItem $item): AutomacaoExecucao
    {
        $avaliacao = $this->avaliar($item);

        if (! $avaliacao['eligible']) {
            throw ValidationException::withMessages(['item' => $avaliacao['motivos']]);
        }

        try {
            $execucao = $this->automacoes->enfileirar(
                $item->tenant_id,
                'gerar_guia',
                $item,
                payload: $this->payloadPersistido($item),
            );
        } catch (AutomationConcurrencyException $exception) {
            throw ValidationException::withMessages([
                'item' => ["Já existe execução Unimed ativa para este tenant ({$exception->execucaoId})."],
            ]);
        }

        ExecutarAutomacaoUnimedJob::dispatch($execucao->id);

        return $execucao;
    }

    public function payloadParaWorker(AutomacaoExecucao $execucao): array
    {
        $execucao->loadMissing('solicitacaoItem.solicitacao');
        $credential = $this->credenciais->ativaParaExecucao($execucao);

        return ($execucao->payload ?? []) + [
            'credential' => [
                'login' => $credential->campo('login'),
                'password' => $credential->campo('password'),
                'base_url' => $credential->campo('base_url'),
                'nome_contratado' => $credential->campo('nome_contratado'),
            ],
        ];
    }

    public function aplicarResultado(AutomacaoExecucao $execucao, array $resultado): AutomacaoExecucao
    {
        if (($resultado['status'] ?? null) === 'uncertain') {
            $execucao = $this->automacoes->concluir($execucao, $resultado);
            $execucao->solicitacaoItem?->update(['status_operacional' => 'uncertain']);

            return $execucao;
        }

        return DB::transaction(function () use ($execucao, $resultado) {
            $execucao = $this->automacoes->concluir($execucao, $resultado);

            if ($execucao->status === 'succeeded') {
                $guia = $this->criarOuAtualizarGuia($execucao, $resultado);
                $this->solicitacoes->sincronizarStatusComGuias($guia->solicitacao);
            }

            return $execucao->refresh();
        });
    }

    private function criarOuAtualizarGuia(AutomacaoExecucao $execucao, array $resultado): Guia
    {
        $item = $execucao->solicitacaoItem()->with(['solicitacao', 'profissional', 'especialidade'])->firstOrFail();
        $solicitacao = $item->solicitacao;

        $guia = Guia::query()->firstOrNew([
            'tenant_id' => $execucao->tenant_id,
            'solicitacao_item_id' => $item->id,
        ]);

        $status = $resultado['guia_status'] ?? $resultado['status_guia'] ?? 'under_review';

        $guia->fill([
            'tenant_id' => $execucao->tenant_id,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $item->id,
            'automacao_execucao_id' => $execucao->id,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $item->profissional_id,
            'especialidade_id' => $item->especialidade_id,
            'numero_guia' => $resultado['numero_guia'] ?? null,
            'tipo_terapia' => 'especializada',
            'unimed_status' => $resultado['unimed_status'] ?? $resultado['status_operadora'] ?? null,
            'sessoes_solicitadas' => $resultado['sessoes_solicitadas'] ?? null,
            'sessoes_autorizadas' => $resultado['sessoes_autorizadas'] ?? null,
            'protocolo_operadora' => $resultado['protocolo_operadora'] ?? null,
            'senha' => $resultado['senha'] ?? null,
            'data_solicitacao' => today(),
            'observacoes' => $solicitacao->observacoes,
        ]);

        // Status por registrarTransicao — ponto único de escrita. Origem
        // `automacao`, e por isso a linha do histórico nasce sem usuário.
        app(GuiaService::class)->registrarTransicao($guia, $status, [
            'origem' => GuiaStatusHistorico::ORIGEM_AUTOMACAO,
        ]);

        $item->update(['status_operacional' => 'guia_generated']);

        return $guia;
    }

    private function payloadPersistido(SolicitacaoItem $item): array
    {
        $item->loadMissing([
            'solicitacao.paciente',
            'solicitacao.convenio',
            'solicitacao.medico',
            'solicitacao.cidCadastros',
            'solicitacao.documentos.arquivo',
            'documentos.arquivo',
            'especialidade',
            'profissional',
        ]);
        $pedidoMedico = $this->pedidoMedico($item);
        $mapeamentoEspecialidade = $this->mapeamentoEspecialidade($item);
        $mapeamentoProfissional = $this->mapeamentoProfissional($item);
        $documentos = $this->documentosPayload($item);

        return [
            'solicitacao_id' => $item->solicitacao_id,
            'solicitacao_item_id' => $item->id,
            // O portal Unimed só tem um campo de texto pra indicação clínica
            // (DS_INDIC_CLINICA) — sem lugar pra N CIDs separados. Concatena
            // os códigos numa string só; cai no texto livre legado quando a
            // solicitação não tem nenhum CID cadastrado (dado antigo).
            'cid' => $item->solicitacao->cidCadastros->isNotEmpty()
                ? $item->solicitacao->cidCadastros->pluck('codigo')->implode(', ')
                : $item->solicitacao->cid,
            'medico' => [
                'id' => $item->solicitacao->medico_id,
                'nome' => $item->solicitacao->medico?->nome,
                'crm' => $item->solicitacao->medico?->crm,
                'crm_uf' => $item->solicitacao->medico?->crm_uf,
            ],
            'paciente' => [
                'id' => $item->solicitacao->paciente_id,
                'nome' => $item->solicitacao->paciente?->nome,
                'carteirinha' => $item->solicitacao->paciente?->carteirinha,
            ],
            'convenio_id' => $item->solicitacao->convenio_id,
            'especialidade' => $item->especialidade?->nome,
            'codigo_procedimento' => $mapeamentoEspecialidade?->codigo_procedimento,
            'descricao_operadora' => $mapeamentoEspecialidade?->descricao_operadora,
            'quantidade_padrao' => $mapeamentoEspecialidade?->quantidade_padrao,
            'usa_descricao_generica' => $mapeamentoEspecialidade?->usa_descricao_generica,
            'valor_generico' => $mapeamentoEspecialidade?->valor_generico,
            'profissional' => $item->profissional?->nome,
            'codigo_profissional_operadora' => $mapeamentoProfissional?->codigo_operadora,
            'nome_profissional_operadora' => $mapeamentoProfissional?->nome_operadora,
            'quantidade' => $item->quantidade,
            'pedido_medico' => $pedidoMedico ? [
                'id' => $pedidoMedico->id,
                'nome_original' => $pedidoMedico->nome_original,
                'mime' => $pedidoMedico->mime,
                'path' => $pedidoMedico->path,
                'local_path' => $this->localPath($pedidoMedico->path),
                'size' => $this->fileSize($pedidoMedico->path),
            ] : null,
            'anexos' => $documentos,
        ];
    }

    private function pedidoMedico(SolicitacaoItem $item)
    {
        $item->loadMissing('solicitacao.documentos.arquivo');

        return $item->solicitacao?->documentos
            ->first(fn ($documento) => $documento->arquivo?->tipo === 'pedido_medico')
            ?->arquivo;
    }

    private function documentosPayload(SolicitacaoItem $item): array
    {
        $item->loadMissing(['solicitacao.documentos.arquivo', 'documentos.arquivo']);

        // Documentos da Solicitação valem para todos os itens; os por item só valem para
        // o seu. Sem o filtro, o Plano de uma especialidade subiria na guia de outra.
        return $item->solicitacao?->documentos
            ->whereNull('solicitacao_item_id')
            ->merge($item->documentos)
            ->map(fn ($documento) => $documento->arquivo)
            ->reject(fn ($arquivo) => $arquivo === null || $arquivo->tipo === 'pedido_medico')
            ->map(fn ($arquivo) => [
                'id' => $arquivo->id,
                'tipo' => $arquivo->tipo,
                'nome_original' => $arquivo->nome_original,
                'mime' => $arquivo->mime,
                'path' => $arquivo->path,
                'local_path' => $this->localPath($arquivo->path),
                'size' => $this->fileSize($arquivo->path),
            ])
            ->values()
            ->all() ?? [];
    }

    private function localPath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::disk('local')->path($path);
    }

    private function fileSize(?string $path): ?int
    {
        if (blank($path) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->size($path);
    }

    private function mapeamentoEspecialidade(SolicitacaoItem $item): ?ConvenioEspecialidadeMapeamento
    {
        $item->loadMissing('solicitacao');

        return ConvenioEspecialidadeMapeamento::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('convenio_id', $item->solicitacao?->convenio_id)
            ->where('especialidade_id', $item->especialidade_id)
            ->where('ativo', true)
            ->first();
    }

    private function mapeamentoProfissional(SolicitacaoItem $item): ?ConvenioProfissionalMapeamento
    {
        $item->loadMissing('solicitacao');

        return ConvenioProfissionalMapeamento::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('convenio_id', $item->solicitacao?->convenio_id)
            ->where('profissional_id', $item->profissional_id)
            ->where('ativo', true)
            ->first();
    }
}

<?php

namespace App\Services;

use App\Exceptions\GuiaStatusInvalidoException;
use App\Support\GuiaStatus;
use Illuminate\Validation\ValidationException;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Support\TenantContext;
use App\Models\ConfiguracaoGlobal;
use App\Models\ConvenioRegra;
use Illuminate\Support\Facades\DB;
use App\Services\Concerns\AppliesOwnScope;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Support\OrdenaListagem;
use Illuminate\Support\Arr;
use RuntimeException;

class GuiaService
{
    use AppliesOwnScope;

    public function __construct(
        private readonly SolicitacaoService $solicitacaoService,
    ) {
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $filtros = $this->traduzirFiltrosDoCard($filtros);

        $query = $this->aplicarEscopoOwn(
            Guia::query()->with([
                'solicitacao.medico',
                'convenio',
                'paciente',
                'profissional',
                'especialidade',
                'solicitacaoItem.especialidade',
                'solicitacaoItem.profissional',
                'automacaoExecucao',
                'ultimaAutomacaoUnimed',
            ]),
            'guias.view',
            'guias.viewOwn',
            fn ($query, $user) => $query->where('profissional_id', $user->profissional_id)
        );

        return $query
            ->when(
                Arr::get($filtros, 'mostrar_historico'),
                fn ($query) => $query->comStatusHistorico(),
                fn ($query) => $query->semStatusHistorico(),
            )
            // Filtro de A DEFINIR só faz sentido dentro do universo "vivo"
            // (não histórico): boa parte das 2238 guias históricas ainda tem
            // Especialidade/Profissional "A DEFINIR" (a triagem está em
            // andamento aos poucos), e o botão "Histórico" precisa mostrar
            // TODAS elas de uma vez, corrigidas ou não — senão a exclusão
            // padrão de A DEFINIR escondia justamente as que mais precisam
            // de revisão.
            ->when(
                ! Arr::get($filtros, 'mostrar_historico'),
                fn ($query) => $query->when(
                    Arr::get($filtros, 'mostrar_a_definir'),
                    fn ($query) => $query->comDadosADefinir(),
                    fn ($query) => $query->comDadosDefinidos(),
                ),
            )
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($filtros, 'convenio_id'), fn ($query, $convenioId) => $query->where('convenio_id', $convenioId))
            ->when(Arr::get($filtros, 'paciente_id'), fn ($query, $pacienteId) => $query->where('paciente_id', $pacienteId))
            ->when(Arr::get($filtros, 'profissional_id'), fn ($query, $profissionalId) => $query->where('profissional_id', $profissionalId))
            ->when(Arr::get($filtros, 'paciente_nome'), fn ($query, $pacienteNome) => $query
                ->whereHas('paciente', fn ($query) => $query->where('nome', 'like', '%' . $pacienteNome . '%')))
            ->when(Arr::get($filtros, 'alerta_negacao_pendente'), fn ($query) => $query
                ->where('status', GuiaStatus::DENIED)
                ->whereNull('alerta_negacao_ocultado_em')
                // Guia histórica (rastro de migração, nunca entra em automação —
                // ver Guia::naoHistorica()) não precisa de "nova solicitação":
                // já é passado resolvido, não uma negação pendente de ação.
                ->naoHistorica())
            ->when(Arr::get($filtros, 'validade_senha_vencendo_em_dias') !== null, function ($query) use ($filtros) {
                $dias = (int) $filtros['validade_senha_vencendo_em_dias'];

                $query->whereNotNull('validade_senha')
                    ->whereDate('validade_senha', '>=', today())
                    ->whereDate('validade_senha', '<=', today()->copy()->addDays($dias));
            })
            // Guias com espaço pra lançar sessão — usado pelo seletor de guia
            // na tela de Sessões (substituiu o antigo seletor de Antecipação
            // aberta). Espelha Guia::sessoesDisponiveis() em SQL: não dá pra
            // filtrar por um método de model direto na query.
            ->when(Arr::get($filtros, 'disponivel_para_lancamento'), fn ($query) => $query
                ->whereIn('status', [GuiaStatus::APPROVED, GuiaStatus::FINALIZED])
                ->whereRaw(
                    'COALESCE(sessoes_autorizadas, sessoes_solicitadas, 0) > '
                    .'(SELECT COUNT(*) FROM lancamentos WHERE lancamentos.guia_id = guias.id)'
                ))
            ->tap(fn ($query) => OrdenaListagem::aplicar(
                $query->select('guias.*'),
                $filtros,
                [
                    'numero_guia' => 'guias.numero_guia',
                    'status' => 'guias.status',
                    'senha' => 'guias.senha',
                    'validade' => 'guias.validade_senha',
                    'sessoes_solicitadas' => 'guias.sessoes_solicitadas',
                    'sessoes_autorizadas' => 'guias.sessoes_autorizadas',
                    'paciente' => fn ($query, $direcao) => $query
                        ->leftJoin('pacientes', 'pacientes.id', '=', 'guias.paciente_id')
                        ->orderBy('pacientes.nome', $direcao),
                    'especialidade' => fn ($query, $direcao) => $query
                        ->leftJoin('especialidades', 'especialidades.id', '=', 'guias.especialidade_id')
                        ->orderBy('especialidades.nome', $direcao),
                    'profissional' => fn ($query, $direcao) => $query
                        ->leftJoin('profissionais', 'profissionais.id', '=', 'guias.profissional_id')
                        ->orderBy('profissionais.nome', $direcao),
                ],
                padrao: 'guias.id',
                direcaoPadrao: 'desc',
                desempate: 'guias.id',
            ))
            ->paginate($perPage);
    }

    /**
     * PONTO UNICO de escrita de `guias.status`. Nenhum outro lugar do codigo
     * pode alterar esse campo — a trava esta no observer `saving` do model Guia,
     * e reprova pelo efeito, nao pelo formato do codigo.
     *
     * Aceita modelo ainda nao salvo de proposito: e a unica forma que serve
     * igualmente para os tres pontos que criam a guia inteira num `fill()` so
     * (automacao, confirmacao de incerta, importacao) e para os que apenas mudam
     * o status. O chamador preenche todo o resto e chama isto por ultimo.
     *
     * `de` sai de `getOriginal('status')`, que e nulo na guia nascendo — que ja
     * e a semantica desejada para a primeira linha do historico.
     *
     * @param array{origem?: string, motivo?: string|null, user_id?: int|null, ocorrido_em?: \Carbon\CarbonInterface} $contexto
     */
    public function registrarTransicao(Guia $guia, string $para, array $contexto = []): Guia
    {
        $origem = $contexto['origem'] ?? GuiaStatusHistorico::ORIGEM_MANUAL;
        $ocorridoEm = $contexto['ocorrido_em'] ?? now();
        $de = $guia->exists ? $guia->getOriginal('status') : null;

        // `user_id` so quando e gente. Inferir por auth() daria "manual" para um
        // job que rodasse com usuario resolvido — quem chama sabe a origem.
        $userId = array_key_exists('user_id', $contexto)
            ? $contexto['user_id']
            : ($origem === GuiaStatusHistorico::ORIGEM_MANUAL ? auth()->id() : null);

        return DB::transaction(function () use ($guia, $para, $de, $origem, $ocorridoEm, $userId, $contexto) {
            $guia->status = $para;

            // Carimbos e historico na mesma transacao: e o que impede o cache de
            // divergir da verdade.
            if ($para === GuiaStatus::DENIED) {
                $guia->negada_em = $ocorridoEm;
            }

            if ($para === GuiaStatus::APPROVED) {
                $guia->aprovada_em = $ocorridoEm;
            }

            Guia::permitindoTransicao(fn () => $guia->save());

            GuiaStatusHistorico::query()->create([
                'tenant_id' => $guia->tenant_id,
                'guia_id' => $guia->id,
                'de' => $de,
                'para' => $para,
                'ocorrido_em' => $ocorridoEm,
                'user_id' => $userId,
                'origem' => $origem,
                'motivo' => $contexto['motivo'] ?? null,
            ]);

            return $guia;
        });
    }

    public function criar(array $dados): Guia
    {
        $convenio = \App\Models\Convenio::query()
            ->where('tenant_id', $this->tenantId())
            ->whereKey($dados['convenio_id'])
            ->firstOrFail();

        if ($convenio->connector_driver === 'unimed_rda') {
            throw ValidationException::withMessages([
                'convenio_id' => ['Guias de Convênio Unimed RDA devem ser criadas pela automação do item.'],
            ]);
        }

        // Tudo menos o status; o status entra por registrarTransicao, que salva.
        $guia = new Guia([
            'tenant_id' => $this->tenantId(),
            'solicitacao_id' => $dados['solicitacao_id'] ?? null,
            'solicitacao_item_id' => $dados['solicitacao_item_id'] ?? null,
            'convenio_id' => $dados['convenio_id'],
            'paciente_id' => $dados['paciente_id'],
            'profissional_id' => $dados['profissional_id'],
            'especialidade_id' => $dados['especialidade_id'],
            'numero_guia' => $dados['numero_guia'],
            'tipo_terapia' => $dados['tipo_terapia'],
            'sessoes_solicitadas' => $dados['sessoes_solicitadas'] ?? null,
            'sessoes_autorizadas' => $dados['sessoes_autorizadas'] ?? null,
            'protocolo_operadora' => $dados['protocolo_operadora'] ?? null,
            'data_solicitacao' => $dados['data_solicitacao'],
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => $dados['observacoes'] ?? null,
        ]);

        return $this->registrarTransicao($guia, GuiaStatus::UNDER_REVIEW, [
            'origem' => GuiaStatusHistorico::ORIGEM_MANUAL,
        ]);
    }

    public function buscar(int $id): Guia
    {
        return Guia::query()->with([
            'solicitacao.medico',
            'convenio',
            'paciente',
            'profissional',
            'especialidade',
            'solicitacaoItem.especialidade',
            'solicitacaoItem.profissional',
            'automacaoExecucao.eventos',
            'ultimaAutomacaoUnimed.eventos',
            'lancamentos',
            'conciliacoes',
        ])->findOrFail($id);
    }

    /**
     * Edicao manual (admin): campos de correcao, nunca status/paciente/convenio
     * — ver UpdateGuiaRequest. Sem trava de status de proposito: e uma
     * ferramenta de correcao, deve valer mesmo apos Finalizada/Negada.
     */
    public function atualizar(Guia $guia, array $dados): Guia
    {
        $guia->fill(array_filter([
            'profissional_id' => $dados['profissional_id'] ?? null,
            'especialidade_id' => $dados['especialidade_id'] ?? null,
            'numero_guia' => array_key_exists('numero_guia', $dados) ? $dados['numero_guia'] : null,
            'tipo_terapia' => $dados['tipo_terapia'] ?? null,
            'data_solicitacao' => $dados['data_solicitacao'] ?? null,
            'data_finalizacao' => array_key_exists('data_finalizacao', $dados) ? $dados['data_finalizacao'] : null,
            'sessoes_solicitadas' => array_key_exists('sessoes_solicitadas', $dados) ? $dados['sessoes_solicitadas'] : null,
            'sessoes_autorizadas' => array_key_exists('sessoes_autorizadas', $dados) ? $dados['sessoes_autorizadas'] : null,
            'protocolo_operadora' => array_key_exists('protocolo_operadora', $dados) ? $dados['protocolo_operadora'] : null,
            'senha' => array_key_exists('senha', $dados) ? $dados['senha'] : null,
            'validade_senha' => array_key_exists('validade_senha', $dados) ? $dados['validade_senha'] : null,
            'observacoes' => array_key_exists('observacoes', $dados) ? $dados['observacoes'] : null,
        ], fn ($value) => $value !== null));

        $guia->save();

        return $guia->refresh();
    }

    /**
     * Tambem aceita guia ja 'approved' (achado em 31/08/2026: guias
     * aprovadas pela automacao Unimed pulam direto pra 'approved' e nunca
     * passavam por aqui). Nesse caso senha/validade_senha ja vieram da
     * automacao (CapturarSenhaValidadeUnimedService) e servem de default,
     * mas continuam editaveis pelo usuario.
     *
     * Finalizar e so bookkeeping (senha/validade/data) desde 10/09/2026: o
     * lancamento de sessao nao depende mais disto — guia com status APPROVED
     * ou FINALIZED ja aceita Lancamento (Guia::aceitaLancamento()), a cota
     * e contada ao vivo contra Guia::sessoesDisponiveis(). Antes disso
     * Finalizar abria um ciclo de Antecipacao (removido).
     */
    public function finalizar(Guia $guia, array $dados): Guia
    {
        if (! in_array($guia->status, [GuiaStatus::UNDER_REVIEW, GuiaStatus::APPROVED], true)) {
            throw GuiaStatusInvalidoException::transicaoInvalida($guia->status, GuiaStatus::FINALIZED);
        }

        $senha = $dados['senha'] ?? $guia->senha;
        $validadeSenha = $dados['validade_senha'] ?? $guia->validade_senha?->toDateString();
        $dataFinalizacao = isset($dados['data_finalizacao'])
            ? Carbon::parse($dados['data_finalizacao'])->toDateString()
            : today()->toDateString();

        if (! $senha) {
            throw GuiaStatusInvalidoException::finalizacaoRequerDados();
        }

        if (! $validadeSenha) {
            $regra = ConvenioRegra::query()
                ->where('convenio_id', $guia->convenio_id)
                ->where('tipo_terapia', $guia->tipo_terapia)
                ->whereDate('vigente_desde', '<=', $dataFinalizacao)
                ->where(function ($query) use ($dataFinalizacao) {
                    $query->whereNull('vigente_ate')
                        ->orWhereDate('vigente_ate', '>=', $dataFinalizacao);
                })
                ->orderByDesc('vigente_desde')
                ->first();

            if (! $regra || ! $regra->validade_senha_dias) {
                throw GuiaStatusInvalidoException::finalizacaoRequerDados();
            }

            $validadeSenha = Carbon::parse($dataFinalizacao)
                ->addDays($regra->validade_senha_dias)
                ->toDateString();
        } else {
            $validadeSenha = Carbon::parse($validadeSenha)->toDateString();
        }

        $guia->fill([
            'senha' => $senha,
            'data_finalizacao' => $dataFinalizacao,
            'validade_senha' => $validadeSenha,
        ]);

        $this->registrarTransicao($guia, GuiaStatus::FINALIZED, [
            'origem' => GuiaStatusHistorico::ORIGEM_MANUAL,
        ]);

        if ($guia->solicitacao_id) {
            $this->solicitacaoService->sincronizarStatusComGuias($guia->solicitacao);
        }

        return $guia->refresh();
    }

    /**
     * Oculta o alerta de guia negada (tela de Guias + Dashboard). Sem trava
     * de status de proposito: se a guia deixar de estar 'denied' por algum
     * motivo, ocultar continua sendo uma operacao valida e idempotente — so
     * nao aparece mais no alerta de qualquer forma, ja que o filtro exige
     * status='denied'.
     */
    public function ocultarAlertaNegacao(Guia $guia): Guia
    {
        $guia->forceFill(['alerta_negacao_ocultado_em' => now()])->save();

        return $guia->refresh();
    }

    public function negar(Guia $guia, ?string $observacoes = null): Guia
    {
        if ($guia->status !== GuiaStatus::UNDER_REVIEW) {
            throw GuiaStatusInvalidoException::transicaoInvalida($guia->status, GuiaStatus::DENIED);
        }

        $guia->fill(['observacoes' => $observacoes ?? $guia->observacoes]);

        $this->registrarTransicao($guia, GuiaStatus::DENIED, [
            'origem' => GuiaStatusHistorico::ORIGEM_MANUAL,
            'motivo' => $observacoes,
        ]);

        return $guia->refresh();
    }

    /**
     * Traduz os filtros que as linhas do card do dashboard usam na URL para os
     * filtros internos da listagem.
     *
     * `pendente=1` so faz sentido junto de `status=denied`: e "negada e ainda
     * nao tratada". Sozinho ele nao significa nada, e por isso e ignorado.
     *
     * `senha_vencendo=1` resolve a janela pelo `senha_alerta_dias` do tenant —
     * regra e dado, nunca codigo (ADR-03). Este e o primeiro consumidor dessa
     * configuracao, que existia sem ninguem ler.
     */
    private function traduzirFiltrosDoCard(array $filtros): array
    {
        $pendente = Arr::pull($filtros, 'pendente');
        $senhaVencendo = Arr::pull($filtros, 'senha_vencendo');

        if ($pendente && ($filtros['status'] ?? null) === GuiaStatus::DENIED) {
            $filtros['alerta_negacao_pendente'] = 1;
        }

        if ($senhaVencendo && ! isset($filtros['validade_senha_vencendo_em_dias'])) {
            $filtros['validade_senha_vencendo_em_dias'] = (int) ConfiguracaoGlobal::doTenant($this->tenantId())
                ->senha_alerta_dias;
        }

        return $filtros;
    }

    private function tenantId(): int
    {
        $tenantId = TenantContext::get() ?? auth()->user()?->tenant_id;

        if (! $tenantId) {
            throw new RuntimeException('Tenant não resolvido para criar guia.');
        }

        return (int) $tenantId;
    }
}

<?php

namespace App\Services;

use App\Exceptions\GuiaStatusInvalidoException;
use App\Support\GuiaStatus;
use Illuminate\Validation\ValidationException;
use App\Models\Guia;
use App\Support\TenantContext;
use App\Models\ConvenioRegra;
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
        private readonly AntecipacaoService $antecipacaoService,
        private readonly SolicitacaoService $solicitacaoService,
    ) {
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
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

        return Guia::query()->create([
            'tenant_id' => $this->tenantId(),
            'solicitacao_id' => $dados['solicitacao_id'] ?? null,
            'solicitacao_item_id' => $dados['solicitacao_item_id'] ?? null,
            'convenio_id' => $dados['convenio_id'],
            'paciente_id' => $dados['paciente_id'],
            'profissional_id' => $dados['profissional_id'],
            'especialidade_id' => $dados['especialidade_id'],
            'numero_guia' => $dados['numero_guia'],
            'tipo_terapia' => $dados['tipo_terapia'],
            'status' => GuiaStatus::UNDER_REVIEW,
            'sessoes_solicitadas' => $dados['sessoes_solicitadas'] ?? null,
            'sessoes_autorizadas' => $dados['sessoes_autorizadas'] ?? null,
            'protocolo_operadora' => $dados['protocolo_operadora'] ?? null,
            'data_solicitacao' => $dados['data_solicitacao'],
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => $dados['observacoes'] ?? null,
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
            'antecipacoes',
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
     * passavam por aqui, entao nunca abriam ciclo de Antecipacao — sem
     * ciclo, nenhum Lancamento de sessao tem cota pra consumir). Nesse caso
     * senha/validade_senha ja vieram da automacao (CapturarSenhaValidadeUnimedService)
     * e servem de default, mas continuam editaveis pelo usuario.
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
            'status' => GuiaStatus::FINALIZED,
            'senha' => $senha,
            'data_finalizacao' => $dataFinalizacao,
            'validade_senha' => $validadeSenha,
        ]);
        $guia->save();

        $this->antecipacaoService->abrirCiclo($guia);

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

        $guia->fill([
            'status' => GuiaStatus::DENIED,
            'observacoes' => $observacoes ?? $guia->observacoes,
        ]);
        $guia->save();

        return $guia->refresh();
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

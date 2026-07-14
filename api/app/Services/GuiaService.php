<?php

namespace App\Services;

use App\Exceptions\GuiaStatusInvalidoException;
use App\Models\Guia;
use App\Support\TenantContext;
use App\Models\ConvenioRegra;
use App\Services\Concerns\AppliesOwnScope;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use RuntimeException;

class GuiaService
{
    use AppliesOwnScope;

    public function __construct(
        private readonly AntecipacaoService $antecipacaoService
    ) {
    }

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->aplicarEscopoOwn(
            Guia::query()->with(['solicitacao', 'convenio', 'paciente', 'profissional', 'especialidade']),
            'guias.view',
            'guias.viewOwn',
            fn ($query, $user) => $query->where('profissional_id', $user->profissional_id)
        );

        return $query
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($filtros, 'convenio_id'), fn ($query, $convenioId) => $query->where('convenio_id', $convenioId))
            ->when(Arr::get($filtros, 'paciente_id'), fn ($query, $pacienteId) => $query->where('paciente_id', $pacienteId))
            ->when(Arr::get($filtros, 'validade_senha_vencendo_em_dias') !== null, function ($query) use ($filtros) {
                $dias = (int) $filtros['validade_senha_vencendo_em_dias'];

                $query->whereNotNull('validade_senha')
                    ->whereDate('validade_senha', '>=', today())
                    ->whereDate('validade_senha', '<=', today()->copy()->addDays($dias));
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function criar(array $dados): Guia
    {
        return Guia::query()->create([
            'tenant_id' => $this->tenantId(),
            'solicitacao_id' => $dados['solicitacao_id'] ?? null,
            'convenio_id' => $dados['convenio_id'],
            'paciente_id' => $dados['paciente_id'],
            'profissional_id' => $dados['profissional_id'],
            'especialidade_id' => $dados['especialidade_id'],
            'numero_guia' => $dados['numero_guia'],
            'tipo_terapia' => $dados['tipo_terapia'],
            'status' => 'under_review',
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
            'solicitacao',
            'convenio',
            'paciente',
            'profissional',
            'especialidade',
            'antecipacoes',
            'conciliacoes',
        ])->findOrFail($id);
    }

    public function finalizar(Guia $guia, array $dados): Guia
    {
        if ($guia->status !== 'under_review') {
            throw GuiaStatusInvalidoException::transicaoInvalida($guia->status, 'finalized');
        }

        $senha = $dados['senha'] ?? null;
        $validadeSenha = $dados['validade_senha'] ?? null;
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
            'status' => 'finalized',
            'senha' => $senha,
            'data_finalizacao' => $dataFinalizacao,
            'validade_senha' => $validadeSenha,
        ]);
        $guia->save();

        $this->antecipacaoService->abrirCiclo($guia);

        return $guia->refresh();
    }

    public function negar(Guia $guia, ?string $observacoes = null): Guia
    {
        if ($guia->status !== 'under_review') {
            throw GuiaStatusInvalidoException::transicaoInvalida($guia->status, 'denied');
        }

        $guia->fill([
            'status' => 'denied',
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

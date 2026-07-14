<?php

namespace App\Services;

use App\Exceptions\SolicitacaoStatusInvalidoException;
use App\Models\Solicitacao;
use App\Support\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use RuntimeException;

class SolicitacaoService
{
    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        return Solicitacao::query()
            ->with([
                'paciente',
                'profissional',
                'especialidade',
                'convenio',
                'medico',
                'guia.paciente',
                'guia.convenio',
                'guia.profissional',
                'guia.especialidade',
                'guia.antecipacoes',
                'guia.conciliacoes',
            ])
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($filtros, 'convenio_id'), fn ($query, $convenioId) => $query->where('convenio_id', $convenioId))
            ->when(Arr::get($filtros, 'medico_id'), fn ($query, $medicoId) => $query->where('medico_id', $medicoId))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function criar(array $dados): Solicitacao
    {
        return Solicitacao::query()->create([
            'tenant_id' => $this->tenantId(),
            'paciente_id' => $dados['paciente_id'],
            'profissional_id' => $dados['profissional_id'],
            'especialidade_id' => $dados['especialidade_id'],
            'convenio_id' => $dados['convenio_id'],
            'medico_id' => $dados['medico_id'],
            'status' => 'under_review',
            'solicitado_em' => $dados['solicitado_em'],
            'observacoes' => $dados['observacoes'] ?? null,
        ]);
    }

    public function aprovar(Solicitacao $solicitacao): Solicitacao
    {
        $this->validarTransicao($solicitacao, 'approved');
        $solicitacao->update(['status' => 'approved']);

        return $solicitacao->refresh();
    }

    public function negar(Solicitacao $solicitacao): Solicitacao
    {
        $this->validarTransicao($solicitacao, 'denied');
        $solicitacao->update(['status' => 'denied']);

        return $solicitacao->refresh();
    }

    private function validarTransicao(Solicitacao $solicitacao, string $destino): void
    {
        if ($solicitacao->status !== 'under_review') {
            throw SolicitacaoStatusInvalidoException::transicaoInvalida($solicitacao->status, $destino);
        }
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

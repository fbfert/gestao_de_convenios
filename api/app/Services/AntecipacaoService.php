<?php

namespace App\Services;

use App\Exceptions\AntecipacaoCotaEsgotadaException;
use App\Exceptions\ConvenioRegraNaoEncontradaException;
use App\Models\Antecipacao;
use App\Models\ConvenioRegra;
use App\Models\Guia;
use App\Services\Concerns\AppliesOwnScope;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Support\OrdenaListagem;
use Illuminate\Support\Arr;

class AntecipacaoService
{
    use AppliesOwnScope;

    public function listar(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->aplicarEscopoOwn(
            Antecipacao::query()->with(['guia.especialidade', 'paciente', 'convenio', 'lancamentos']),
            'antecipacoes.view',
            'antecipacoes.viewOwn',
            fn ($query, $user) => $query->whereHas('guia', fn ($guiaQuery) => $guiaQuery->where('profissional_id', $user->profissional_id))
        );

        return $query
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($filtros, 'paciente_id'), fn ($query, $pacienteId) => $query->where('paciente_id', $pacienteId))
            ->when(Arr::get($filtros, 'convenio_id'), fn ($query, $convenioId) => $query->where('convenio_id', $convenioId))
            ->tap(fn ($query) => OrdenaListagem::aplicar(
                $query->select('antecipacoes.*'),
                $filtros,
                [
                    'id' => 'antecipacoes.id',
                    'cota' => 'antecipacoes.qtd_utilizada',
                    'status' => 'antecipacoes.status',
                    'paciente' => fn ($query, $direcao) => $query
                        ->leftJoin('pacientes', 'pacientes.id', '=', 'antecipacoes.paciente_id')
                        ->orderBy('pacientes.nome', $direcao),
                    'convenio' => fn ($query, $direcao) => $query
                        ->leftJoin('convenios', 'convenios.id', '=', 'antecipacoes.convenio_id')
                        ->orderBy('convenios.nome', $direcao),
                ],
                padrao: 'antecipacoes.id',
                direcaoPadrao: 'desc',
                desempate: 'antecipacoes.id',
            ))
            ->paginate($perPage);
    }

    public function buscar(int $id): Antecipacao
    {
        return Antecipacao::query()
            ->with(['guia.especialidade', 'paciente', 'convenio', 'lancamentos.profissional'])
            ->findOrFail($id);
    }

    /**
     * Edicao manual (admin): so qtd_autorizada/ciclo_inicio/ciclo_fim, nunca
     * qtd_utilizada/status/vinculos — ver UpdateAntecipacaoRequest. Quando
     * qtd_autorizada muda, reavalia o status a partir da cota real em vez de
     * deixá-lo inerte: sem isso, aumentar a cota de um ciclo já fechado não o
     * reabriria (nada mais reavalia status fora de consumirCota()/remover()).
     */
    public function atualizar(Antecipacao $antecipacao, array $dados): Antecipacao
    {
        $antecipacao->fill(array_filter([
            'qtd_autorizada' => $dados['qtd_autorizada'] ?? null,
            'ciclo_inicio' => $dados['ciclo_inicio'] ?? null,
            'ciclo_fim' => $dados['ciclo_fim'] ?? null,
        ], fn ($value) => $value !== null));

        if ($antecipacao->isDirty('qtd_autorizada')) {
            $antecipacao->status = $antecipacao->qtd_utilizada >= $antecipacao->qtd_autorizada ? 'closed' : 'open';
        }

        $antecipacao->save();

        return $antecipacao->refresh();
    }

    public function abrirCiclo(Guia $guia): Antecipacao
    {
        $regra = ConvenioRegra::query()
            ->where('convenio_id', $guia->convenio_id)
            ->where('tipo_terapia', $guia->tipo_terapia)
            ->where('vigente_desde', '<=', today())
            ->where(function ($query) {
                $query->whereNull('vigente_ate')
                    ->orWhere('vigente_ate', '>=', today());
            })
            ->orderByDesc('vigente_desde')
            ->first();

        if (! $regra) {
            throw ConvenioRegraNaoEncontradaException::forGuia(
                (int) $guia->convenio_id,
                (string) $guia->tipo_terapia
            );
        }

        $cicloInicio = today();

        // guia.sessoes_autorizadas e o total realmente autorizado nesta guia
        // (capturado da operadora, ex.: 10) — quando existe, a cota do ciclo
        // e ele, nao o qtd_autorizada_por_ciclo do convenio (que descreve o
        // ritmo de liberacao, ex.: "1 por dia", nao o total da guia; sem essa
        // preferencia a guia so conseguia lancar 1 sessao no total, pois nada
        // reabre um novo ciclo diario sozinho). O ciclo passa a cobrir a
        // validade da senha, ja que ele vale pro total autorizado e nao mais
        // so pra 1 dia.
        $qtdAutorizada = $guia->sessoes_autorizadas ?? $regra->qtd_autorizada_por_ciclo;
        $cicloFim = $guia->sessoes_autorizadas && $guia->validade_senha
            ? $guia->validade_senha->copy()
            : $this->calcularCicloFim($cicloInicio, $regra->frequencia_lancamento);

        return Antecipacao::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'paciente_id' => $guia->paciente_id,
            'convenio_id' => $guia->convenio_id,
            'ciclo_inicio' => $cicloInicio,
            'ciclo_fim' => $cicloFim,
            'qtd_autorizada' => $qtdAutorizada,
            'qtd_utilizada' => 0,
            'status' => 'open',
        ]);
    }

    public function consumirCota(Antecipacao $antecipacao): void
    {
        if ($antecipacao->status === 'closed' || $antecipacao->qtd_utilizada >= $antecipacao->qtd_autorizada) {
            throw AntecipacaoCotaEsgotadaException::forAntecipacao((int) $antecipacao->id);
        }

        $antecipacao->qtd_utilizada++;

        if ($antecipacao->qtd_utilizada >= $antecipacao->qtd_autorizada) {
            $antecipacao->status = 'closed';
        }

        $antecipacao->save();
    }

    /**
     * Mesmo incremento de consumirCota(), mas nunca lança
     * AntecipacaoCotaEsgotadaException — usada só pela importação de Sessões
     * históricas: a sessão já aconteceu de verdade, então travar por causa da
     * cota (que é bookkeeping, não o fato em si) esconderia dado real. Fecha
     * a Antecipação do mesmo jeito quando bate ou passa da cota.
     */
    public function consumirCotaForcado(Antecipacao $antecipacao): void
    {
        $antecipacao->qtd_utilizada++;

        if ($antecipacao->qtd_utilizada >= $antecipacao->qtd_autorizada) {
            $antecipacao->status = 'closed';
        }

        $antecipacao->save();
    }

    private function calcularCicloFim(Carbon $inicio, string $frequencia): Carbon
    {
        return match ($frequencia) {
            'diaria' => $inicio->copy(),
            'semanal' => $inicio->copy()->addDays(6),
            'mensal' => $inicio->copy()->addMonthNoOverflow()->subDay(),
            default => $inicio->copy(),
        };
    }
}

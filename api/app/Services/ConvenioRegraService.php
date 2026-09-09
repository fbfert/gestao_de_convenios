<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\ConvenioRegra;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConvenioRegraService
{
    public function criar(Convenio $convenio, array $dados): ConvenioRegra
    {
        return DB::transaction(function () use ($convenio, $dados) {
            $vigenteDesde = Carbon::parse($dados['vigente_desde'])->startOfDay();
            ConvenioRegra::query()
                ->where('convenio_id', $convenio->id)
                ->where('tipo_terapia', $dados['tipo_terapia'])
                ->whereNull('vigente_ate')
                ->update(['vigente_ate' => $vigenteDesde->copy()->subDay()->toDateString()]);

            return ConvenioRegra::query()->create([
                ...$dados,
                'tenant_id' => $convenio->tenant_id,
                'convenio_id' => $convenio->id,
                'vigente_desde' => $vigenteDesde->toDateString(),
            ]);
        });
    }

    public function encerrar(ConvenioRegra $regra, ?string $data): ConvenioRegra
    {
        $regra->update(['vigente_ate' => Carbon::parse($data ?? today())->toDateString()]);
        return $regra;
    }

    /**
     * A regra do convênio em vigor hoje para um tipo de terapia — nula quando
     * não há nenhuma cadastrada, o que é resposta legítima e não erro.
     *
     * Mesma leitura que `AntecipacaoService` já fazia inline: `vigente_desde`
     * no passado e `vigente_ate` nulo ou no futuro, a mais recente primeiro.
     * Aqui ela vira método porque passou a ter um segundo consumidor — a
     * quantidade padrão de sessões —, e regra de convênio lida em dois lugares
     * por conta própria é regra que diverge.
     */
    public function vigente(int $convenioId, string $tipoTerapia): ?ConvenioRegra
    {
        $hoje = today()->toDateString();

        return ConvenioRegra::query()
            ->where('convenio_id', $convenioId)
            ->where('tipo_terapia', $tipoTerapia)
            ->whereDate('vigente_desde', '<=', $hoje)
            ->where(fn ($query) => $query
                ->whereNull('vigente_ate')
                ->orWhereDate('vigente_ate', '>=', $hoje))
            ->orderByDesc('vigente_desde')
            ->first();
    }
}

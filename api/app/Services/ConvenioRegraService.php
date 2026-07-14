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
}

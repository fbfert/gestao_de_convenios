<?php

namespace App\Services\ClinicaSync;

use App\Models\ClinicaPushPendencia;
use App\Models\Paciente;
use App\Models\Profissional;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Resolve as pendências abertas antes de criar paciente/profissional novo
 * no clinica (ver interceptarSemMatchExato em PacienteSyncService e
 * ProfissionalSyncService) — espelha ClinicaPendenteService, mas pro
 * sentido push: aqui o local já existe, o candidato é remoto.
 */
class ClinicaPushPendenteService
{
    /**
     * Vincula: o local passa a apontar pro candidato remoto escolhido —
     * nada é criado no clinica. O próximo pull traz os dados de verdade e
     * reconcilia normalmente.
     */
    public function confirmar(ClinicaPushPendencia $pendencia, int $clinicaIdEscolhido): void
    {
        if ($pendencia->status !== 'pendente') {
            throw new InvalidArgumentException('Pendência já foi resolvida.');
        }

        $candidatosValidos = collect($pendencia->candidatos_json)->pluck('clinica_id')->all();
        if (! in_array($clinicaIdEscolhido, $candidatosValidos, true)) {
            throw new InvalidArgumentException('O vínculo escolhido não está entre os candidatos sugeridos.');
        }

        $modelo = $this->localModelo($pendencia);

        $modelo->timestamps = false;
        $modelo->forceFill([
            'clinica_id' => $clinicaIdEscolhido,
            'sincronizado_em' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ])->save();

        $pendencia->forceFill([
            'status' => 'confirmado',
            'clinica_id_escolhido' => $clinicaIdEscolhido,
            'resolvido_por' => request()->user()?->id,
            'resolvido_em' => CarbonImmutable::now(),
        ])->save();
    }

    /** Rejeita: são registros diferentes — a próxima rodada de push cria normalmente no clinica. */
    public function rejeitar(ClinicaPushPendencia $pendencia): void
    {
        if ($pendencia->status !== 'pendente') {
            throw new InvalidArgumentException('Pendência já foi resolvida.');
        }

        $pendencia->forceFill([
            'status' => 'rejeitado',
            'resolvido_por' => request()->user()?->id,
            'resolvido_em' => CarbonImmutable::now(),
        ])->save();
    }

    private function localModelo(ClinicaPushPendencia $pendencia): Paciente|Profissional
    {
        $classe = $pendencia->tipo === 'paciente' ? Paciente::class : Profissional::class;

        $modelo = $classe::where('tenant_id', $pendencia->tenant_id)->find($pendencia->local_id);

        if ($modelo === null) {
            throw new InvalidArgumentException('Cadastro local não encontrado neste tenant.');
        }

        return $modelo;
    }
}

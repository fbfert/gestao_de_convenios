<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila de revisão: paciente vindo da clinica que não bateu por clinica_id
 * nem CPF exatos, mas parece (~90% de nome) ser alguém já cadastrado no
 * gescon sem vínculo. Um humano confirma ou rejeita — nunca é decidido
 * sozinho, pra não duplicar nem contaminar um cadastro existente
 * (ver ClinicaSync\PacienteSyncService::pullUm e ClinicaPacientePendenteService).
 */
class ClinicaPacientePendente extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'clinica_pacientes_pendentes';

    protected $fillable = [
        'tenant_id',
        'clinica_id',
        'dados_remoto',
        'remoto_atualizado_em',
        'status',
        'candidato_paciente_id',
        'similaridade',
        'candidatos_json',
        'resolvido_por',
        'resolvido_em',
    ];

    protected $casts = [
        'dados_remoto' => 'array',
        'candidatos_json' => 'array',
        'remoto_atualizado_em' => 'datetime',
        'resolvido_em' => 'datetime',
    ];

    public function candidato()
    {
        return $this->belongsTo(Paciente::class, 'candidato_paciente_id');
    }

    public function resolvidoPor()
    {
        return $this->belongsTo(User::class, 'resolvido_por');
    }
}

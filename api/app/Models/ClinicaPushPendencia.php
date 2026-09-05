<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila de revisão pro sentido PUSH: antes de criar um paciente/profissional
 * novo no clinica, achou-se lá alguém com nome parecido ainda não vinculado
 * a nenhum registro local — um humano confirma (vincula ao candidato) ou
 * rejeita (segue e cria normalmente). Espelha ClinicaPacientePendente, que
 * cobre o sentido inverso (pull).
 */
class ClinicaPushPendencia extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'clinica_push_pendencias';

    protected $fillable = [
        'tenant_id',
        'tipo',
        'local_id',
        'candidatos_json',
        'status',
        'clinica_id_escolhido',
        'resolvido_por',
        'resolvido_em',
    ];

    protected $casts = [
        'candidatos_json' => 'array',
        'resolvido_em' => 'datetime',
    ];

    public function resolvidoPor()
    {
        return $this->belongsTo(User::class, 'resolvido_por');
    }
}

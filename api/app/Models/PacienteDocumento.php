<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Imagem de documento do paciente com prazo de validade — hoje, só a
 * carteirinha lida pela IA.
 *
 * Sem o trait Auditable de propósito: o vínculo com o paciente já aparece na
 * auditoria do próprio paciente, e o expurgo diário geraria um evento por
 * imagem apagada, enchendo a trilha de ruído previsível.
 */
class PacienteDocumento extends Model
{
    use BelongsToTenant;

    protected $table = 'paciente_documentos';

    protected $fillable = [
        'tenant_id', 'paciente_id', 'tipo', 'path', 'mime', 'nome_original', 'expira_em',
    ];

    protected $casts = [
        'expira_em' => 'datetime',
    ];

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }
}

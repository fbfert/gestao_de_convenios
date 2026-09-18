<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma mudança de estado de um componente monitorado.
 *
 * Sem `Auditable`: a trilha de auditoria registra o que PESSOAS fizeram, e isto
 * é o sistema observando a si mesmo. Auditar cada queda encheria a trilha de
 * eventos sem autor, exatamente o que `SaudeService` já evita no heartbeat.
 */
class SaudeComponenteEvento extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'saude_componente_eventos';

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'saude_componente_id',
        'estado',
        'ocorrido_em',
        'mensagem',
    ];

    protected $casts = [
        'ocorrido_em' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function componente()
    {
        return $this->belongsTo(SaudeComponente::class, 'saude_componente_id');
    }
}

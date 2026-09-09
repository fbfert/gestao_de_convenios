<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma transicao de status de guia.
 *
 * Sem o trait Auditable de proposito: esta tabela JA E a trilha desta mudanca
 * especifica, com mais contexto que o diff cru (origem e motivo). Audita-la
 * geraria dois registros para o mesmo evento — o mesmo argumento do ADR-07 para
 * os pontos que tem evento explicito melhor que o diff.
 */
class GuiaStatusHistorico extends Model
{
    use BelongsToTenant, HasFactory;

    /** Origem da transicao. */
    public const ORIGEM_MANUAL = 'manual';

    public const ORIGEM_AUTOMACAO = 'automacao';

    public const ORIGEM_IMPORTACAO = 'importacao';

    public const ORIGEM_MIGRACAO = 'migracao';

    protected $table = 'guia_status_historico';

    /** Linha de historico nao e editada. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'guia_id',
        'de',
        'para',
        'ocorrido_em',
        'user_id',
        'origem',
        'motivo',
    ];

    protected $casts = [
        'ocorrido_em' => 'datetime',
    ];

    public function guia()
    {
        return $this->belongsTo(Guia::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

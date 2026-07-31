<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomacaoExecucao extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'automacao_execucoes';

    protected $fillable = [
        'tenant_id',
        'solicitacao_item_id',
        'guia_id',
        'operacao',
        'status',
        'idempotency_key',
        'payload',
        'resultado',
        'erro_codigo',
        'erro_mensagem',
        'queued_at',
        'started_at',
        'finished_at',
        'parent_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'resultado' => 'array',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function eventos()
    {
        return $this->hasMany(AutomacaoEvento::class, 'automacao_execucao_id');
    }

    public function solicitacaoItem()
    {
        return $this->belongsTo(SolicitacaoItem::class);
    }

    public function guia()
    {
        return $this->belongsTo(Guia::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}

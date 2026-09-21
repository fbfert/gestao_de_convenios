<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AutomacaoExecucao extends Model
{
    use BelongsToTenant, HasFactory;

    /** Ainda não chegou a um resultado final — o item continua preso a ela. */
    public const STATUS_ATIVOS = ['queued', 'running', 'uncertain'];

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

    /**
     * A execução ativa mais recente de uma coleção já carregada (uma guia
     * pode ter várias ao longo do tempo; só a última em aberto interessa pra
     * decidir se o item está livre pra reenvio). Mesmo formato usado por
     * SolicitacaoResource e AntecipacaoService — dois lugares que resolvem a
     * MESMA pergunta ("este item tem uma execução travando o reenvio?") pra
     * telas diferentes (Solicitações e Antecipações).
     *
     * @param  Collection<int, self>  $execucoes
     */
    public static function ativaMaisRecente($execucoes): ?array
    {
        $execucao = $execucoes
            ->whereIn('status', self::STATUS_ATIVOS)
            ->sortByDesc('id')
            ->first();

        if (! $execucao) {
            return null;
        }

        return [
            'id' => $execucao->id,
            'operacao' => $execucao->operacao,
            'status' => $execucao->status,
            'queued_at' => $execucao->queued_at?->toISOString(),
        ];
    }
}

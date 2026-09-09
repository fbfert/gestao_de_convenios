<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Um alerta aberto (ou ja resolvido) do tenant.
 *
 * Alerta e do TENANT, nao do usuario: `reconhecido_por` registra quem viu, e nao
 * para quem o alerta e.
 */
class Alerta extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const NIVEL_VERDE = 'verde';

    public const NIVEL_AMARELO = 'amarelo';

    public const NIVEL_VERMELHO = 'vermelho';

    /** Niveis que aparecem no card do dashboard — verde nao entra. */
    public const NIVEIS_DO_CARD = [self::NIVEL_AMARELO, self::NIVEL_VERMELHO];

    /**
     * Marca de deduplicacao. Vale 1 enquanto aberto e NULL quando resolvido —
     * ver o cabecalho da migration para o porque.
     */
    public const DEDUPE_ABERTO = 1;

    protected $table = 'alertas';

    protected $fillable = [
        'tenant_id',
        'chave',
        'nivel',
        'titulo',
        'descricao',
        'entidade',
        'entidade_id',
        'dados',
        'aberto_em',
        'resolvido_em',
        'reconhecido_por',
        'reconhecido_em',
        'silenciado_ate',
        'aberto_dedupe',
        'notificado_em',
    ];

    protected $casts = [
        'dados' => 'array',
        'aberto_em' => 'datetime',
        'resolvido_em' => 'datetime',
        'reconhecido_em' => 'datetime',
        'silenciado_ate' => 'datetime',
        'notificado_em' => 'datetime',
    ];

    public function reconhecidoPor()
    {
        return $this->belongsTo(User::class, 'reconhecido_por');
    }

    public function scopeAberto(Builder $query): Builder
    {
        return $query->whereNull('resolvido_em');
    }

    public function scopeResolvido(Builder $query): Builder
    {
        return $query->whereNotNull('resolvido_em');
    }

    /**
     * Aberto e nao silenciado — o que de fato cobra acao agora.
     *
     * Silenciar tem prazo de proposito: silenciar sem data seria fechar, e
     * fechar ja e papel do avaliador quando a causa some.
     */
    public function scopePendente(Builder $query): Builder
    {
        return $query->aberto()->where(function (Builder $q) {
            $q->whereNull('silenciado_ate')->orWhere('silenciado_ate', '<=', now());
        });
    }

    public function estaSilenciado(): bool
    {
        return $this->silenciado_ate !== null && $this->silenciado_ate->isFuture();
    }
}

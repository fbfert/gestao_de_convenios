<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Quem, dentro da clinica, recebe alerta por e-mail.
 *
 * O par global (a Xiax) e AlertaDestinatarioGlobal, em tabela separada e SEM
 * este trait — ver o cabecalho daquele model.
 */
class AlertaDestinatario extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    public const CANAL_DIGEST = 'digest';

    public const CANAL_IMEDIATO = 'imediato';

    public const CANAL_AMBOS = 'ambos';

    /** Falhas seguidas a partir das quais o destinatario e desativado. */
    public const LIMITE_FALHAS = 5;

    protected $table = 'alerta_destinatarios';

    protected $fillable = [
        'tenant_id',
        'email',
        'nome',
        'niveis',
        'chaves',
        'canal',
        'horario_digest',
        'ativo',
        'verificado_em',
        'falhas_consecutivas',
    ];

    protected $casts = [
        'niveis' => 'array',
        'chaves' => 'array',
        'ativo' => 'boolean',
        'verificado_em' => 'datetime',
        'horario_digest' => 'integer',
        'falhas_consecutivas' => 'integer',
    ];

    /**
     * `chaves` nulo significa TODAS de proposito: e o padrao mais util para
     * quem acabou de cadastrar e ainda nao sabe quais existem.
     */
    public function querReceber(string $nivel, string $chave): bool
    {
        if (! in_array($nivel, $this->niveis ?? [], true)) {
            return false;
        }

        return $this->chaves === null || in_array($chave, $this->chaves, true);
    }

    public function aceitaCanal(string $canal): bool
    {
        return $this->canal === $canal || $this->canal === self::CANAL_AMBOS;
    }
}

<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * O suporte da Xiax: recebe alerta de TODOS os tenants.
 *
 * SEM o trait BelongsToTenant, e em tabela sem `tenant_id`. Isto nao e detalhe
 * de modelagem, e a decisao central desta fase: com `tenant_id` nulo na tabela
 * do tenant, o global scope do trait esconderia o registro de qualquer consulta
 * que esquecesse `withoutGlobalScopes()`. O suporte simplesmente pararia de
 * receber, e ausencia de e-mail parece "sem problemas" — falha silenciosa no
 * exato mecanismo que existe para avisar de falhas.
 */
class AlertaDestinatarioGlobal extends Model
{
    use Auditable, HasFactory;

    public const LIMITE_FALHAS = 5;

    protected $table = 'alerta_destinatarios_globais';

    protected $fillable = [
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

    public function querReceber(string $nivel, string $chave): bool
    {
        if (! in_array($nivel, $this->niveis ?? [], true)) {
            return false;
        }

        return $this->chaves === null || in_array($chave, $this->chaves, true);
    }

    public function aceitaCanal(string $canal): bool
    {
        return $this->canal === $canal || $this->canal === AlertaDestinatario::CANAL_AMBOS;
    }
}

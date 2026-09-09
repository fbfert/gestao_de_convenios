<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * O que dispara alerta, e com que limiar, para este tenant.
 *
 * Com Auditable de proposito: limiar configuravel convida a desligar o alerta
 * incomodo, e ligar/desligar auditado deixa essa decisao rastreavel.
 */
class AlertaRegra extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /** Chaves das quatro regras desta entrega. */
    public const CHAVE_SENHA_VENCENDO = 'senha.vencendo';

    public const CHAVE_GUIA_NEGADA = 'guia.negada';

    public const CHAVE_AUTOMACAO_FALHAS = 'automacao.falhas_em_serie';

    public const CHAVE_COMPONENTE_FORA = 'componente.fora';

    protected $table = 'alerta_regras';

    protected $fillable = [
        'tenant_id',
        'chave',
        'ativo',
        'nivel_base',
        'limiar_amarelo',
        'limiar_vermelho',
        'critica',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'critica' => 'boolean',
        'limiar_amarelo' => 'integer',
        'limiar_vermelho' => 'integer',
    ];
}

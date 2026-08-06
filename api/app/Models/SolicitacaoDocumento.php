<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoDocumento extends Model
{
    use BelongsToTenant, HasFactory;

    /** Anexos que valem para a Solicitação inteira. */
    public const TIPOS_DA_SOLICITACAO = ['pedido_medico', 'laudo_medico'];

    /** Anexos que existem por especialidade, ou seja, por item da Solicitação. */
    public const TIPOS_POR_ITEM = ['plano_individualizado', 'relatorio_evolucao'];

    public const TIPOS = [
        'pedido_medico',
        'laudo_medico',
        'plano_individualizado',
        'relatorio_evolucao',
    ];

    protected $table = 'solicitacao_documentos';

    protected $fillable = [
        'tenant_id',
        'solicitacao_id',
        'solicitacao_item_id',
        'tipo',
        'nome_original',
        'mime',
        'path',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class);
    }

    public function item()
    {
        return $this->belongsTo(SolicitacaoItem::class, 'solicitacao_item_id');
    }
}

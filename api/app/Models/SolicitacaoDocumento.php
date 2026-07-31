<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoDocumento extends Model
{
    use BelongsToTenant, HasFactory;

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

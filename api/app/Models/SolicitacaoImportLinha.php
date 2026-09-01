<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoImportLinha extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'solicitacao_import_linhas';

    protected $fillable = [
        'tenant_id',
        'solicitacao_import_lote_id',
        'linha',
        'grupo',
        'status',
        'matched_solicitacao_id',
        'dados_json',
        'erros_json',
    ];

    protected $casts = [
        'linha' => 'integer',
        'dados_json' => 'array',
        'erros_json' => 'array',
    ];

    public function lote()
    {
        return $this->belongsTo(SolicitacaoImportLote::class, 'solicitacao_import_lote_id');
    }

    public function matchedSolicitacao()
    {
        return $this->belongsTo(Solicitacao::class, 'matched_solicitacao_id');
    }
}

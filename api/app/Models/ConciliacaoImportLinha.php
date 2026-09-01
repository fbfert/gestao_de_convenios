<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConciliacaoImportLinha extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'conciliacao_import_linhas';

    protected $fillable = [
        'tenant_id',
        'conciliacao_import_lote_id',
        'linha',
        'status',
        'matched_conciliacao_id',
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
        return $this->belongsTo(ConciliacaoImportLote::class, 'conciliacao_import_lote_id');
    }

    public function matchedConciliacao()
    {
        return $this->belongsTo(ConciliacaoFinanceira::class, 'matched_conciliacao_id');
    }
}

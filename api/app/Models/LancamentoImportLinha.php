<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LancamentoImportLinha extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'lancamento_import_linhas';

    protected $fillable = [
        'tenant_id',
        'lancamento_import_lote_id',
        'linha',
        'status',
        'matched_lancamento_id',
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
        return $this->belongsTo(LancamentoImportLote::class, 'lancamento_import_lote_id');
    }

    public function matchedLancamento()
    {
        return $this->belongsTo(Lancamento::class, 'matched_lancamento_id');
    }
}

<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuiaImportLinha extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'guia_import_linhas';

    protected $fillable = [
        'tenant_id',
        'guia_import_lote_id',
        'linha',
        'status',
        'matched_guia_id',
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
        return $this->belongsTo(GuiaImportLote::class, 'guia_import_lote_id');
    }

    public function matchedGuia()
    {
        return $this->belongsTo(Guia::class, 'matched_guia_id');
    }
}

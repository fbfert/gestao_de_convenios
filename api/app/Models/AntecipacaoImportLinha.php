<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AntecipacaoImportLinha extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'antecipacao_import_linhas';

    protected $fillable = [
        'tenant_id',
        'antecipacao_import_lote_id',
        'linha',
        'status',
        'matched_antecipacao_id',
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
        return $this->belongsTo(AntecipacaoImportLote::class, 'antecipacao_import_lote_id');
    }

    public function matchedAntecipacao()
    {
        return $this->belongsTo(Antecipacao::class, 'matched_antecipacao_id');
    }
}

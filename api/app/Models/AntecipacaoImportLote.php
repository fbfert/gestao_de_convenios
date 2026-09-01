<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AntecipacaoImportLote extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'antecipacao_import_lotes';

    protected $fillable = [
        'tenant_id',
        'arquivo_nome_original',
        'arquivo_path',
        'status',
        'confirmado_em',
        'total_linhas',
        'total_validas',
        'total_invalidas',
        'total_importados',
        'total_atualizados',
        'total_ignorados',
    ];

    protected $casts = [
        'confirmado_em' => 'datetime',
        'total_linhas' => 'integer',
        'total_validas' => 'integer',
        'total_invalidas' => 'integer',
        'total_importados' => 'integer',
        'total_atualizados' => 'integer',
        'total_ignorados' => 'integer',
    ];

    public function linhas()
    {
        return $this->hasMany(AntecipacaoImportLinha::class, 'antecipacao_import_lote_id');
    }
}

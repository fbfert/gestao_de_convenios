<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnaliticoUnimedLote extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'analitico_unimed_lotes';

    protected $fillable = [
        'tenant_id',
        'arquivo_nome_original',
        'arquivo_path',
        'status',
        'importado_em',
        'total_linhas_analitico',
        'total_linhas_glosa',
        'total_linhas_conciliacao',
        'total_pago',
        'total_glosado',
        'saldo_total',
        'cabecalho_json',
        'planilhas_json',
        'totais_json',
    ];

    protected $casts = [
        'importado_em' => 'datetime',
        'total_linhas_analitico' => 'integer',
        'total_linhas_glosa' => 'integer',
        'total_linhas_conciliacao' => 'integer',
        'total_pago' => 'decimal:2',
        'total_glosado' => 'decimal:2',
        'saldo_total' => 'decimal:2',
        'cabecalho_json' => 'array',
        'planilhas_json' => 'array',
        'totais_json' => 'array',
    ];

    public function linhas()
    {
        return $this->hasMany(AnaliticoUnimedLinha::class, 'analitico_unimed_lote_id');
    }
}

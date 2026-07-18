<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnaliticoUnimedLinha extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'analitico_unimed_linhas';

    protected $fillable = [
        'tenant_id',
        'analitico_unimed_lote_id',
        'linha',
        'origem',
        'natureza',
        'processavel',
        'numero_guia_operadora',
        'numero_guia_prestador',
        'codigo',
        'usuario',
        'data_autorizacao',
        'data_realizacao',
        'procedimento',
        'descricao_procedimento',
        'qtd',
        'qtd_normalizada',
        'tipo',
        'motivo',
        'valor',
        'valor_normalizado',
        'local_realizacao',
        'chave_conciliacao',
        'dados_json',
    ];

    protected $casts = [
        'linha' => 'integer',
        'processavel' => 'boolean',
        'qtd_normalizada' => 'integer',
        'valor_normalizado' => 'decimal:2',
        'dados_json' => 'array',
    ];

    public function lote()
    {
        return $this->belongsTo(AnaliticoUnimedLote::class, 'analitico_unimed_lote_id');
    }
}

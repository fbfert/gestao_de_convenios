<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimentoFinanceiro extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'movimentos_financeiros';

    protected $fillable = [
        'tenant_id',
        'conciliacao_financeira_id',
        'guia_id',
        'profissional_informado_id',
        'profissional_executor_id',
        'tipo',
        'origem',
        'quantidade',
        'valor_unitario',
        'valor_total',
        'referencia_analitico_convenio',
        'descricao',
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'valor_unitario' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    public function conciliacaoFinanceira()
    {
        return $this->belongsTo(ConciliacaoFinanceira::class);
    }

    public function guia()
    {
        return $this->belongsTo(Guia::class);
    }

    public function profissionalInformado()
    {
        return $this->belongsTo(Profissional::class, 'profissional_informado_id');
    }

    public function profissionalExecutor()
    {
        return $this->belongsTo(Profissional::class, 'profissional_executor_id');
    }
}

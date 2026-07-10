<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConciliacaoFinanceira extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'conciliacoes_financeiras';

    protected $fillable = [
        'tenant_id', 'guia_id', 'profissional_id', 'quantidade',
        'valor_unitario', 'valor_total', 'referencia_analitico_convenio',
        'status', 'conferido_em',
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'valor_unitario' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'conferido_em' => 'datetime',
    ];

    public function guia()
    {
        return $this->belongsTo(Guia::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }
}

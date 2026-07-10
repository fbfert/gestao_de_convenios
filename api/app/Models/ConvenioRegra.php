<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConvenioRegra extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'convenio_regras';

    protected $fillable = [
        'tenant_id', 'convenio_id', 'tipo_terapia', 'frequencia_lancamento',
        'qtd_autorizada_por_ciclo', 'validade_senha_dias', 'observacoes',
        'vigente_desde', 'vigente_ate',
    ];

    protected $casts = [
        'vigente_desde' => 'date',
        'vigente_ate' => 'date',
        'qtd_autorizada_por_ciclo' => 'integer',
        'validade_senha_dias' => 'integer',
    ];

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }
}

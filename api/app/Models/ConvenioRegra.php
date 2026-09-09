<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConvenioRegra extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'convenio_regras';

    protected $fillable = [
        'tenant_id', 'convenio_id', 'tipo_terapia', 'frequencia_lancamento',
        'qtd_autorizada_por_ciclo', 'sessoes_por_guia', 'validade_senha_dias', 'observacoes',
        'vigente_desde', 'vigente_ate',
    ];

    protected $casts = [
        'vigente_desde' => 'date',
        'vigente_ate' => 'date',
        // Taxa de lançamento ("1 por dia"), emparelhada com
        // `frequencia_lancamento` — NÃO é o total que a operadora autoriza.
        'qtd_autorizada_por_ciclo' => 'integer',
        // Total autorizado por guia. Nulo = não sabemos, e não zero.
        'sessoes_por_guia' => 'integer',
        'validade_senha_dias' => 'integer',
    ];

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }
}

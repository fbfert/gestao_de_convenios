<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Antecipacao extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'antecipacoes';

    protected $fillable = [
        'tenant_id', 'guia_id', 'paciente_id', 'convenio_id',
        'ciclo_inicio', 'ciclo_fim', 'qtd_autorizada', 'qtd_utilizada', 'status',
    ];

    protected $casts = [
        'ciclo_inicio' => 'date',
        'ciclo_fim' => 'date',
        'qtd_autorizada' => 'integer',
        'qtd_utilizada' => 'integer',
    ];

    public function guia()
    {
        return $this->belongsTo(Guia::class);
    }

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function lancamentos()
    {
        return $this->hasMany(Lancamento::class);
    }
}

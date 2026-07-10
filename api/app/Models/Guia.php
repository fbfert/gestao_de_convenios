<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guia extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'solicitacao_id', 'convenio_id', 'paciente_id',
        'profissional_id', 'especialidade_id', 'numero_guia', 'tipo_terapia',
        'status', 'data_solicitacao', 'data_finalizacao', 'senha',
        'validade_senha', 'observacoes',
    ];

    protected $casts = [
        'data_solicitacao' => 'date',
        'data_finalizacao' => 'date',
        'validade_senha' => 'date',
    ];

    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class);
    }

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }

    public function especialidade()
    {
        return $this->belongsTo(Especialidade::class);
    }

    public function antecipacoes()
    {
        return $this->hasMany(Antecipacao::class);
    }

    public function conciliacoes()
    {
        return $this->hasMany(ConciliacaoFinanceira::class);
    }
}

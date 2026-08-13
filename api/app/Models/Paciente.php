<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paciente extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'nome', 'cpf', 'carteirinha', 'convenio_id',
        'telefone', 'clinica_agil_id', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function solicitacoes()
    {
        return $this->hasMany(Solicitacao::class);
    }
}

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
        'tenant_id', 'nome', 'cpf', 'data_nascimento', 'carteirinha', 'validade_carteirinha',
        'convenio_id', 'telefone', 'clinica_agil_id', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'data_nascimento' => 'date',
        'validade_carteirinha' => 'date',
    ];

    public function carteirinhaVencida(): bool
    {
        return $this->validade_carteirinha !== null && $this->validade_carteirinha->isPast();
    }

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function telefones()
    {
        return $this->hasMany(PacienteTelefone::class)->orderBy('ordem')->orderBy('id');
    }

    public function documentos()
    {
        return $this->hasMany(PacienteDocumento::class);
    }

    public function solicitacoes()
    {
        return $this->hasMany(Solicitacao::class);
    }
}

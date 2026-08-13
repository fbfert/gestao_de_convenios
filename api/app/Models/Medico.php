<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medico extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'medicos';

    protected $fillable = [
        'tenant_id',
        'nome',
        'crm',
        'especialidade_medica',
        'telefone',
        'email',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function solicitacoes()
    {
        return $this->hasMany(Solicitacao::class);
    }
}

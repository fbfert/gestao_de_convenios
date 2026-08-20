<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Credencial da integração com o clinica.gestaonossa.com.br — ver App\Services\ClinicaSync. */
class ClinicaConexaoConfig extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'clinica_conexao_configs';

    /** Nunca entra na auditoria: fica registrado que mudou, nunca o valor. */
    protected array $auditOcultos = ['token'];

    protected $fillable = [
        'tenant_id',
        'base_url',
        'token',
        'ativo',
        'ultima_execucao_em',
        'ultima_execucao_status',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'ativo' => 'boolean',
        'ultima_execucao_em' => 'datetime',
    ];
}

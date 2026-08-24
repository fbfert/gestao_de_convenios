<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UnimedRdaCredential extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'unimed_rda_credentials';

    /** Nunca entra na auditoria: fica registrado que mudou, nunca o valor. */
    protected array $auditOcultos = ['password'];

    protected $fillable = [
        'tenant_id',
        'login',
        'password',
        'base_url',
        'nome_contratado',
        'ativo',
        'automation_paused_at',
        'automation_paused_reason',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'ativo' => 'boolean',
        'automation_paused_at' => 'datetime',
    ];
}

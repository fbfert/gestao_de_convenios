<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EmailSmtpSetting extends Model
{
    use Auditable, BelongsToTenant;

    /** Nunca entra na auditoria: fica registrado que mudou, nunca o valor. */
    protected array $auditOcultos = ['password'];

    protected $fillable = [
        'tenant_id',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_email',
        'from_name',
        'ativo',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'ativo' => 'boolean',
        'port' => 'integer',
    ];
}

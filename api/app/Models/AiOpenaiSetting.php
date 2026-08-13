<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiOpenaiSetting extends Model
{
    use Auditable, BelongsToTenant;

    /** Nunca entra na auditoria: fica registrado que mudou, nunca o valor. */
    protected array $auditOcultos = ['api_key'];

    protected $fillable = [
        'tenant_id',
        'api_key',
        'base_url',
        'organization_id',
        'project_id',
        'model_id',
        'ativo',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'ativo' => 'boolean',
    ];
}

<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiOpenaiSetting extends Model
{
    use BelongsToTenant;

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

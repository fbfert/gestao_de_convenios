<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UnimedRdaCredential extends Model
{
    use BelongsToTenant;

    protected $table = 'unimed_rda_credentials';

    protected $fillable = [
        'tenant_id',
        'login',
        'password',
        'base_url',
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

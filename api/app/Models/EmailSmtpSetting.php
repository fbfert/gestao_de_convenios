<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EmailSmtpSetting extends Model
{
    use BelongsToTenant;

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

<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LancamentoPrintTemplate extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'chave',
        'nome',
        'html',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}

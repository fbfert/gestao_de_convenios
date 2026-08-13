<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'chave',
        'nome',
        'assunto',
        'corpo',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}

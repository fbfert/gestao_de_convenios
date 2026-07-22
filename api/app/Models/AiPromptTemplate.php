<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiPromptTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'chave',
        'nome',
        'descricao',
        'model_id',
        'system_prompt',
        'user_prompt',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];
}

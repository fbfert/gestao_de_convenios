<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomacaoEvento extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'automacao_eventos';

    protected $fillable = [
        'tenant_id',
        'automacao_execucao_id',
        'tipo',
        'status',
        'payload',
        'evidencias',
        'registrado_em',
    ];

    protected $casts = [
        'payload' => 'array',
        'evidencias' => 'array',
        'registrado_em' => 'datetime',
    ];

    public function execucao()
    {
        return $this->belongsTo(AutomacaoExecucao::class, 'automacao_execucao_id');
    }
}

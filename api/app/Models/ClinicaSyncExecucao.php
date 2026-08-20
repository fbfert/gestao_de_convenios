<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ClinicaSyncExecucao extends Model
{
    use BelongsToTenant;

    protected $table = 'clinica_sync_execucoes';

    protected $fillable = [
        'tenant_id',
        'origem',
        'status',
        'iniciado_em',
        'finalizado_em',
        'resumo',
        'erro_mensagem',
    ];

    protected $casts = [
        'iniciado_em' => 'datetime',
        'finalizado_em' => 'datetime',
        'resumo' => 'array',
    ];
}

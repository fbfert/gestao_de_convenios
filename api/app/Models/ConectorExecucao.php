<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConectorExecucao extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'conector_execucoes';

    protected $fillable = [
        'tenant_id', 'convenio_id', 'executado_em', 'status', 'detalhes',
    ];

    protected $casts = [
        'executado_em' => 'datetime',
        'detalhes' => 'array',
    ];

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }
}

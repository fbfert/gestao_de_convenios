<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TabelaValor extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'tabela_valores';

    protected $fillable = [
        'tenant_id', 'convenio_id', 'especialidade_id', 'profissional_id',
        'valor', 'vigente_desde', 'vigente_ate',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'vigente_desde' => 'date',
        'vigente_ate' => 'date',
    ];

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function especialidade()
    {
        return $this->belongsTo(Especialidade::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }
}

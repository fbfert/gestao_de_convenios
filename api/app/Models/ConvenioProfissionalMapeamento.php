<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConvenioProfissionalMapeamento extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'convenio_profissional_mapeamentos';

    protected $fillable = [
        'tenant_id',
        'convenio_id',
        'profissional_id',
        'codigo_operadora',
        'nome_operadora',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }
}

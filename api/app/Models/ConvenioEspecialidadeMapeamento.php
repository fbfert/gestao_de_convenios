<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConvenioEspecialidadeMapeamento extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'convenio_especialidade_mapeamentos';

    protected $fillable = [
        'tenant_id',
        'convenio_id',
        'especialidade_id',
        'codigo_procedimento',
        'descricao_operadora',
        'quantidade_padrao',
        'usa_descricao_generica',
        'valor_generico',
        'ativo',
    ];

    protected $casts = [
        'quantidade_padrao' => 'integer',
        'usa_descricao_generica' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function especialidade()
    {
        return $this->belongsTo(Especialidade::class);
    }
}

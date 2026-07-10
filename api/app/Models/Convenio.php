<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Convenio extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'nome', 'connector_type', 'connector_config', 'ativo'];

    protected $casts = [
        'connector_config' => 'array',
        'ativo' => 'boolean',
    ];

    public function regras()
    {
        return $this->hasMany(ConvenioRegra::class);
    }

    public function tabelaValores()
    {
        return $this->hasMany(TabelaValor::class);
    }
}

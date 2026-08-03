<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Especialidade extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'nome', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function profissionais()
    {
        return $this->hasMany(Profissional::class);
    }

    public function convenioMapeamentos()
    {
        return $this->hasMany(ConvenioEspecialidadeMapeamento::class);
    }
}

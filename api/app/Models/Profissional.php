<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profissional extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'profissionais';

    protected $fillable = [
        'tenant_id',
        'especialidade_id',
        'nome',
        'conselho_registro',
        'ativo',
        'percentual_repasse',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'percentual_repasse' => 'decimal:2',
    ];

    public function especialidade()
    {
        return $this->belongsTo(Especialidade::class);
    }

    public function convenioMapeamentos()
    {
        return $this->hasMany(ConvenioProfissionalMapeamento::class);
    }

    public function lancamentos()
    {
        return $this->hasMany(Lancamento::class);
    }
}

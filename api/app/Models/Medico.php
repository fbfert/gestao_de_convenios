<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use App\Support\NomeMedicoNormalizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medico extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'medicos';

    protected $fillable = [
        'tenant_id',
        'nome',
        'crm',
        'crm_uf',
        'especialidade_medica',
        'telefone',
        'email',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function solicitacoes()
    {
        return $this->hasMany(Solicitacao::class);
    }

    /**
     * O portal da Unimed cadastra o cooperado sem "Dr./Dra." — mantido no
     * nosso cadastro, esse prefixo só atrapalha a busca por nome durante a
     * automação. Removido aqui pra valer em qualquer via de gravação
     * (cadastro manual, importação, correção pós-automação).
     */
    protected function nome(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => NomeMedicoNormalizer::semPrefixo($value),
        );
    }
}

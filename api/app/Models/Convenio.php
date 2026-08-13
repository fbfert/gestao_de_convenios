<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Convenio extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'nome',
        'descricao',
        'connector_type',
        'connector_driver',
        'connector_config',
        'carteirinha_blocos',
        'ativo',
    ];

    protected $casts = [
        'connector_config' => 'array',
        'carteirinha_blocos' => 'array',
        'ativo' => 'boolean',
    ];

    /**
     * Tamanhos dos blocos da carteirinha, ou null quando o convênio aceita
     * texto livre. Ver a migration 2026_08_12_200000.
     *
     * @return int[]|null
     */
    public function blocosCarteirinha(): ?array
    {
        $blocos = $this->carteirinha_blocos;

        if (! is_array($blocos) || $blocos === []) {
            return null;
        }

        return array_map('intval', array_values($blocos));
    }

    /** Total de dígitos exigido, ou null se o convênio não define formato. */
    public function tamanhoCarteirinha(): ?int
    {
        $blocos = $this->blocosCarteirinha();

        return $blocos === null ? null : array_sum($blocos);
    }

    public function regras()
    {
        return $this->hasMany(ConvenioRegra::class);
    }

    public function tabelaValores()
    {
        return $this->hasMany(TabelaValor::class);
    }
}

<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profissional extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

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

    /**
     * Garante a invariante: a especialidade principal está sempre entre as
     * que o profissional atende.
     *
     * Fica num evento do model, e não só no controller, porque profissional
     * também nasce de seeder, de factory e de qualquer código futuro — todos
     * esses caminhos deixariam a ligação vazia, e o profissional sumiria dos
     * filtros por especialidade sem erro nenhum.
     *
     * `syncWithoutDetaching` é idempotente e não remove as adicionais que o
     * operador tenha escolhido.
     */
    protected static function booted(): void
    {
        static::saved(function (self $profissional) {
            if ($profissional->especialidade_id) {
                $profissional->especialidades()->syncWithoutDetaching([$profissional->especialidade_id]);
            }
        });
    }

    /** Especialidade principal — o registro de conselho da pessoa. */
    public function especialidade()
    {
        return $this->belongsTo(Especialidade::class);
    }

    /**
     * Todas em que o profissional atende, principal incluída.
     * Ver a migration 2026_08_12_210000.
     */
    public function especialidades()
    {
        return $this->belongsToMany(Especialidade::class, 'especialidade_profissional')
            ->withTimestamps();
    }

    /**
     * Grava em quais especialidades o profissional atua.
     *
     * A principal entra sempre, mesmo que não venha na lista: ela é a
     * referência de vários fluxos que ainda leem a coluna, e um profissional
     * que não atendesse na própria especialidade principal seria um estado
     * incoerente, difícil de perceber e pior de depurar.
     *
     * @param  int[]  $especialidadeIds
     */
    public function sincronizarEspecialidades(array $especialidadeIds): void
    {
        $ids = collect($especialidadeIds)
            ->map(fn ($id) => (int) $id)
            ->push((int) $this->especialidade_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->especialidades()->sync($ids);
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

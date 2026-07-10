<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $nome = fake()->company();
        $slug = Str::slug($nome).'-'.fake()->unique()->numberBetween(1000, 9999);

        return [
            'nome' => $nome,
            'slug' => $slug,
            'cnpj' => fake()->numerify('##.###.###/####-##'),
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => [
            'ativo' => false,
        ]);
    }
}

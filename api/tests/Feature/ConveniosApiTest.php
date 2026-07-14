<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConveniosApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_atualiza_descricao_do_convenio(): void
    {
        $this->autenticar();

        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->patchJson("/api/convenios/{$convenio->id}", [
            'nome' => 'Unimed',
            'descricao' => 'Descrição atualizada pelo teste',
            'connector_type' => 'manual',
            'ativo' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $convenio->id)
            ->assertJsonPath('data.descricao', 'Descrição atualizada pelo teste');

        $this->assertDatabaseHas('convenios', [
            'id' => $convenio->id,
            'descricao' => 'Descrição atualizada pelo teste',
        ]);
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }
}

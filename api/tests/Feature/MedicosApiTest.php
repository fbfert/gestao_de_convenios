<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicosApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lista_medicos_referencia_da_clinica_exemplo(): void
    {
        $this->autenticar();

        $this->getJson('/api/medicos')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.nome', 'Dr. Carlos Almeida');
    }

    public function test_lista_medicos_ignora_tenant_externo(): void
    {
        $this->criarMedicoExterno();
        $this->autenticar();

        $this->getJson('/api/medicos')
            ->assertOk()
            ->assertJsonMissing(['nome' => 'Dr. Externo']);
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function criarMedicoExterno(): void
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa',
            'slug' => 'clinica-externa',
            'cnpj' => '98.765.432/0001-10',
            'ativo' => true,
        ]);

        Medico::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Dr. Externo',
            'crm' => 'CRM 999999',
            'especialidade_medica' => 'Clínica Geral',
            'telefone' => '(11) 90000-0000',
            'email' => 'externo@clinica-externa.test',
            'ativo' => true,
        ]);
    }
}

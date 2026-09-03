<?php

namespace Tests\Feature;

use App\Models\Medico;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
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

    public function test_cria_medico_para_o_tenant_autenticado(): void
    {
        $this->autenticar();

        $this->postJson('/api/medicos', [
            'nome' => 'Dra. Laura Martins',
            'crm' => '777888',
            'crm_uf' => 'SC',
            'especialidade_medica' => 'Dermatologia',
            'telefone' => '(11) 95555-0199',
            'email' => 'laura.martins@clinica-exemplo.test',
            'ativo' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Dra. Laura Martins')
            ->assertJsonPath('data.crm', '777888')
            ->assertJsonPath('data.crm_uf', 'SC')
            ->assertJsonPath('data.ativo', true);

        $this->assertDatabaseHas('medicos', [
            'nome' => 'Dra. Laura Martins',
            'crm' => '777888',
            'crm_uf' => 'SC',
        ]);
    }

    public function test_crm_com_letras_e_rejeitado(): void
    {
        $this->autenticar();

        $this->postJson('/api/medicos', [
            'nome' => 'Dra. Laura Martins',
            'crm' => 'CRM-SC 777888',
            'crm_uf' => 'SC',
            'especialidade_medica' => 'Dermatologia',
            'telefone' => '(11) 95555-0199',
            'ativo' => true,
        ])->assertJsonValidationErrors(['crm']);
    }

    public function test_atualiza_medico_e_pode_desativar(): void
    {
        $this->autenticar();

        $medico = Medico::query()
            ->where('nome', 'Dr. Carlos Almeida')
            ->firstOrFail();

        $this->patchJson("/api/medicos/{$medico->id}", [
            'telefone' => '(11) 97777-0000',
            'ativo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $medico->id)
            ->assertJsonPath('data.telefone', '(11) 97777-0000')
            ->assertJsonPath('data.ativo', false);

        $this->assertDatabaseHas('medicos', [
            'id' => $medico->id,
            'telefone' => '(11) 97777-0000',
            'ativo' => false,
        ]);
    }

    public function test_lista_medicos_ignora_tenant_externo(): void
    {
        $this->criarMedicoExterno();
        $this->autenticar();

        $this->getJson('/api/medicos')
            ->assertOk()
            ->assertJsonMissing(['nome' => 'Dr. Externo']);
    }

    public function test_medico_de_outro_tenant_retorna_404_no_binding(): void
    {
        $medico = Medico::query()->where('nome', 'Dr. Carlos Almeida')->firstOrFail();
        $user = $this->criarUsuarioAutorizadoDeOutroTenant();
        Sanctum::actingAs($user);

        $this->patchJson("/api/medicos/{$medico->id}", [
            'ativo' => false,
        ])->assertNotFound();
    }

    public function test_usuario_sem_permissao_nao_pode_gerenciar_medicos(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/medicos', [
            'nome' => 'Dr. Bloqueado',
            'crm' => '555666',
            'crm_uf' => 'SC',
            'especialidade_medica' => 'Cardiologia',
            'telefone' => '(11) 96666-0000',
        ])->assertForbidden();
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

    private function criarUsuarioAutorizadoDeOutroTenant(): User
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa',
            'slug' => 'clinica-externa',
            'cnpj' => '98.765.432/0001-10',
            'ativo' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Externo',
            'email' => 'admin@clinica-externa.test',
            'password' => 'password',
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->givePermissionTo('medicos.manage');

        return $user;
    }
}

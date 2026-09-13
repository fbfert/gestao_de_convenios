<?php

namespace Tests\Feature;

use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuariosApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lista_usuarios_da_clinica_exemplo(): void
    {
        $this->autenticar();

        // 3 contas de exemplo + o administrador inicial do sistema
        // (ver database/migrations/2026_08_07_100000_create_admin_inicial_fbfert).
        $this->getJson('/api/usuarios')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name', 'Admin Clínica Exemplo');
    }

    public function test_cria_usuario_profissional_com_vinculo_no_mesmo_tenant(): void
    {
        $this->autenticar();

        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();

        $this->postJson('/api/usuarios', [
            'name' => 'Usuário Profissional Novo',
            'email' => 'usuario.profissional.novo@clinica-exemplo.test',
            'password' => 'password',
            'role' => 'profissional',
            'profissional_id' => $profissional->id,
            'ativo' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Usuário Profissional Novo')
            ->assertJsonPath('data.role', 'profissional')
            ->assertJsonPath('data.profissional_id', $profissional->id);

        $this->assertDatabaseHas('users', [
            'email' => 'usuario.profissional.novo@clinica-exemplo.test',
            'profissional_id' => $profissional->id,
        ]);
    }

    public function test_atualiza_usuario_trocando_role_e_desativando(): void
    {
        $this->autenticar();

        $usuario = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();

        $this->patchJson("/api/usuarios/{$usuario->id}", [
            'name' => 'Profissional Reclassificado',
            'role' => 'funcionario',
            'ativo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Profissional Reclassificado')
            ->assertJsonPath('data.role', 'funcionario')
            ->assertJsonPath('data.profissional_id', null)
            ->assertJsonPath('data.ativo', false);

        $this->assertDatabaseHas('users', [
            'id' => $usuario->id,
            'name' => 'Profissional Reclassificado',
            'ativo' => false,
            'profissional_id' => null,
        ]);
    }

    public function test_valida_profissional_obrigatorio_quando_role_e_profissional(): void
    {
        $this->autenticar();

        $this->postJson('/api/usuarios', [
            'name' => 'Usuário inválido',
            'email' => 'usuario.invalido@clinica-exemplo.test',
            'password' => 'password',
            'role' => 'profissional',
            'ativo' => true,
        ])->assertStatus(422);
    }

    public function test_usuario_de_outro_tenant_retorna_404_no_binding(): void
    {
        $this->autenticar();

        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa',
            'slug' => 'clinica-externa-usuarios',
            'cnpj' => '55.555.555/0001-55',
            'ativo' => true,
        ]);

        $usuarioExterno = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Usuário Externo',
            'email' => 'externo@clinica-externa.test',
            'password' => 'password',
            'ativo' => true,
        ]);

        $this->patchJson("/api/usuarios/{$usuarioExterno->id}", [
            'ativo' => false,
        ])->assertNotFound();

        $this->getJson("/api/usuarios/{$usuarioExterno->id}")->assertNotFound();
    }

    /**
     * A tela de edição hidratava o formulário procurando o usuário na página já
     * carregada da listagem — quem não estivesse nela abria o formulário em
     * branco, no modo "Novo usuário", e salvar criava um usuário novo em vez de
     * editar o pretendido. O endpoint existe para a hidratação não depender da
     * página aberta.
     */
    public function test_busca_um_usuario_pelo_id_independente_da_pagina_da_listagem(): void
    {
        $this->autenticar();

        $usuario = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();

        $this->getJson("/api/usuarios/{$usuario->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $usuario->id)
            ->assertJsonPath('data.email', $usuario->email);
    }

    public function test_usuario_sem_permissao_nao_acessa_crud_de_usuarios(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/usuarios')->assertForbidden();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }
}

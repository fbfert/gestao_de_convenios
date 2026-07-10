<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_login_com_sucesso_retorna_token_e_dados_do_usuario(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@clinica-exemplo.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'admin@clinica-exemplo.test')
            ->assertJsonPath('user.name', 'Admin Clínica Exemplo')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.tenant.slug', 'clinica-exemplo')
            ->assertJsonStructure([
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'tenant' => [
                        'id',
                        'nome',
                        'slug',
                    ],
                ],
            ]);

        $token = $response->json('token');

        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'admin@clinica-exemplo.test');
    }

    public function test_login_com_senha_errada_retorna_401_sem_token(): void
    {
        $this->postJson('/api/login', [
            'email' => 'admin@clinica-exemplo.test',
            'password' => 'senha-errada',
        ])
            ->assertStatus(401)
            ->assertJsonMissingPath('token');
    }

    public function test_login_com_usuario_inativo_retorna_401(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Usuário Inativo',
            'email' => 'inativo@clinica-exemplo.test',
            'password' => 'password',
            'role' => 'operador',
            'ativo' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => 'inativo@clinica-exemplo.test',
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_login_com_tenant_inativo_retorna_401(): void
    {
        $tenant = Tenant::factory()->inativo()->create([
            'nome' => 'Clínica Inativa',
            'slug' => 'clinica-inativa',
            'cnpj' => '22.222.222/0001-22',
        ]);

        User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Inativo',
            'email' => 'admin@clinica-inativa.test',
            'password' => 'password',
            'role' => 'admin',
            'ativo' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin@clinica-inativa.test',
            'password' => 'password',
        ])->assertStatus(401);
    }

    public function test_logout_revoga_apenas_o_token_atual(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => 'admin@clinica-exemplo.test',
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('token');

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_throttle_bloqueia_mais_de_cinco_tentativas_invalidas(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'admin@clinica-exemplo.test',
                'password' => 'senha-errada',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email' => 'admin@clinica-exemplo.test',
            'password' => 'senha-errada',
        ])->assertStatus(429);
    }
}

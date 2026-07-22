<?php

namespace Tests\Feature;

use App\Models\Manual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_qualquer_usuario_logado_pode_ler_o_manual(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/manual')
            ->assertOk()
            ->assertJsonStructure(['data' => ['conteudo_html', 'atualizado_em', 'atualizado_por']]);
    }

    public function test_primeira_leitura_cria_o_manual_com_conteudo_padrao(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->assertDatabaseCount('manuais', 0);

        $response = $this->getJson('/api/manual')->assertOk();

        $this->assertDatabaseCount('manuais', 1);
        $this->assertNotEmpty($response->json('data.conteudo_html'));
    }

    public function test_admin_pode_editar_o_manual(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->putJson('/api/manual', [
            'conteudo_html' => '<html><body>Novo conteudo</body></html>',
        ])
            ->assertOk()
            ->assertJsonPath('data.conteudo_html', '<html><body>Novo conteudo</body></html>')
            ->assertJsonPath('data.atualizado_por', $user->name);

        $this->assertDatabaseHas('manuais', [
            'conteudo_html' => '<html><body>Novo conteudo</body></html>',
            'atualizado_por' => $user->id,
        ]);
    }

    public function test_usuario_sem_permissao_nao_pode_editar_o_manual(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->putJson('/api/manual', [
            'conteudo_html' => '<html><body>Tentativa bloqueada</body></html>',
        ])->assertForbidden();
    }

    public function test_manual_e_isolado_por_tenant(): void
    {
        $tenant = \App\Models\Tenant::query()->create([
            'nome' => 'Clínica Externa Manual',
            'slug' => 'clinica-externa-manual',
            'cnpj' => '20.020.020/0001-20',
            'ativo' => true,
        ]);

        Manual::query()->create([
            'tenant_id' => $tenant->id,
            'conteudo_html' => '<html><body>Manual de outro tenant</body></html>',
        ]);

        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/manual')->assertOk();

        $this->assertNotEquals('<html><body>Manual de outro tenant</body></html>', $response->json('data.conteudo_html'));
    }

    public function test_le_e_edita_o_mapa_mental_separadamente_do_manual(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $mapaMental = $this->getJson('/api/manual/mapa-mental')
            ->assertOk()
            ->assertJsonPath('data.tipo', 'mapa-mental');

        $manual = $this->getJson('/api/manual')->assertOk()->assertJsonPath('data.tipo', 'manual');

        $this->assertNotEquals($manual->json('data.conteudo_html'), $mapaMental->json('data.conteudo_html'));

        $this->putJson('/api/manual/mapa-mental', [
            'conteudo_html' => '<html><body>Novo mapa mental</body></html>',
        ])
            ->assertOk()
            ->assertJsonPath('data.tipo', 'mapa-mental')
            ->assertJsonPath('data.conteudo_html', '<html><body>Novo mapa mental</body></html>');

        $this->getJson('/api/manual')
            ->assertOk()
            ->assertJsonPath('data.conteudo_html', $manual->json('data.conteudo_html'));

        $this->assertDatabaseHas('manuais', [
            'tipo' => 'mapa-mental',
            'conteudo_html' => '<html><body>Novo mapa mental</body></html>',
        ]);
    }

    public function test_tipo_invalido_retorna_404(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/manual/inexistente')->assertNotFound();
    }
}

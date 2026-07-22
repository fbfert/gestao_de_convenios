<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_dashboard_exibe_blocos_para_admin_e_inclui_auditoria(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $response = $this->getJson('/api/dashboard')->assertOk();

        $keys = array_column($response->json('data.blocks'), 'key');

        $this->assertContains('convenios', $keys);
        $this->assertContains('auditoria', $keys);
    }

    public function test_dashboard_oculta_blocos_restritos_para_profissional(): void
    {
        $this->autenticar('profissional@clinica-exemplo.test');

        $response = $this->getJson('/api/dashboard')->assertOk();

        $keys = array_column($response->json('data.blocks'), 'key');

        $this->assertContains('guias', $keys);
        $this->assertNotContains('auditoria', $keys);
        $this->assertNotContains('usuarios', $keys);
    }

    public function test_auditoria_requer_permissao_dashboard(): void
    {
        $this->autenticar('profissional@clinica-exemplo.test');

        $this->getJson('/api/auditoria')->assertForbidden();
    }

    private function autenticar(string $email): void
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);
    }
}

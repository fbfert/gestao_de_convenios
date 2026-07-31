<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Convenio;
use App\Models\Tenant;
use App\Models\UnimedRdaCredential;
use App\Models\User;
use App\Services\Automation\FakeUnimedWorkerClient;
use App\Services\Automation\UnimedWorkerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UnimedSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_salva_credencial_unimed_e_driver_sem_expor_senha(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->putJson('/api/configuracoes/unimed', $this->payload($convenio->id, 'senha-inicial'))
            ->assertOk()
            ->assertJsonPath('data.convenio_id', $convenio->id)
            ->assertJsonPath('data.credential.login', 'operador-unimed')
            ->assertJsonPath('data.credential.senha_configurada', true)
            ->assertJsonMissingPath('data.credential.password');

        $credential = UnimedRdaCredential::query()->firstOrFail();
        $this->assertSame('senha-inicial', $credential->password);

        $this->assertDatabaseHas('convenios', [
            'id' => $convenio->id,
            'connector_type' => 'scraping',
            'connector_driver' => 'unimed_rda',
        ]);

        $audit = AuditLog::query()->where('acao', 'unimed_rda_settings.updated')->firstOrFail();
        $this->assertSame($convenio->id, $audit->payload['convenio_id']);
        $this->assertTrue($audit->payload['senha_atualizada']);
        $this->assertArrayNotHasKey('password', $audit->payload);
    }

    public function test_preserva_senha_existente_quando_update_vem_sem_senha(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->putJson('/api/configuracoes/unimed', $this->payload($convenio->id, 'senha-inicial'))
            ->assertOk();

        $payload = $this->payload($convenio->id, '');
        $payload['credential']['login'] = 'operador-alterado';

        $this->putJson('/api/configuracoes/unimed', $payload)
            ->assertOk()
            ->assertJsonPath('data.credential.login', 'operador-alterado')
            ->assertJsonPath('data.credential.senha_configurada', true);

        $this->assertSame('senha-inicial', UnimedRdaCredential::query()->firstOrFail()->password);
    }

    public function test_isola_configuracao_unimed_por_tenant(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->putJson('/api/configuracoes/unimed', $this->payload($convenio->id, 'senha-inicial'))
            ->assertOk();

        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Unimed Externa',
            'slug' => 'clinica-unimed-externa',
            'cnpj' => '55.555.555/0001-55',
            'ativo' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Unimed Externo',
            'email' => 'admin.unimed.externo@clinica.test',
            'password' => bcrypt('password'),
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $permission = Permission::findOrCreate('configuracoes.manage', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->getJson('/api/configuracoes/unimed')
            ->assertOk()
            ->assertJsonPath('data.credential', null)
            ->assertJsonPath('data.convenio_id', null);
    }

    public function test_rejeita_convenio_de_outro_tenant(): void
    {
        $this->autenticar();
        $tenant = Tenant::query()->create([
            'nome' => 'Tenant Convênio Unimed Externo',
            'slug' => 'tenant-convenio-unimed-externo',
            'cnpj' => '44.444.444/0001-44',
            'ativo' => true,
        ]);
        $convenio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Unimed Externa',
            'connector_type' => 'manual',
            'connector_driver' => null,
            'connector_config' => null,
            'ativo' => true,
        ]);

        $this->putJson('/api/configuracoes/unimed', $this->payload($convenio->id, 'senha'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['convenio_id']);
    }

    public function test_healthcheck_do_worker_retorna_status_administrativo(): void
    {
        $this->autenticar();
        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient());

        $this->getJson('/api/configuracoes/unimed/worker-health')
            ->assertOk()
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.worker.status', 'available');
    }

    public function test_reativa_automacao_pausada_com_auditoria(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->putJson('/api/configuracoes/unimed', $this->payload($convenio->id, 'senha-inicial'))
            ->assertOk();

        $credential = UnimedRdaCredential::query()->firstOrFail();
        $credential->forceFill([
            'ativo' => false,
            'automation_paused_at' => now(),
            'automation_paused_reason' => 'PORTAL_STRUCTURE_CHANGED',
        ])->save();

        $this->postJson('/api/configuracoes/unimed/reativar')
            ->assertOk()
            ->assertJsonPath('data.credential.ativo', true)
            ->assertJsonPath('data.credential.automation_paused_at', null)
            ->assertJsonPath('data.credential.automation_paused_reason', null);

        $this->assertDatabaseHas('audit_logs', [
            'acao' => 'unimed_rda.automation_reactivated',
            'entidade_id' => $credential->id,
        ]);
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function payload(?int $convenioId, string $password): array
    {
        return [
            'convenio_id' => $convenioId,
            'credential' => [
                'login' => 'operador-unimed',
                'password' => $password,
                'base_url' => 'https://portal.unimed.test',
                'ativo' => true,
            ],
        ];
    }
}

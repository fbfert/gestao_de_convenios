<?php

namespace Tests\Feature;

use App\Models\EmailSmtpSetting;
use App\Models\EmailTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmailSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_salva_configuracao_smtp_e_templates_sem_expor_senha(): void
    {
        $this->autenticar();

        $this->putJson('/api/configuracoes/emails', $this->payload('senha-inicial'))
            ->assertOk()
            ->assertJsonPath('data.smtp.host', 'smtp.clinica.test')
            ->assertJsonPath('data.smtp.senha_configurada', true)
            ->assertJsonMissingPath('data.smtp.password')
            ->assertJsonPath('data.templates.0.chave', 'guia_aprovada');

        $smtp = EmailSmtpSetting::query()->firstOrFail();
        $this->assertSame('senha-inicial', $smtp->password);

        $this->assertDatabaseHas('email_templates', [
            'tenant_id' => $smtp->tenant_id,
            'chave' => 'guia_aprovada',
            'assunto' => 'Guia aprovada',
        ]);
    }

    public function test_preserva_senha_existente_quando_update_vem_sem_senha(): void
    {
        $this->autenticar();

        $this->putJson('/api/configuracoes/emails', $this->payload('senha-inicial'))
            ->assertOk();

        $payload = $this->payload('');
        $payload['smtp']['host'] = 'smtp-novo.clinica.test';

        $this->putJson('/api/configuracoes/emails', $payload)
            ->assertOk()
            ->assertJsonPath('data.smtp.host', 'smtp-novo.clinica.test')
            ->assertJsonPath('data.smtp.senha_configurada', true);

        $this->assertSame('senha-inicial', EmailSmtpSetting::query()->firstOrFail()->password);
    }

    public function test_isola_configuracoes_por_tenant(): void
    {
        $this->autenticar();

        $this->putJson('/api/configuracoes/emails', $this->payload('senha-inicial'))
            ->assertOk();

        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Email Externa',
            'slug' => 'clinica-email-externa',
            'cnpj' => '12.345.678/0001-90',
            'ativo' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Externo',
            'email' => 'admin.email.externo@clinica.test',
            'password' => bcrypt('password'),
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $permission = Permission::findOrCreate('configuracoes.manage', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->getJson('/api/configuracoes/emails')
            ->assertOk()
            ->assertJsonPath('data.smtp', null)
            ->assertJsonCount(0, 'data.templates');
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function payload(string $password): array
    {
        return [
            'smtp' => [
                'host' => 'smtp.clinica.test',
                'port' => 587,
                'username' => 'envios@clinica.test',
                'password' => $password,
                'encryption' => 'tls',
                'from_email' => 'envios@clinica.test',
                'from_name' => 'Clínica Exemplo',
                'ativo' => true,
            ],
            'templates' => [
                [
                    'chave' => 'guia_aprovada',
                    'nome' => 'Guia aprovada',
                    'assunto' => 'Guia aprovada',
                    'corpo' => 'Olá {{paciente_nome}}, sua guia foi aprovada.',
                    'ativo' => true,
                ],
            ],
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Models\AiOpenaiSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_salva_configuracao_openai_e_prompts_sem_expor_api_key(): void
    {
        $this->autenticar();

        $this->putJson('/api/configuracoes/ia', $this->payload('sk-teste-inicial'))
            ->assertOk()
            ->assertJsonPath('data.openai.base_url', 'https://api.openai.com/v1')
            ->assertJsonPath('data.openai.api_key_configurada', true)
            ->assertJsonMissingPath('data.openai.api_key')
            ->assertJsonPath('data.prompts.0.chave', 'ler_sessoes_escaneadas')
            ->assertJsonPath('data.prompts.1.chave', 'ler_solicitacao_medica');

        $setting = AiOpenaiSetting::query()->firstOrFail();
        $this->assertSame('sk-teste-inicial', $setting->api_key);
    }

    public function test_preserva_api_key_existente_quando_update_vem_sem_chave(): void
    {
        $this->autenticar();

        $this->putJson('/api/configuracoes/ia', $this->payload('sk-teste-inicial'))
            ->assertOk();

        $payload = $this->payload('');
        $payload['openai']['project_id'] = 'proj_123';

        $this->putJson('/api/configuracoes/ia', $payload)
            ->assertOk()
            ->assertJsonPath('data.openai.project_id', 'proj_123')
            ->assertJsonPath('data.openai.api_key_configurada', true);

        $this->assertSame('sk-teste-inicial', AiOpenaiSetting::query()->firstOrFail()->api_key);
    }

    public function test_lista_modelos_openai_no_backend(): void
    {
        $this->autenticar();

        $this->putJson('/api/configuracoes/ia', $this->payload('sk-teste-inicial'))
            ->assertOk();

        Http::fake([
            'api.openai.com/v1/models' => Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'gpt-5.2', 'owned_by' => 'openai'],
                    ['id' => 'gpt-4.1-mini', 'owned_by' => 'openai'],
                ],
            ]),
        ]);

        $this->getJson('/api/configuracoes/ia/modelos')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'gpt-4.1-mini')
            ->assertJsonPath('data.1.id', 'gpt-5.2');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-teste-inicial'));
    }

    public function test_isola_configuracoes_de_ia_por_tenant(): void
    {
        $this->autenticar();

        $this->putJson('/api/configuracoes/ia', $this->payload('sk-teste-inicial'))
            ->assertOk();

        $tenant = Tenant::query()->create([
            'nome' => 'Clínica IA Externa',
            'slug' => 'clinica-ia-externa',
            'cnpj' => '98.765.432/0001-90',
            'ativo' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin IA Externo',
            'email' => 'admin.ia.externo@clinica.test',
            'password' => bcrypt('password'),
            'ativo' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $permission = Permission::findOrCreate('configuracoes.manage', 'web');
        $role = Role::findOrCreate('admin', 'web');
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->getJson('/api/configuracoes/ia')
            ->assertOk()
            ->assertJsonPath('data.openai', null)
            ->assertJsonCount(2, 'data.prompts');
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function payload(string $apiKey): array
    {
        return [
            'openai' => [
                'api_key' => $apiKey,
                'base_url' => 'https://api.openai.com/v1',
                'organization_id' => 'org_123',
                'project_id' => null,
                'ativo' => true,
            ],
            'prompts' => [
                [
                    'chave' => 'ler_solicitacao_medica',
                    'nome' => 'Ler solicitação médica',
                    'descricao' => 'Extrai dados de solicitações médicas.',
                    'model_id' => 'gpt-5.2',
                    'system_prompt' => 'Responda em JSON.',
                    'user_prompt' => 'Leia a solicitação médica.',
                    'ativo' => true,
                ],
                [
                    'chave' => 'ler_sessoes_escaneadas',
                    'nome' => 'Ler sessões escaneadas',
                    'descricao' => 'Extrai sessões escaneadas.',
                    'model_id' => 'gpt-5.2',
                    'system_prompt' => 'Responda em JSON.',
                    'user_prompt' => 'Leia as sessões escaneadas.',
                    'ativo' => true,
                ],
            ],
        ];
    }
}

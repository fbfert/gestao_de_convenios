<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Convenio;
use App\Models\ConvenioCredencial;
use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConvenioDriverCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConvenioCredenciaisApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const ROTA = '/api/configuracoes/convenios-credenciais';

    /** Segundo critério de aceite: duas credenciais no mesmo tenant, sem uma sobrescrever a outra. */
    public function test_tenant_tem_credenciais_de_dois_convenios_ao_mesmo_tempo(): void
    {
        $this->autenticar();

        $unimed = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $outro = Convenio::query()->where('nome', 'SC Saúde')->firstOrFail();

        $this->putJson(self::ROTA."/{$unimed->id}", $this->payloadUnimed('login-unimed', 'senha-unimed'))
            ->assertOk();
        $this->putJson(self::ROTA."/{$outro->id}", $this->payloadUnimed('login-outro', 'senha-outro'))
            ->assertOk();

        $this->assertSame(2, ConvenioCredencial::query()->count());
        $this->assertSame(
            'login-unimed',
            ConvenioCredencial::query()->where('convenio_id', $unimed->id)->sole()->campo('login'),
        );
        $this->assertSame(
            'senha-outro',
            ConvenioCredencial::query()->where('convenio_id', $outro->id)->sole()->campo('password'),
        );
    }

    public function test_salvar_duas_vezes_atualiza_em_vez_de_criar_segunda_linha(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->putJson(self::ROTA."/{$convenio->id}", $this->payloadUnimed('primeiro', 'senha'))->assertOk();
        $this->putJson(self::ROTA."/{$convenio->id}", $this->payloadUnimed('segundo', 'senha'))->assertOk();

        $this->assertSame(1, ConvenioCredencial::query()->where('convenio_id', $convenio->id)->count());
        $this->assertSame('segundo', ConvenioCredencial::query()->sole()->campo('login'));
    }

    public function test_segredo_nunca_volta_na_resposta(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $resposta = $this->putJson(self::ROTA."/{$convenio->id}", $this->payloadUnimed('operador', 'senha-secreta'))
            ->assertOk()
            ->assertJsonPath('data.credencial.campos.password.preenchido', true)
            ->assertJsonPath('data.credencial.campos.login.valor', 'operador');

        $this->assertStringNotContainsString('senha-secreta', $resposta->getContent());
        $this->assertStringNotContainsString('senha-secreta', $this->getJson(self::ROTA)->getContent());
    }

    public function test_senha_em_branco_preserva_a_gravada(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->putJson(self::ROTA."/{$convenio->id}", $this->payloadUnimed('operador', 'senha-original'))->assertOk();

        $payload = $this->payloadUnimed('operador-novo', '');
        $this->putJson(self::ROTA."/{$convenio->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.credencial.campos.login.valor', 'operador-novo')
            ->assertJsonPath('data.credencial.campos.password.preenchido', true);

        $this->assertSame('senha-original', ConvenioCredencial::query()->sole()->campo('password'));
    }

    public function test_chave_fora_do_catalogo_e_recusada(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $payload = $this->payloadUnimed('operador', 'senha');
        $payload['credenciais']['campo_inventado'] = 'x';

        $this->putJson(self::ROTA."/{$convenio->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['credenciais.campo_inventado']);
    }

    public function test_campo_obrigatorio_ausente_e_recusado(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->putJson(self::ROTA."/{$convenio->id}", [
            'driver' => ConvenioDriverCatalog::UNIMED_RDA,
            'credenciais' => ['base_url' => 'https://rda.unimed.com.br'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['credenciais.login', 'credenciais.password']);
    }

    /**
     * Sétimo critério de aceite: driver sem campos mostra o aviso e não impede
     * o uso da tela para os outros convênios.
     */
    public function test_driver_sem_campos_traz_aviso_e_nao_aceita_campo_algum(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'SC Saúde')->firstOrFail();

        $this->putJson(self::ROTA."/{$convenio->id}", [
            'driver' => ConvenioDriverCatalog::SCSAUDE,
            'credenciais' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.catalogo.implementado', false)
            ->assertJsonPath('data.catalogo.campos', [])
            ->assertJsonPath('data.credencial.pronta', false);

        $this->assertNotNull(
            $this->getJson(self::ROTA)->assertOk()->json('data.0.catalogo.aviso')
                ?? $this->getJson(self::ROTA)->json('meta.drivers.1.aviso'),
        );

        $this->putJson(self::ROTA."/{$convenio->id}", [
            'driver' => ConvenioDriverCatalog::SCSAUDE,
            'credenciais' => ['login' => 'chute', 'password' => 'chute'],
        ])->assertStatus(422);
    }

    /** A listagem traz todos os convênios do tenant, com ou sem credencial. */
    public function test_listagem_traz_convenio_sem_credencial(): void
    {
        $this->autenticar();

        $corpo = $this->getJson(self::ROTA)->assertOk()->json();

        $this->assertNotEmpty($corpo['data']);
        $this->assertNull($corpo['data'][0]['credencial']);
        $this->assertSame(
            ConvenioDriverCatalog::drivers(),
            array_column($corpo['meta']['drivers'], 'driver'),
        );
    }

    /**
     * Gravar credencial não liga automação: `connector_driver` não é tocado.
     * É a distinção que esta change existe para deixar clara.
     */
    public function test_gravar_credencial_nao_mexe_no_connector_driver(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'SC Saúde')->firstOrFail();
        $antes = $convenio->connector_driver;

        $this->putJson(self::ROTA."/{$convenio->id}", $this->payloadUnimed('operador', 'senha'))->assertOk();

        $this->assertSame($antes, $convenio->fresh()->connector_driver);
    }

    public function test_reativar_limpa_a_pausa_daquele_convenio_e_audita(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->putJson(self::ROTA."/{$convenio->id}", $this->payloadUnimed('operador', 'senha'))->assertOk();

        $credencial = ConvenioCredencial::query()->sole();
        $credencial->forceFill([
            'ativo' => false,
            'automation_paused_at' => now(),
            'automation_paused_reason' => 'PORTAL_STRUCTURE_CHANGED',
        ])->save();

        $this->postJson(self::ROTA."/{$convenio->id}/reativar")
            ->assertOk()
            ->assertJsonPath('data.credencial.ativo', true)
            ->assertJsonPath('data.credencial.automation_paused_at', null)
            ->assertJsonPath('data.credencial.automation_paused_reason', null);

        $this->assertDatabaseHas('audit_logs', [
            'acao' => 'convenio_credencial.automation_reactivated',
            'entidade_id' => $credencial->id,
        ]);
    }

    public function test_auditoria_registra_a_alteracao_sem_o_valor_do_segredo(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->putJson(self::ROTA."/{$convenio->id}", $this->payloadUnimed('operador', 'senha-secreta'))->assertOk();

        $audit = AuditLog::query()->where('acao', 'convenio_credencial.updated')->sole();

        $this->assertSame($convenio->id, $audit->payload['convenio_id']);
        $this->assertContains('password', $audit->payload['campos_informados']);
        $this->assertStringNotContainsString('senha-secreta', json_encode($audit->payload));
    }

    public function test_convenio_de_outro_tenant_retorna_404(): void
    {
        $this->autenticar();

        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Credencial Externa',
            'slug' => 'clinica-credencial-externa',
            'cnpj' => '66.666.666/0001-66',
            'ativo' => true,
        ]);
        $alheio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Alheio',
            'connector_type' => 'manual',
            'ativo' => true,
        ]);

        $this->putJson(self::ROTA."/{$alheio->id}", $this->payloadUnimed('x', 'y'))->assertNotFound();
        $this->postJson(self::ROTA."/{$alheio->id}/reativar")->assertNotFound();
    }

    public function test_usuario_sem_permissao_nao_alcanca_as_rotas(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        $convenio = Convenio::query()->where('tenant_id', $user->tenant_id)->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        $user->roles()->detach();
        Sanctum::actingAs($user);

        $this->getJson(self::ROTA)->assertForbidden();
        $this->putJson(self::ROTA."/{$convenio->id}", $this->payloadUnimed('x', 'y'))->assertForbidden();
        $this->getJson(self::ROTA."/{$convenio->id}/worker-health")->assertForbidden();
        $this->postJson(self::ROTA."/{$convenio->id}/reativar")->assertForbidden();
    }

    /**
     * Sexto critério de aceite: só a permissão antiga já abre a tela nova. A
     * migration de sincronização cobre quem existia; isto cobre a rota.
     */
    public function test_permissao_antiga_da_unimed_ainda_abre_a_tela_nova(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        $user->roles()->detach();

        $papel = Role::findOrCreate('so-unimed', 'web');
        $papel->givePermissionTo(Permission::findOrCreate('configuracoes.unimed.manage', 'web'));
        $papel->revokePermissionTo('configuracoes.convenios.manage');
        $user->assignRole($papel);

        Sanctum::actingAs($user);

        $this->getJson(self::ROTA)->assertOk();
    }

    /**
     * Os de-para passam a levar o convênio no CAMINHO, e não em query.
     *
     * A tela nova escolhe o convênio no topo; um de-para não existe fora de um
     * convênio, e tê-lo como campo do formulário deixava salvá-lo apontando
     * para um convênio diferente do que estava sendo editado.
     */
    public function test_de_para_de_especialidade_pelo_caminho_do_convenio(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('tenant_id', $convenio->tenant_id)->firstOrFail();

        $this->postJson(self::ROTA."/{$convenio->id}/mapeamentos/especialidades", [
            'convenio_id' => $convenio->id,
            'especialidade_id' => $especialidade->id,
            'codigo_procedimento' => '50000470',
            'quantidade_padrao' => 10,
            'ativo' => true,
        ])->assertCreated();

        $listagem = $this->getJson(self::ROTA."/{$convenio->id}/mapeamentos/especialidades")
            ->assertOk()
            ->json('data');

        $this->assertSame('50000470', collect($listagem)->firstWhere('especialidade_id', $especialidade->id)['codigo_procedimento']);
    }

    public function test_de_para_de_profissional_pelo_caminho_do_convenio(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $profissional = Profissional::query()->where('tenant_id', $convenio->tenant_id)->firstOrFail();

        $criado = $this->postJson(self::ROTA."/{$convenio->id}/mapeamentos/profissionais", [
            'convenio_id' => $convenio->id,
            'profissional_id' => $profissional->id,
            'codigo_operadora' => '1234',
            'ativo' => true,
        ])->assertCreated()->json('data.id');

        $this->patchJson(self::ROTA."/{$convenio->id}/mapeamentos/profissionais/{$criado}", [
            'convenio_id' => $convenio->id,
            'profissional_id' => $profissional->id,
            'codigo_operadora' => '4321',
            'ativo' => true,
        ])->assertOk()->assertJsonPath('data.codigo_operadora', '4321');
    }

    /** As rotas antigas, com `convenio_id` em query, seguem respondendo. */
    public function test_rotas_antigas_de_de_para_continuam_respondendo(): void
    {
        $this->autenticar();

        $this->getJson('/api/configuracoes/unimed/mapeamentos/especialidades')->assertOk();
        $this->getJson('/api/configuracoes/unimed/mapeamentos/profissionais')->assertOk();
    }

    public function test_de_para_de_convenio_alheio_retorna_404(): void
    {
        $this->autenticar();

        $tenant = Tenant::query()->create([
            'nome' => 'Clínica De-Para Externa',
            'slug' => 'clinica-de-para-externa',
            'cnpj' => '77.777.777/0001-77',
            'ativo' => true,
        ]);
        $alheio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio De-Para Alheio',
            'connector_type' => 'manual',
            'ativo' => true,
        ]);

        $this->getJson(self::ROTA."/{$alheio->id}/mapeamentos/especialidades")->assertNotFound();
        $this->getJson(self::ROTA."/{$alheio->id}/mapeamentos/profissionais")->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function payloadUnimed(string $login, string $senha): array
    {
        return [
            'driver' => ConvenioDriverCatalog::UNIMED_RDA,
            'credenciais' => [
                'login' => $login,
                'password' => $senha,
                'base_url' => 'https://rda.unimed.com.br',
                'nome_contratado' => 'Centro Neuro Kids Ltda',
            ],
        ];
    }

    private function autenticar(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }
}

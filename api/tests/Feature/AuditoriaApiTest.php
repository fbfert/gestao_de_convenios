<?php

namespace Tests\Feature;

use App\Jobs\ExpurgarAuditoriaJob;
use App\Models\AuditLog;
use App\Models\ConfiguracaoGlobal;
use App\Models\Convenio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditoriaApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_alteracao_de_modelo_vira_evento_com_antes_e_depois(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->firstOrFail();

        $this->patchJson("/api/convenios/{$convenio->id}", [
            'nome' => 'Convênio Renomeado',
            'connector_type' => $convenio->connector_type,
            'ativo' => true,
        ])->assertOk();

        $evento = AuditLog::query()
            ->where('entidade', 'convenios')
            ->where('acao', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($convenio->nome, $evento->payload['antes']['nome']);
        $this->assertSame('Convênio Renomeado', $evento->payload['depois']['nome']);
        $this->assertNotNull($evento->user_id);
    }

    public function test_gravacao_sem_mudanca_nao_gera_evento(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->firstOrFail();

        $antes = AuditLog::query()->where('entidade', 'convenios')->count();

        $convenio->save();

        $this->assertSame($antes, AuditLog::query()->where('entidade', 'convenios')->count());
    }

    public function test_senha_e_chave_nunca_entram_na_trilha(): void
    {
        $this->autenticar();

        $this->putJson('/api/configuracoes/ia', [
            'openai' => [
                'api_key' => 'sk-um-segredo-que-nao-pode-vazar',
                'base_url' => 'https://api.openai.com/v1',
                'ativo' => true,
            ],
        ])->assertOk();

        $eventos = AuditLog::query()->where('entidade', 'ai_openai_settings')->get();

        $this->assertNotEmpty($eventos);

        foreach ($eventos as $evento) {
            $serializado = json_encode($evento->payload);
            $this->assertStringNotContainsString('sk-um-segredo-que-nao-pode-vazar', $serializado);
            $this->assertContains('api_key', $evento->payload['campos_ocultos'] ?? []);
        }
    }

    public function test_senha_do_convenio_continua_visivel_por_nao_ser_credencial(): void
    {
        $this->autenticar();

        // Neste domínio "senha" é o código de autorização da operadora, e é
        // justamente o que a trilha precisa mostrar. Se um padrão genérico
        // escondesse isso, a auditoria de guias perderia o miolo.
        $configuracao = ConfiguracaoGlobal::doTenant((int) $this->usuario()->tenant_id);
        $configuracao->update(['senha_alerta_dias' => 21]);

        $evento = AuditLog::query()
            ->where('entidade', 'configuracoes_globais')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(21, $evento->payload['depois']['senha_alerta_dias']);
    }

    public function test_login_logout_e_acesso_negado_guardam_origem(): void
    {
        $token = $this->postJson('/api/login', [
            'email' => 'admin@clinica-exemplo.test',
            'password' => 'password',
        ])->assertOk()->json('token');

        $login = AuditLog::query()->where('acao', 'acesso.login')->latest('id')->firstOrFail();
        $this->assertNotNull($login->ip);

        $this->postJson('/api/login', [
            'email' => 'admin@clinica-exemplo.test',
            'password' => 'errada',
        ])->assertStatus(401);

        $this->assertDatabaseHas('audit_logs', ['acao' => 'acesso.login_recusado']);

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['acao' => 'acesso.logout']);

        // Profissional não tem `permissoes.manage`: o 403 vira evento.
        Sanctum::actingAs($this->usuario('profissional@clinica-exemplo.test'));
        $this->getJson('/api/roles')->assertForbidden();

        $negado = AuditLog::query()->where('acao', 'acesso.negado')->latest('id')->firstOrFail();
        $this->assertSame('GET api/roles', $negado->payload['rota']);
    }

    public function test_evento_de_alteracao_nao_guarda_ip(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->firstOrFail();

        $this->patchJson("/api/convenios/{$convenio->id}", [
            'nome' => 'Outro Nome',
            'connector_type' => $convenio->connector_type,
            'ativo' => true,
        ])->assertOk();

        $evento = AuditLog::query()->where('entidade', 'convenios')->latest('id')->firstOrFail();

        $this->assertNull($evento->ip);
        $this->assertNull($evento->user_agent);
    }

    public function test_alteracao_de_permissoes_registra_o_delta(): void
    {
        $this->autenticar();

        $this->putJson('/api/roles/funcionario/permissions', [
            'permissions' => ['solicitacoes.view', 'guias.view'],
        ])->assertOk();

        $evento = AuditLog::query()->where('acao', 'papel.permissoes_alteradas')->latest('id')->firstOrFail();

        $this->assertSame('funcionario', $evento->payload['nome']);
        $this->assertContains('lancamentos.manage', $evento->payload['revogadas']);
    }

    public function test_consulta_filtra_por_acao_usuario_e_periodo(): void
    {
        $this->autenticar();
        $usuario = $this->usuario();

        AuditLog::query()->create([
            'tenant_id' => $usuario->tenant_id,
            'user_id' => $usuario->id,
            'acao' => 'teste.filtro',
            'entidade' => 'convenios',
            'entidade_id' => 1,
        ]);

        AuditLog::query()->create([
            'tenant_id' => $usuario->tenant_id,
            'user_id' => null,
            'acao' => 'teste.sistema',
            'entidade' => 'convenios',
            'entidade_id' => 1,
        ]);

        $this->getJson('/api/auditoria?acao=teste.filtro')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.acao', 'teste.filtro')
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/auditoria?usuario_id=sistema&acao=teste.sistema')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.usuario', null);

        $this->getJson('/api/auditoria?de='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_exportacao_devolve_csv_do_recorte_filtrado(): void
    {
        $this->autenticar();

        $resposta = $this->get('/api/auditoria/exportar?acao=acesso.login');

        $resposta->assertOk();
        $this->assertStringContainsString('text/csv', $resposta->headers->get('content-type'));
    }

    public function test_expurgo_exporta_antes_de_apagar(): void
    {
        Storage::fake('local');

        $usuario = $this->usuario();
        ConfiguracaoGlobal::doTenant((int) $usuario->tenant_id)->update(['auditoria_retencao_meses' => 3]);

        $antigo = AuditLog::query()->create([
            'tenant_id' => $usuario->tenant_id,
            'user_id' => $usuario->id,
            'acao' => 'teste.antigo',
            'entidade' => 'convenios',
            'entidade_id' => 1,
        ]);
        $antigo->forceFill(['created_at' => now()->subMonths(6)])->save();

        $recente = AuditLog::query()->create([
            'tenant_id' => $usuario->tenant_id,
            'user_id' => $usuario->id,
            'acao' => 'teste.recente',
            'entidade' => 'convenios',
            'entidade_id' => 1,
        ]);

        (new ExpurgarAuditoriaJob)->handle();

        $this->assertDatabaseMissing('audit_logs', ['id' => $antigo->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recente->id]);

        $arquivos = Storage::disk('local')->files('auditoria');
        $this->assertNotEmpty($arquivos);
        $this->assertStringContainsString('teste.antigo', Storage::disk('local')->get($arquivos[0]));

        // O expurgo entra na própria trilha.
        $this->assertDatabaseHas('audit_logs', ['acao' => 'auditoria.expurgada']);
    }

    public function test_expurgo_nao_apaga_quando_a_exportacao_falha(): void
    {
        $usuario = $this->usuario();
        ConfiguracaoGlobal::doTenant((int) $usuario->tenant_id)->update(['auditoria_retencao_meses' => 3]);

        $antigo = AuditLog::query()->create([
            'tenant_id' => $usuario->tenant_id,
            'user_id' => $usuario->id,
            'acao' => 'teste.antigo',
            'entidade' => 'convenios',
            'entidade_id' => 1,
        ]);
        $antigo->forceFill(['created_at' => now()->subMonths(6)])->save();

        // Disco que aceita a escrita e depois nega a existência: é o cenário de
        // gravação silenciosamente perdida, o pior caso para o expurgo.
        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('put')->andReturn(true);
        Storage::shouldReceive('exists')->andReturn(false);

        (new ExpurgarAuditoriaJob)->handle();

        $this->assertDatabaseHas('audit_logs', ['id' => $antigo->id]);
    }

    public function test_trilha_nao_expoe_registro_de_outro_tenant(): void
    {
        $usuario = $this->usuario();

        $externo = \App\Models\Tenant::query()->create([
            'nome' => 'Clínica Externa',
            'slug' => 'clinica-externa-auditoria',
            'ativo' => true,
        ]);

        AuditLog::query()->create([
            'tenant_id' => $externo->id,
            'user_id' => null,
            'acao' => 'teste.externo',
            'entidade' => 'convenios',
            'entidade_id' => 1,
        ]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/auditoria?acao=teste.externo')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function autenticar(): void
    {
        Sanctum::actingAs($this->usuario());
    }

    private function usuario(string $email = 'admin@clinica-exemplo.test'): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}

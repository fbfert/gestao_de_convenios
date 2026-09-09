<?php

namespace Tests\Feature;

use App\Http\Controllers\HealthController;
use App\Models\SaudeComponente;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SaudeService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SaudeApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function autenticar(): User
    {
        $usuario = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();

        Sanctum::actingAs($usuario);
        TenantContext::set((int) $usuario->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId((int) $usuario->tenant_id);

        return $usuario;
    }

    /**
     * Deixa o sistema no estado que o monitor externo consideraria saudável:
     * carimbo do agendador no cache e heartbeat nos componentes que o seeder
     * criou ativos. Sem isto o `/api/health` responde 503 com razão — o
     * componente `scheduler` nasce sem heartbeat nenhum, e "nunca deu sinal de
     * vida" é `down` por decisão de design.
     */
    private function sistemaSaudavel(): void
    {
        Cache::forever(HealthController::CHAVE_SCHEDULER, now());

        SaudeComponente::query()->withoutGlobalScopes()->where('ativo', true)
            ->update(['ultimo_heartbeat_em' => now(), 'ultimo_status' => 'ok']);
    }

    private function componente(int $tenantId, array $atributos = []): SaudeComponente
    {
        return SaudeComponente::query()->withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenantId,
            'chave' => 'conector.teste',
            'nome' => 'Conector de teste',
            'tipo' => 'connector',
            'intervalo_esperado_segundos' => 60,
            'ativo' => true,
        ], $atributos));
    }

    public function test_heartbeat_recente_resulta_em_saudavel(): void
    {
        $usuario = $this->autenticar();
        $componente = $this->componente((int) $usuario->tenant_id, [
            'ultimo_heartbeat_em' => now()->subSeconds(30),
        ]);

        $this->assertSame(
            SaudeComponente::ESTADO_SAUDAVEL,
            app(SaudeService::class)->estadoDe($componente),
        );
    }

    public function test_heartbeat_atrasado_ate_tres_vezes_resulta_em_atencao(): void
    {
        $usuario = $this->autenticar();
        $componente = $this->componente((int) $usuario->tenant_id, [
            'ultimo_heartbeat_em' => now()->subSeconds(120), // 2x o intervalo de 60s
        ]);

        $this->assertSame(
            SaudeComponente::ESTADO_ATENCAO,
            app(SaudeService::class)->estadoDe($componente),
        );
    }

    public function test_heartbeat_de_quatro_vezes_o_intervalo_resulta_em_fora(): void
    {
        $usuario = $this->autenticar();
        $componente = $this->componente((int) $usuario->tenant_id, [
            'ultimo_heartbeat_em' => now()->subSeconds(240), // 4x o intervalo de 60s
        ]);

        $this->assertSame(
            SaudeComponente::ESTADO_FORA,
            app(SaudeService::class)->estadoDe($componente),
        );
    }

    public function test_componente_sem_heartbeat_nenhum_resulta_em_fora(): void
    {
        $usuario = $this->autenticar();
        $componente = $this->componente((int) $usuario->tenant_id, [
            'ultimo_heartbeat_em' => null,
        ]);

        $this->assertSame(
            SaudeComponente::ESTADO_FORA,
            app(SaudeService::class)->estadoDe($componente),
        );
    }

    public function test_lista_os_componentes_do_tenant_com_estado_derivado(): void
    {
        $usuario = $this->autenticar();
        $this->componente((int) $usuario->tenant_id, [
            'chave' => 'conector.vivo',
            'nome' => 'Conector vivo',
            'ultimo_heartbeat_em' => now(),
        ]);

        $resposta = $this->getJson('/api/saude')->assertOk();

        $chaves = collect($resposta->json('data'))->pluck('chave');
        $this->assertTrue($chaves->contains('conector.vivo'));
        $this->assertSame(
            SaudeComponente::ESTADO_SAUDAVEL,
            collect($resposta->json('data'))->firstWhere('chave', 'conector.vivo')['estado'],
        );
    }

    public function test_componente_desativado_fica_fora_da_listagem(): void
    {
        $usuario = $this->autenticar();
        $this->componente((int) $usuario->tenant_id, [
            'chave' => 'conector.desligado',
            'ativo' => false,
            'ultimo_heartbeat_em' => now(),
        ]);

        $resposta = $this->getJson('/api/saude')->assertOk();

        $this->assertFalse(
            collect($resposta->json('data'))->pluck('chave')->contains('conector.desligado'),
        );
    }

    public function test_componente_de_outro_tenant_nao_aparece(): void
    {
        $usuario = $this->autenticar();
        $outro = Tenant::query()->create(['nome' => 'Outra Clínica', 'slug' => 'outra-clinica']);

        $this->componente((int) $outro->id, [
            'chave' => 'conector.alheio',
            'ultimo_heartbeat_em' => now(),
        ]);

        $resposta = $this->getJson('/api/saude')->assertOk();

        $this->assertFalse(
            collect($resposta->json('data'))->pluck('chave')->contains('conector.alheio'),
        );
    }

    public function test_heartbeat_carimba_todos_os_tenants_quando_nao_ha_tenant_definido(): void
    {
        $usuario = $this->autenticar();
        $outro = Tenant::query()->create(['nome' => 'Outra Clínica', 'slug' => 'outra-clinica-2']);

        $a = $this->componente((int) $usuario->tenant_id, ['chave' => 'infra.compartilhada']);
        $b = $this->componente((int) $outro->id, ['chave' => 'infra.compartilhada']);

        // Como o agendador: fora de requisição, sem tenant resolvido.
        TenantContext::clear();
        $carimbados = app(SaudeService::class)->registrarHeartbeat('infra.compartilhada');

        $this->assertSame(2, $carimbados);
        $this->assertNotNull($a->fresh()->ultimo_heartbeat_em);
        $this->assertNotNull($b->fresh()->ultimo_heartbeat_em);
    }

    public function test_heartbeat_nao_cria_componente_inexistente(): void
    {
        $this->autenticar();

        $carimbados = app(SaudeService::class)->registrarHeartbeat('nao.existe');

        $this->assertSame(0, $carimbados);
        $this->assertSame(
            0,
            SaudeComponente::query()->withoutGlobalScopes()->where('chave', 'nao.existe')->count(),
        );
    }

    public function test_heartbeat_nao_polui_a_trilha_de_auditoria(): void
    {
        $usuario = $this->autenticar();
        $this->componente((int) $usuario->tenant_id, ['chave' => 'infra.silenciosa']);

        $antes = \App\Models\AuditLog::query()->withoutGlobalScopes()->count();
        app(SaudeService::class)->registrarHeartbeat('infra.silenciosa');
        $depois = \App\Models\AuditLog::query()->withoutGlobalScopes()->count();

        // O carimbo roda a cada minuto: auditá-lo somaria 1.440 linhas por dia
        // por componente e afogaria a trilha que o ADR-07 existe para manter útil.
        $this->assertSame($antes, $depois);
    }

    public function test_health_publico_lista_chave_e_estado_sem_identificar_tenant(): void
    {
        $usuario = $this->autenticar();
        $this->sistemaSaudavel();
        $this->componente((int) $usuario->tenant_id, [
            'chave' => 'conector.publico',
            'ultimo_heartbeat_em' => now(),
        ]);

        $corpo = $this->getJson('/api/health')->assertOk()->json();

        $item = collect($corpo['componentes'])->firstWhere('chave', 'conector.publico');
        $this->assertNotNull($item);
        $this->assertSame(['chave', 'estado'], array_keys($item));
    }

    public function test_health_publico_agrupa_por_chave_e_nao_entrega_quantidade_de_tenants(): void
    {
        $usuario = $this->autenticar();
        $outro = Tenant::query()->create(['nome' => 'Outra Clínica', 'slug' => 'outra-clinica-3']);
        $this->sistemaSaudavel();

        $this->componente((int) $usuario->tenant_id, ['chave' => 'infra.comum', 'ultimo_heartbeat_em' => now()]);
        $this->componente((int) $outro->id, ['chave' => 'infra.comum', 'ultimo_heartbeat_em' => now()]);

        $corpo = $this->getJson('/api/health')->json();

        // Uma linha só: repetir a chave por tenant entregaria quantas clínicas
        // existem, num endpoint sem autenticação.
        $this->assertSame(
            1,
            collect($corpo['componentes'])->where('chave', 'infra.comum')->count(),
        );
    }

    public function test_health_publico_usa_o_pior_estado_entre_os_tenants(): void
    {
        $usuario = $this->autenticar();
        $outro = Tenant::query()->create(['nome' => 'Outra Clínica', 'slug' => 'outra-clinica-4']);
        $this->sistemaSaudavel();

        $this->componente((int) $usuario->tenant_id, ['chave' => 'infra.mista', 'ultimo_heartbeat_em' => now()]);
        $this->componente((int) $outro->id, ['chave' => 'infra.mista', 'ultimo_heartbeat_em' => now()->subSeconds(600)]);

        $corpo = $this->getJson('/api/health')->json();

        $item = collect($corpo['componentes'])->firstWhere('chave', 'infra.mista');
        $this->assertSame(SaudeComponente::ESTADO_FORA, $item['estado']);
    }

    public function test_componente_fora_derruba_o_health_publico(): void
    {
        $usuario = $this->autenticar();

        // Primeiro prova que o sistema está verde, senão o 503 do final poderia
        // vir de qualquer outra coisa e o teste passaria sem testar nada.
        $this->sistemaSaudavel();
        $this->getJson('/api/health')->assertOk();

        $this->componente((int) $usuario->tenant_id, [
            'chave' => 'conector.morto',
            'ultimo_heartbeat_em' => now()->subSeconds(600),
        ]);

        // É isto que faz o monitor externo da fase 0 acordar alguém quando uma
        // peça do produto cai, e não só quando a infraestrutura cai.
        $this->getJson('/api/health')->assertStatus(503);
    }

    public function test_componentes_padrao_nascem_para_o_tenant(): void
    {
        $usuario = $this->autenticar();

        $chaves = SaudeComponente::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $usuario->tenant_id)
            ->pluck('ativo', 'chave');

        $this->assertTrue($chaves->has(SaudeComponente::CHAVE_SCHEDULER));
        $this->assertTrue($chaves->has(SaudeComponente::CHAVE_FILA));
        $this->assertTrue($chaves->has(SaudeComponente::CHAVE_SMTP));

        // Só o agendador nasce ativo: é o único com fonte de heartbeat
        // periódica e incondicional hoje. Ver o cabeçalho do SaudeComponenteSeeder.
        $this->assertTrue((bool) $chaves[SaudeComponente::CHAVE_SCHEDULER]);
        $this->assertFalse((bool) $chaves[SaudeComponente::CHAVE_FILA]);
        $this->assertFalse((bool) $chaves[SaudeComponente::CHAVE_SMTP]);
    }

    public function test_saude_exige_autenticacao(): void
    {
        $this->getJson('/api/saude')->assertUnauthorized();
    }
}

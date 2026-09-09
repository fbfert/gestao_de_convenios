<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\AlertaDestinatario;
use App\Models\AlertaDestinatarioGlobal;
use App\Models\AlertaRegra;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Alertas\NotificadorDeAlertas;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificacoesDeAlertasTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $usuario = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($usuario);
        TenantContext::set((int) $usuario->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId((int) $usuario->tenant_id);
        $this->tenantId = (int) $usuario->tenant_id;
    }

    private function destinatario(array $atributos = []): AlertaDestinatario
    {
        return AlertaDestinatario::query()->withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->tenantId,
            'email' => 'recepcao@clinica-exemplo.test',
            'nome' => 'Recepção',
            'niveis' => ['amarelo', 'vermelho'],
            'chaves' => null,
            'canal' => AlertaDestinatario::CANAL_AMBOS,
            'horario_digest' => 8,
            'ativo' => true,
        ], $atributos));
    }

    private function alerta(array $atributos = []): Alerta
    {
        return Alerta::query()->withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->tenantId,
            'chave' => AlertaRegra::CHAVE_GUIA_NEGADA,
            'nivel' => Alerta::NIVEL_VERMELHO,
            'titulo' => 'Guia negada — 123',
            'aberto_em' => now(),
            'aberto_dedupe' => Alerta::DEDUPE_ABERTO,
        ], $atributos));
    }

    public function test_digest_nao_envia_quando_nao_ha_alerta_aberto(): void
    {
        Mail::fake();
        $this->destinatario();

        app(NotificadorDeAlertas::class)->enviarDigestDoTenant($this->tenantId, 8);

        // Digest vazio todo dia treina a pessoa a arquivar sem ler.
        Mail::assertNothingSent();
    }

    public function test_digest_sai_no_horario_do_destinatario(): void
    {
        Mail::fake();
        $this->destinatario(['horario_digest' => 8]);
        $this->alerta();

        app(NotificadorDeAlertas::class)->enviarDigestDoTenant($this->tenantId, 9);
        Mail::assertNothingSent();

        app(NotificadorDeAlertas::class)->enviarDigestDoTenant($this->tenantId, 8);
        Mail::assertSentCount(1);
    }

    public function test_destinatario_inscrito_em_uma_chave_nao_recebe_alerta_de_outra(): void
    {
        Mail::fake();
        $this->destinatario(['chaves' => [AlertaRegra::CHAVE_SENHA_VENCENDO]]);
        $this->alerta(['chave' => AlertaRegra::CHAVE_GUIA_NEGADA]);

        app(NotificadorDeAlertas::class)->enviarDigestDoTenant($this->tenantId, 8);

        Mail::assertNothingSent();
    }

    public function test_destinatario_inscrito_so_em_vermelho_nao_recebe_amarelo(): void
    {
        Mail::fake();
        $this->destinatario(['niveis' => [Alerta::NIVEL_VERMELHO]]);
        $this->alerta(['nivel' => Alerta::NIVEL_AMARELO, 'chave' => AlertaRegra::CHAVE_SENHA_VENCENDO]);

        app(NotificadorDeAlertas::class)->enviarDigestDoTenant($this->tenantId, 8);

        Mail::assertNothingSent();
    }

    public function test_destinatario_inativo_nao_recebe(): void
    {
        Mail::fake();
        $this->destinatario(['ativo' => false]);
        $this->alerta();

        app(NotificadorDeAlertas::class)->enviarDigestDoTenant($this->tenantId, 8);

        Mail::assertNothingSent();
    }

    public function test_imediato_so_dispara_para_vermelho_de_regra_critica(): void
    {
        Mail::fake();
        $this->destinatario();
        $regra = AlertaRegra::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->firstOrFail();

        $amarelo = $this->alerta(['nivel' => Alerta::NIVEL_AMARELO]);
        app(NotificadorDeAlertas::class)->notificarImediato($amarelo, $regra);
        Mail::assertNothingSent();

        $regra->forceFill(['critica' => false])->save();
        $vermelho = $this->alerta(['nivel' => Alerta::NIVEL_VERMELHO, 'entidade' => 'guias', 'entidade_id' => 7]);
        app(NotificadorDeAlertas::class)->notificarImediato($vermelho, $regra->fresh());
        Mail::assertNothingSent();
    }

    public function test_imediato_nao_reenvia_dentro_da_janela_de_silencio(): void
    {
        Mail::fake();
        $this->destinatario();
        $regra = AlertaRegra::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->firstOrFail();
        $regra->forceFill(['critica' => true, 'janela_silencio_horas' => 24])->save();

        $alerta = $this->alerta();

        app(NotificadorDeAlertas::class)->notificarImediato($alerta, $regra);
        Mail::assertSentCount(1);

        // Sem esta trava, uma automação em loop manda 200 e-mails de madrugada.
        app(NotificadorDeAlertas::class)->notificarImediato($alerta->fresh(), $regra);
        Mail::assertSentCount(1);
    }

    public function test_destinatario_global_recebe_todos_os_tenants_num_email_so(): void
    {
        Mail::fake();

        $outro = Tenant::query()->create(['nome' => 'Outra Clínica', 'slug' => 'outra-notif']);
        $this->alerta();
        Alerta::query()->withoutGlobalScopes()->create([
            'tenant_id' => $outro->id,
            'chave' => AlertaRegra::CHAVE_GUIA_NEGADA,
            'nivel' => Alerta::NIVEL_VERMELHO,
            'titulo' => 'Guia negada — outra clínica',
            'aberto_em' => now(),
            'aberto_dedupe' => Alerta::DEDUPE_ABERTO,
        ]);

        AlertaDestinatarioGlobal::query()->create([
            'email' => 'suporte@xiax.com.br',
            'nome' => 'Suporte Xiax',
            'niveis' => ['amarelo', 'vermelho'],
            'chaves' => null,
            'canal' => AlertaDestinatario::CANAL_DIGEST,
            'horario_digest' => 8,
            'ativo' => true,
        ]);

        app(NotificadorDeAlertas::class)->enviarDigestGlobal(8);

        // UM e-mail, e não um por tenant.
        Mail::assertSentCount(1);
    }

    public function test_destinatario_global_sobrevive_a_consulta_sem_tenant_resolvido(): void
    {
        AlertaDestinatarioGlobal::query()->create([
            'email' => 'suporte@xiax.com.br',
            'niveis' => ['vermelho'],
            'canal' => AlertaDestinatario::CANAL_DIGEST,
            'horario_digest' => 8,
            'ativo' => true,
        ]);

        // É a razão de ele viver em tabela separada: com `tenant_id` nulo na
        // tabela do tenant, o global scope o esconderia aqui e o suporte
        // simplesmente pararia de receber, em silêncio.
        TenantContext::clear();

        $this->assertSame(1, AlertaDestinatarioGlobal::query()->count());
    }

    public function test_falhas_seguidas_desativam_o_destinatario_e_abrem_alerta(): void
    {
        $destinatario = $this->destinatario(['email' => 'invalido@']);
        $this->alerta();

        // Sem Mail::fake: o endereço inválido faz o envio real lançar.
        for ($i = 0; $i < AlertaDestinatario::LIMITE_FALHAS; $i++) {
            app(NotificadorDeAlertas::class)->enviarDigestDoTenant($this->tenantId, 8);
        }

        $this->assertFalse((bool) $destinatario->fresh()->ativo);
        $this->assertSame(
            1,
            Alerta::query()
                ->withoutGlobalScopes()
                ->where('chave', NotificadorDeAlertas::CHAVE_DESTINATARIO_FALHANDO)
                ->count(),
        );
    }

    public function test_envio_bem_sucedido_marca_como_verificado(): void
    {
        Mail::fake();
        $destinatario = $this->destinatario();
        $this->alerta();

        $this->assertNull($destinatario->verificado_em);

        app(NotificadorDeAlertas::class)->enviarDigestDoTenant($this->tenantId, 8);

        $this->assertNotNull($destinatario->fresh()->verificado_em);
    }

    public function test_crud_de_destinatarios_grava_e_lista(): void
    {
        $this->postJson('/api/alertas/destinatarios', [
            'email' => 'novo@clinica-exemplo.test',
            'nome' => 'Novo',
            'niveis' => ['vermelho'],
            'chaves' => null,
            'canal' => 'digest',
            'horario_digest' => 9,
            'ativo' => true,
        ])->assertCreated();

        $lista = $this->getJson('/api/alertas/destinatarios')->assertOk()->json('data');

        $this->assertCount(1, $lista);
        $this->assertSame('novo@clinica-exemplo.test', $lista[0]['email']);
        // `chaves` nulo significa TODAS: é o padrão mais útil para quem acabou
        // de cadastrar e ainda não sabe quais chaves existem.
        $this->assertNull($lista[0]['chaves']);
    }
}

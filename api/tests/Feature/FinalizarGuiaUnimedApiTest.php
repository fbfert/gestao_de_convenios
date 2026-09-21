<?php

namespace Tests\Feature;

use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\ConfiguracaoGlobal;
use App\Models\Convenio;
use App\Models\ConvenioCredencial;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Automation\AutomacaoService;
use App\Services\Automation\ConsultarStatusUnimedService;
use App\Services\Automation\FakeUnimedWorkerClient;
use App\Services\Automation\FinalizarGuiaUnimedService;
use App\Services\Automation\GerarGuiaUnimedService;
use App\Services\Automation\UnimedCircuitBreakerService;
use App\Services\Automation\UnimedWorkerClient;
use App\Support\GuiaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A finalização de guia na Unimed vista pela API — ver a spec
 * `automacao-unimed-finalizar-guia`.
 *
 * O worker tem cobertura própria contra a fixture (worker-unimed/tests). Aqui
 * o que importa é o que a API decide: o que o pré-voo exige, o que o disparo
 * recusa, o que o payload leva, e — o ponto mais importante — QUANDO a guia é
 * dada por finalizada.
 */
class FinalizarGuiaUnimedApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ---------------------------------------------------------------- pré-voo

    public function test_pre_voo_libera_guia_pronta(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(2, autorizadas: 2);
        $this->anexarFolha($guia);

        $resposta = $this->getJson("/api/guias/{$guia->id}/finalizar-unimed/pre-voo")->assertOk();

        $this->assertTrue($resposta->json('data.pode_finalizar'));
        $this->assertSame([], $resposta->json('data.impedimentos'));
        $this->assertSame([], $resposta->json('data.decisoes'));
        $this->assertCount(2, $resposta->json('data.sessoes'));
    }

    public function test_pre_voo_impede_guia_de_convenio_sem_automacao(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(1);
        $guia->convenio->update(['connector_driver' => null, 'connector_type' => 'manual']);

        $resposta = $this->getJson("/api/guias/{$guia->id}/finalizar-unimed/pre-voo")->assertOk();

        $this->assertFalse($resposta->json('data.pode_finalizar'));
        $this->assertContains(
            'A guia não é de convênio com automação Unimed.',
            $resposta->json('data.impedimentos'),
        );
    }

    public function test_pre_voo_impede_guia_sem_sessao_registrada(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(0);

        $resposta = $this->getJson("/api/guias/{$guia->id}/finalizar-unimed/pre-voo")->assertOk();

        $this->assertContains(
            'A guia precisa ter ao menos uma sessão registrada.',
            $resposta->json('data.impedimentos'),
        );
    }

    public function test_pre_voo_impede_guia_sem_credencial_ativa(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(1);
        ConvenioCredencial::query()->where('convenio_id', $guia->convenio_id)->update(['ativo' => false]);

        $resposta = $this->getJson("/api/guias/{$guia->id}/finalizar-unimed/pre-voo")->assertOk();

        $this->assertContains(
            'A credencial Unimed ativa não está configurada.',
            $resposta->json('data.impedimentos'),
        );
    }

    public function test_pre_voo_acusa_conflito_de_agenda_e_nao_libera(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(0, autorizadas: 10);
        $this->gravarSessao($guia, '2026-09-21', '08:00');
        $this->gravarSessao($guia, '2026-09-21', '08:20');
        $this->anexarFolha($guia);

        $resposta = $this->getJson("/api/guias/{$guia->id}/finalizar-unimed/pre-voo")->assertOk();

        $this->assertFalse($resposta->json('data.pode_finalizar'));
        $this->assertSame('intervalo', $resposta->json('data.conflitos.0.tipo'));
    }

    public function test_pre_voo_pede_decisao_quando_ha_menos_sessoes_que_o_autorizado(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(2, autorizadas: 10);
        $this->anexarFolha($guia);

        $resposta = $this->getJson("/api/guias/{$guia->id}/finalizar-unimed/pre-voo")->assertOk();

        $this->assertSame('confirmar_menos_sessoes', $resposta->json('data.decisoes.0.chave'));
        $this->assertSame(2, $resposta->json('data.decisoes.0.registradas'));
        $this->assertSame(10, $resposta->json('data.decisoes.0.autorizadas'));
    }

    public function test_pre_voo_pede_decisao_quando_ha_mais_sessoes_que_o_autorizado(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(4, autorizadas: 2);
        $this->anexarFolha($guia);

        $resposta = $this->getJson("/api/guias/{$guia->id}/finalizar-unimed/pre-voo")->assertOk();

        $this->assertSame('limitar_ao_autorizado', $resposta->json('data.decisoes.0.chave'));
    }

    public function test_pre_voo_pede_decisao_quando_nao_ha_folha_anexada(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(2, autorizadas: 2);

        $resposta = $this->getJson("/api/guias/{$guia->id}/finalizar-unimed/pre-voo")->assertOk();

        $this->assertSame('confirmar_sem_anexo', $resposta->json('data.decisoes.0.chave'));
        $this->assertSame(0, $resposta->json('data.folhas'));
    }

    // ---------------------------------------------------------------- disparo

    public function test_disparo_enfileira_a_operacao_de_finalizacao(): void
    {
        Queue::fake();
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(2, autorizadas: 2);
        $this->anexarFolha($guia);

        $this->postJson("/api/guias/{$guia->id}/finalizar-unimed")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.operacao', 'finalizar_guia');

        Queue::assertPushed(ExecutarAutomacaoUnimedJob::class, 1);
    }

    public function test_disparo_recusa_quando_a_decisao_nao_vem_confirmada(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(2, autorizadas: 10);
        $this->anexarFolha($guia);

        $this->postJson("/api/guias/{$guia->id}/finalizar-unimed")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirmar_menos_sessoes']);

        $this->assertSame(0, AutomacaoExecucao::query()->where('guia_id', $guia->id)->count());
    }

    public function test_disparo_recusa_sem_a_confirmacao_de_falta_de_anexo(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(2, autorizadas: 2);

        $this->postJson("/api/guias/{$guia->id}/finalizar-unimed")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirmar_sem_anexo']);
    }

    public function test_disparo_recusa_com_conflito_de_agenda(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(0, autorizadas: 10);
        $this->gravarSessao($guia, '2026-09-21', '08:00');
        $this->gravarSessao($guia, '2026-09-21', '08:20');
        $this->anexarFolha($guia);

        $this->postJson("/api/guias/{$guia->id}/finalizar-unimed")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sessoes']);
    }

    public function test_disparo_recusa_segunda_finalizacao_simultanea(): void
    {
        Queue::fake();
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(2, autorizadas: 2);
        $this->anexarFolha($guia);

        $this->postJson("/api/guias/{$guia->id}/finalizar-unimed")->assertAccepted();

        $this->postJson("/api/guias/{$guia->id}/finalizar-unimed")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['guia']);
    }

    public function test_payload_leva_sessoes_ordenadas_anexos_e_o_modo_simulacao(): void
    {
        Queue::fake();
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(0, autorizadas: 10);
        $this->gravarSessao($guia, '2026-09-22', '10:00');
        $this->gravarSessao($guia, '2026-09-21', '08:00');
        $this->anexarFolha($guia, 'folha-1.pdf');
        $this->anexarFolha($guia, 'folha-2.pdf');

        $this->postJson("/api/guias/{$guia->id}/finalizar-unimed", [
            'confirmar_menos_sessoes' => true,
        ])->assertAccepted();

        $execucao = AutomacaoExecucao::query()->where('guia_id', $guia->id)->firstOrFail();
        $payload = app(FinalizarGuiaUnimedService::class)->payloadParaWorker($execucao);

        $this->assertSame($guia->numero_guia, $payload['numero_guia']);
        $this->assertSame(10, $payload['sessoes_autorizadas']);
        $this->assertSame(
            [['data' => '2026-09-21', 'hora' => '08:00'], ['data' => '2026-09-22', 'hora' => '10:00']],
            $payload['sessoes'],
        );
        $this->assertCount(2, $payload['anexos']);
        $this->assertArrayHasKey('local_path', $payload['anexos'][0]);
        $this->assertTrue($payload['simular'], 'a simulação nasce ligada');
        $this->assertSame('operador-unimed', $payload['credential']['login']);
    }

    public function test_limitar_ao_autorizado_corta_as_sessoes_mais_recentes(): void
    {
        Queue::fake();
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(0, autorizadas: 2);
        $this->gravarSessao($guia, '2026-09-21', '08:00');
        $this->gravarSessao($guia, '2026-09-22', '08:00');
        $this->gravarSessao($guia, '2026-09-23', '08:00');
        $this->anexarFolha($guia);

        $this->postJson("/api/guias/{$guia->id}/finalizar-unimed", [
            'limitar_ao_autorizado' => true,
        ])->assertAccepted();

        $execucao = AutomacaoExecucao::query()->where('guia_id', $guia->id)->firstOrFail();

        $this->assertSame(
            ['2026-09-21', '2026-09-22'],
            array_column($execucao->payload['sessoes'], 'data'),
        );
    }

    // ------------------------------------------------------- resultado / guia

    public function test_sucesso_real_finaliza_a_guia_com_origem_automacao(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(2, autorizadas: 2, senha: 'SENHA-9');
        $this->anexarFolha($guia);
        $this->desligarSimulacao($guia);

        $execucao = $this->enfileirar($guia);
        $this->comWorker(['status' => 'succeeded', 'simulado' => false]);
        $this->executarJob($execucao);

        $guia->refresh();
        $this->assertSame(GuiaStatus::FINALIZED, $guia->status);
        $this->assertNotNull($guia->data_finalizacao);

        $transicao = GuiaStatusHistorico::query()
            ->where('guia_id', $guia->id)
            ->where('para', GuiaStatus::FINALIZED)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(GuiaStatusHistorico::ORIGEM_AUTOMACAO, $transicao->origem);
    }

    public function test_sucesso_simulado_nao_finaliza_a_guia(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(2, autorizadas: 2, senha: 'SENHA-9');
        $this->anexarFolha($guia);

        $execucao = $this->enfileirar($guia);
        $this->comWorker([
            'status' => 'succeeded',
            'simulado' => true,
            'evidencia' => ['url' => 'https://portal.unimed.test/execucao.do'],
        ]);
        $this->executarJob($execucao);

        $this->assertSame('succeeded', $execucao->refresh()->status);
        $this->assertSame(GuiaStatus::APPROVED, $guia->refresh()->status);
        $this->assertNull($guia->data_finalizacao);
    }

    public function test_falha_mantem_a_guia_como_estava_e_permite_reacionar(): void
    {
        Queue::fake();
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(2, autorizadas: 2, senha: 'SENHA-9');
        $this->anexarFolha($guia);
        $this->desligarSimulacao($guia);

        $execucao = $this->enfileirar($guia);
        $this->comWorker([
            'status' => 'failed',
            'error_code' => 'DT_SERIE_FORMATO_RECUSADO',
            'message' => 'O portal recusou o formato da data.',
        ]);
        $this->executarJob($execucao);

        $execucao->refresh();
        $this->assertSame('failed', $execucao->status);
        $this->assertSame('DT_SERIE_FORMATO_RECUSADO', $execucao->erro_codigo);
        $this->assertSame(GuiaStatus::APPROVED, $guia->refresh()->status);

        // E dá para acionar de novo depois da falha.
        $this->postJson("/api/guias/{$guia->id}/finalizar-unimed")->assertAccepted();
    }

    // ------------------------------------------------------------ manual/auto

    public function test_finalizacao_manual_de_guia_unimed_e_recusada(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(1, autorizadas: 2);

        $this->patchJson("/api/guias/{$guia->id}/finalizar", ['senha' => 'ABC123'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Guia de convênio Unimed é finalizada na operadora, pelo botão "Finalizar na Unimed" na tela de Sessões.');

        $this->assertSame(GuiaStatus::APPROVED, $guia->refresh()->status);
    }

    public function test_finalizacao_manual_de_convenio_sem_automacao_continua_valendo(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimedComSessoes(1, autorizadas: 2);
        $guia->convenio->update(['connector_driver' => null, 'connector_type' => 'manual']);

        $this->patchJson("/api/guias/{$guia->id}/finalizar", [
            'senha' => 'ABC123',
            'validade_senha' => '2026-12-31',
        ])->assertOk();

        $this->assertSame(GuiaStatus::FINALIZED, $guia->refresh()->status);
    }

    public function test_permissao_de_leitura_nao_dispara_a_finalizacao(): void
    {
        $guia = $this->guiaUnimedComSessoes(2, autorizadas: 2);
        $this->anexarFolha($guia);

        // O profissional vê guias, mas não gerencia — finalizar na operadora é
        // irreversível e exige `guias.manage`.
        Sanctum::actingAs(User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail());

        $this->postJson("/api/guias/{$guia->id}/finalizar-unimed")->assertForbidden();
    }

    // -------------------------------------------------------------- bastidores

    private function autenticar(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail());
    }

    private function comWorker(array $resultado): void
    {
        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient($resultado));
    }

    private function enfileirar(Guia $guia, array $confirmacoes = []): AutomacaoExecucao
    {
        return app(FinalizarGuiaUnimedService::class)->enviar($guia, $confirmacoes, dispatch: false);
    }

    private function executarJob(AutomacaoExecucao $execucao): void
    {
        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(ConsultarStatusUnimedService::class),
            app(UnimedCircuitBreakerService::class),
        );
    }

    private function desligarSimulacao(Guia $guia): void
    {
        ConfiguracaoGlobal::query()
            ->where('tenant_id', $guia->tenant_id)
            ->update(['automacao_finalizar_guia_simulacao_ativo' => false]);
    }

    private function anexarFolha(Guia $guia, string $nome = 'folha.pdf'): void
    {
        app(\App\Services\Sessoes\FolhasDeRegistroService::class)->anexar(
            $guia,
            UploadedFile::fake()->create($nome, 32, 'application/pdf'),
        );
    }

    private function gravarSessao(Guia $guia, string $data, string $hora): Lancamento
    {
        return Lancamento::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'profissional_id' => $guia->profissional_id,
            'data_sessao' => $data,
            'hora_inicio' => $hora,
            'status' => 'completed',
        ]);
    }

    /**
     * Guia da Unimed automatizada, aprovada, com credencial ativa e `$sessoes`
     * sessões em dias distintos (um dia por sessão: o limite diário da
     * especialidade não deixa duas caírem no mesmo).
     */
    private function guiaUnimedComSessoes(int $sessoes, ?int $autorizadas = null, ?string $senha = null): Guia
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $convenio->update(['connector_type' => 'scraping', 'connector_driver' => 'unimed_rda']);

        ConvenioCredencial::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'convenio_id' => $convenio->id,
        ], [
            'driver' => 'unimed_rda',
            'credenciais' => [
                'login' => 'operador-unimed',
                'password' => 'senha-unimed',
                'base_url' => 'https://portal.unimed.test',
            ],
            'ativo' => true,
        ]);

        $especialidade = Especialidade::query()->where('nome', 'Terapia ABA')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();

        $guia = Guia::query()->create([
            'tenant_id' => $tenant->id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'FINAL-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::APPROVED,
            'sessoes_autorizadas' => $autorizadas,
            'data_solicitacao' => today(),
            'senha' => $senha,
            'validade_senha' => $senha ? '2026-12-31' : null,
        ]);

        for ($i = 0; $i < $sessoes; $i++) {
            $this->gravarSessao($guia, today()->subDays($i + 1)->toDateString(), '08:00');
        }

        return $guia->load(['convenio', 'especialidade', 'paciente']);
    }
}

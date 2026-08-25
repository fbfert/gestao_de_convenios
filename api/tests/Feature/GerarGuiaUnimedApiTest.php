<?php

namespace Tests\Feature;

use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Jobs\EnfileirarConsultasUnimedDueJob;
use App\Models\AuditLog;
use App\Models\AutomacaoExecucao;
use App\Models\AutomacaoEvento;
use App\Models\Convenio;
use App\Models\ConvenioEspecialidadeMapeamento;
use App\Models\ConvenioProfissionalMapeamento;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\SolicitacaoItem;
use App\Models\UnimedRdaCredential;
use App\Models\User;
use App\Services\Automation\AutomacaoService;
use App\Services\Automation\CapturarSenhaValidadeUnimedService;
use App\Services\Automation\ConsultarStatusUnimedService;
use App\Services\Automation\FakeUnimedWorkerClient;
use App\Services\Automation\GerarGuiaUnimedService;
use App\Services\Automation\UnimedWorkerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GerarGuiaUnimedApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_endpoint_enfileira_envio_manual_sem_criar_guia_local(): void
    {
        Queue::fake();
        $this->autenticar();
        $item = $this->prepararItemUnimed();

        $this->postJson("/api/solicitacao-itens/{$item->id}/enviar-unimed")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.operacao', 'gerar_guia')
            ->assertJsonPath('data.solicitacao_item_id', $item->id);

        $this->assertSame(1, AutomacaoExecucao::query()->where('operacao', 'gerar_guia')->count());
        $this->assertSame(0, Guia::query()->where('solicitacao_item_id', $item->id)->count());
        Queue::assertPushed(ExecutarAutomacaoUnimedJob::class);
    }

    public function test_bloqueia_item_inelegivel_sem_pedido_medico(): void
    {
        Queue::fake();
        $this->autenticar();
        $item = $this->prepararItemUnimed(comPedidoMedico: false);

        $this->postJson("/api/solicitacao-itens/{$item->id}/enviar-unimed")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item']);

        Queue::assertNothingPushed();
    }

    public function test_job_com_sucesso_cria_guia_local_vinculada_ao_item_e_execucao(): void
    {
        $item = $this->prepararItemUnimed();
        $execucao = app(AutomacaoService::class)->enfileirar(
            $item->tenant_id,
            'gerar_guia',
            $item,
            payload: ['solicitacao_item_id' => $item->id],
        );
        $worker = new FakeUnimedWorkerClient(['status' => 'succeeded', 'numero_guia' => 'UNI-12345']);
        $this->app->instance(UnimedWorkerClient::class, $worker);

        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(\App\Services\Automation\ConsultarStatusUnimedService::class),
            app(\App\Services\Automation\UnimedCircuitBreakerService::class),
        );

        $guia = Guia::query()->where('solicitacao_item_id', $item->id)->firstOrFail();

        $this->assertSame('UNI-12345', $guia->numero_guia);
        $this->assertSame($execucao->id, $guia->automacao_execucao_id);
        $this->assertSame('guia_generated', $item->refresh()->status_operacional);
        $this->assertSame('senha-unimed', $worker->calls[0]['payload']['credential']['password']);

        // A guia nasce 'under_review' (a operadora ainda não decidiu) — a
        // Solicitação (item único) tem que refletir isso como 'guia_gerada',
        // não mais 'ready_for_automation' (senão a tela mostra "pronta pra
        // automatizar" com a guia já gerada de verdade).
        $this->assertSame('under_review', $guia->status);
        $this->assertSame('guia_gerada', $item->solicitacao->refresh()->status);
    }

    public function test_payload_para_worker_inclui_medico_mapeamentos_e_documentos(): void
    {
        Queue::fake();
        $item = $this->prepararItemUnimed();

        $execucao = app(GerarGuiaUnimedService::class)->enviar($item);
        $payload = app(GerarGuiaUnimedService::class)->payloadParaWorker($execucao);

        $this->assertSame('Dr. Carlos Almeida', $payload['medico']['nome']);
        $this->assertNotEmpty($payload['medico']['crm']);
        $this->assertNotEmpty($payload['codigo_procedimento']);
        $this->assertNotEmpty($payload['codigo_profissional_operadora']);
        $this->assertSame('pedido.pdf', $payload['pedido_medico']['nome_original']);
        $this->assertSame('pedidos-medicos/teste.pdf', $payload['pedido_medico']['path']);
        $this->assertArrayHasKey('local_path', $payload['pedido_medico']);
    }

    public function test_anexos_do_worker_ignoram_documentos_de_outra_especialidade(): void
    {
        Queue::fake();
        $item = $this->prepararItemUnimed();
        $solicitacao = $item->solicitacao;
        $outroItem = $solicitacao->itens()->create([
            'tenant_id' => $item->tenant_id,
            'especialidade_id' => $item->especialidade_id,
            'profissional_id' => $item->profissional_id,
            'quantidade' => 10,
            'status_operacional' => 'pending',
        ]);

        $solicitacao->documentos()->create([
            'tenant_id' => $item->tenant_id,
            'solicitacao_item_id' => null,
            'tipo' => 'laudo_medico',
            'nome_original' => 'laudo-geral.pdf',
            'mime' => 'application/pdf',
            'path' => 'solicitacoes/laudo-geral.pdf',
        ]);
        $solicitacao->documentos()->create([
            'tenant_id' => $item->tenant_id,
            'solicitacao_item_id' => $item->id,
            'tipo' => 'plano_individualizado',
            'nome_original' => 'plano-do-item.pdf',
            'mime' => 'application/pdf',
            'path' => 'solicitacoes/plano-do-item.pdf',
        ]);
        $solicitacao->documentos()->create([
            'tenant_id' => $item->tenant_id,
            'solicitacao_item_id' => $outroItem->id,
            'tipo' => 'plano_individualizado',
            'nome_original' => 'plano-de-outra-especialidade.pdf',
            'mime' => 'application/pdf',
            'path' => 'solicitacoes/plano-de-outra.pdf',
        ]);

        $execucao = app(GerarGuiaUnimedService::class)->enviar($item->fresh());
        $payload = app(GerarGuiaUnimedService::class)->payloadParaWorker($execucao);
        $nomes = array_column($payload['anexos'], 'nome_original');

        sort($nomes);
        $this->assertSame(['laudo-geral.pdf', 'plano-do-item.pdf'], $nomes);
    }

    public function test_resultado_needs_verification_cria_guia_sem_numero(): void
    {
        $item = $this->prepararItemUnimed();
        $execucao = app(AutomacaoService::class)->enfileirar($item->tenant_id, 'gerar_guia', $item);
        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'succeeded',
            'guia_status' => 'needs_verification',
            'numero_guia' => null,
            'unimed_status' => 'Restrição Administrativa',
        ]));

        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(\App\Services\Automation\ConsultarStatusUnimedService::class),
            app(\App\Services\Automation\UnimedCircuitBreakerService::class),
        );

        $guia = Guia::query()->where('solicitacao_item_id', $item->id)->firstOrFail();

        $this->assertNull($guia->numero_guia);
        $this->assertSame('needs_verification', $guia->status);
        $this->assertSame('Restrição Administrativa', $guia->unimed_status);
    }

    public function test_detalhe_da_guia_retorna_item_e_resumo_da_execucao_sem_segredos(): void
    {
        $this->autenticar();
        $item = $this->prepararItemUnimed();
        $execucao = app(AutomacaoService::class)->enfileirar(
            $item->tenant_id,
            'gerar_guia',
            $item,
            payload: ['password' => 'senha-redigida'],
        );
        $this->app->instance(
            UnimedWorkerClient::class,
            new FakeUnimedWorkerClient(['status' => 'succeeded', 'numero_guia' => 'UNI-DETALHE']),
        );

        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(\App\Services\Automation\ConsultarStatusUnimedService::class),
            app(\App\Services\Automation\UnimedCircuitBreakerService::class),
        );

        $guia = Guia::query()->where('solicitacao_item_id', $item->id)->firstOrFail();

        $this->getJson("/api/guias/{$guia->id}")
            ->assertOk()
            ->assertJsonPath('data.solicitacao_item.id', $item->id)
            ->assertJsonPath('data.automacao_execucao.id', $execucao->id)
            ->assertJsonPath('data.automacao_execucao.status', 'succeeded')
            ->assertJsonMissingPath('data.automacao_execucao.payload.password')
            ->assertJsonMissingPath('data.automacao_execucao.credential');
    }

    public function test_bloqueia_criacao_manual_de_guia_para_convenio_unimed_automatizado(): void
    {
        $this->autenticar();
        $item = $this->prepararItemUnimed();
        $solicitacao = $item->solicitacao;

        $this->postJson('/api/guias', [
            'solicitacao_id' => null,
            'solicitacao_item_id' => null,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $item->profissional_id,
            'especialidade_id' => $item->especialidade_id,
            'numero_guia' => 'MANUAL-UNIMED',
            'tipo_terapia' => 'especializada',
            'data_solicitacao' => today()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['convenio_id']);
    }

    public function test_resultado_incerto_nao_cria_guia_e_bloqueia_reenvio_cego(): void
    {
        $this->autenticar();
        $item = $this->prepararItemUnimed();
        $execucao = app(AutomacaoService::class)->enfileirar($item->tenant_id, 'gerar_guia', $item);
        $this->app->instance(
            UnimedWorkerClient::class,
            new FakeUnimedWorkerClient(['status' => 'uncertain', 'mensagem' => 'timeout apos submit']),
        );

        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(\App\Services\Automation\ConsultarStatusUnimedService::class),
            app(\App\Services\Automation\UnimedCircuitBreakerService::class),
        );

        $this->assertSame('uncertain', $execucao->refresh()->status);
        $this->assertSame('uncertain', $item->refresh()->status_operacional);
        $this->assertSame(0, Guia::query()->where('solicitacao_item_id', $item->id)->count());

        $this->postJson("/api/solicitacao-itens/{$item->id}/enviar-unimed")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item']);
    }

    public function test_endpoint_enfileira_consulta_manual_de_status_unimed(): void
    {
        Queue::fake();
        $this->autenticar();
        $guia = $this->criarGuiaUnimedPendente();

        $this->postJson("/api/guias/{$guia->id}/consultar-unimed")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.operacao', 'consult_status_batch')
            ->assertJsonPath('data.guia_id', $guia->id);

        $this->assertNotNull($guia->refresh()->unimed_next_check_at);
        Queue::assertPushed(ExecutarAutomacaoUnimedJob::class);
    }

    public function test_consulta_status_atualiza_status_sem_capturar_senha(): void
    {
        $guia = $this->criarGuiaUnimedPendente();
        $execucao = app(AutomacaoService::class)->enfileirar(
            $guia->tenant_id,
            'consult_status_batch',
            guia: $guia,
            payload: ['numero_guia' => $guia->numero_guia],
        );
        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'succeeded',
            'guia_status' => 'approved',
            'unimed_status' => 'Autorizado',
            'conclusivo' => true,
        ]));

        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(ConsultarStatusUnimedService::class),
            app(\App\Services\Automation\UnimedCircuitBreakerService::class),
        );

        $guia->refresh();

        $this->assertSame('approved', $guia->status);
        $this->assertSame('Autorizado', $guia->unimed_status);
        $this->assertNull($guia->senha);
        $this->assertNotNull($guia->unimed_last_checked_at);
        $this->assertNotNull($guia->unimed_next_check_at);
    }

    public function test_consulta_status_atualiza_sessoes_autorizadas_quando_o_portal_informa(): void
    {
        $guia = $this->criarGuiaUnimedPendente();
        $guia->forceFill(['sessoes_solicitadas' => 10, 'sessoes_autorizadas' => 10])->save();

        $consultar = function (array $resultado) use ($guia) {
            $execucao = app(AutomacaoService::class)->enfileirar(
                $guia->tenant_id,
                'consult_status_batch',
                guia: $guia,
                payload: ['numero_guia' => $guia->numero_guia],
            );
            $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient($resultado));

            (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
                app(AutomacaoService::class),
                app(UnimedWorkerClient::class),
                app(GerarGuiaUnimedService::class),
                app(ConsultarStatusUnimedService::class),
                app(\App\Services\Automation\UnimedCircuitBreakerService::class),
            );

            $execucao->forceFill(['idempotency_key' => 'reconsulta-'.uniqid()])->save();
        };

        $consultar([
            'status' => 'succeeded',
            'guia_status' => 'approved',
            'unimed_status' => 'Autorizado',
            'conclusivo' => true,
            'sessoes_solicitadas' => 10,
            'sessoes_autorizadas' => 6,
        ]);

        $this->assertSame(6, $guia->refresh()->sessoes_autorizadas);

        // Portal sem a informação: mantém o que já estava, não zera.
        $consultar([
            'status' => 'succeeded',
            'guia_status' => 'approved',
            'unimed_status' => 'Autorizado',
            'conclusivo' => true,
        ]);

        $guia->refresh();
        $this->assertSame(6, $guia->sessoes_autorizadas);
        $this->assertSame(10, $guia->sessoes_solicitadas);
    }

    public function test_captura_senha_validade_preserva_valores_existentes_quando_worker_retorna_vazio(): void
    {
        $guia = $this->criarGuiaUnimedPendente([
            'status' => 'approved',
            'senha' => 'SENHA-ANTIGA',
            'validade_senha' => null,
        ]);
        $execucao = app(AutomacaoService::class)->enfileirar(
            $guia->tenant_id,
            'capture_authorization_data_batch',
            guia: $guia,
            payload: ['numero_guia' => $guia->numero_guia],
        );
        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'succeeded',
            'senha' => '',
            'validade_senha' => today()->addDays(30)->toDateString(),
        ]));

        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(ConsultarStatusUnimedService::class),
            app(\App\Services\Automation\UnimedCircuitBreakerService::class),
        );

        $guia->refresh();

        $this->assertSame('approved', $guia->status);
        $this->assertSame('SENHA-ANTIGA', $guia->senha);
        $this->assertSame(today()->addDays(30)->toDateString(), $guia->validade_senha->toDateString());
    }

    public function test_consulta_sem_dados_reagenda_sem_falha_estrutural(): void
    {
        $guia = $this->criarGuiaUnimedPendente();
        $execucao = app(AutomacaoService::class)->enfileirar($guia->tenant_id, 'consultar_status', guia: $guia);
        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'succeeded',
            'portal_status' => 'pending',
            'conclusivo' => false,
        ]));

        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(ConsultarStatusUnimedService::class),
            app(\App\Services\Automation\UnimedCircuitBreakerService::class),
        );

        $guia->refresh();

        $this->assertSame('under_review', $guia->status);
        $this->assertNull($guia->unimed_status);
        $this->assertNull($guia->unimed_last_checked_at);
        $this->assertDatabaseHas('automacao_eventos', [
            'automacao_execucao_id' => $execucao->id,
            'tipo' => 'dados_indisponiveis',
        ]);
        $this->assertSame(0, AutomacaoEvento::query()->where('tipo', 'PORTAL_STRUCTURE_CHANGED')->count());
    }

    public function test_scheduler_leve_enfileira_apenas_guias_due(): void
    {
        Queue::fake();
        Guia::query()->delete();
        $due = $this->criarGuiaUnimedPendente(['unimed_next_check_at' => now()->subMinute()]);
        $future = $this->criarGuiaUnimedPendente(['unimed_next_check_at' => now()->addDay()]);

        (new EnfileirarConsultasUnimedDueJob())->handle(
            app(ConsultarStatusUnimedService::class),
            app(CapturarSenhaValidadeUnimedService::class),
        );

        $this->assertSame(1, AutomacaoExecucao::query()->where('operacao', 'consult_status_batch')->count());
        $this->assertDatabaseHas('automacao_execucoes', [
            'guia_id' => $due->id,
            'operacao' => 'consult_status_batch',
        ]);
        $this->assertDatabaseMissing('automacao_execucoes', [
            'guia_id' => $future->id,
            'operacao' => 'consult_status_batch',
        ]);
        Queue::assertPushed(ExecutarAutomacaoUnimedJob::class, 1);
    }

    public function test_circuit_breaker_pausa_conector_em_falha_estrutural(): void
    {
        $item = $this->prepararItemUnimed();
        $execucao = app(AutomacaoService::class)->enfileirar($item->tenant_id, 'gerar_guia', $item);
        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'failed',
            'error_code' => 'PORTAL_STRUCTURE_CHANGED',
            'message' => 'layout alterado',
        ]));

        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(ConsultarStatusUnimedService::class),
            app(\App\Services\Automation\UnimedCircuitBreakerService::class),
        );

        $credential = UnimedRdaCredential::query()->where('tenant_id', $item->tenant_id)->firstOrFail();

        $this->assertFalse($credential->ativo);
        $this->assertSame('PORTAL_STRUCTURE_CHANGED', $credential->automation_paused_reason);
        $this->assertNotNull($credential->automation_paused_at);

        // O worker devolveu a falha "de forma controlada" (sem lançar exceção) —
        // erro_codigo/erro_mensagem têm que vir preenchidos mesmo assim, senão a
        // tela de Automações mostra "-" onde deveria mostrar o motivo real.
        $this->assertSame('PORTAL_STRUCTURE_CHANGED', $execucao->refresh()->erro_codigo);
        $this->assertSame('layout alterado', $execucao->erro_mensagem);
        $this->assertSame(1, AuditLog::query()->where('acao', 'unimed_rda.automation_paused')->count());
    }

    public function test_circuit_breaker_pausa_conector_para_novos_erros_estruturais(): void
    {
        foreach ([
            'LOGIN_ERROR',
            'SESSION_LOST_UNRECOVERABLE',
            'WORKER_INTERNAL_FATAL',
            'CONFIGURATION_INVALID_GLOBAL',
        ] as $code) {
            $item = $this->prepararItemUnimed();
            $execucao = app(AutomacaoService::class)->enfileirar($item->tenant_id, 'gerar_guia', $item);
            $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
                'status' => 'failed',
                'error_code' => $code,
                'message' => 'falha estrutural',
            ]));

            (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
                app(AutomacaoService::class),
                app(UnimedWorkerClient::class),
                app(GerarGuiaUnimedService::class),
                app(ConsultarStatusUnimedService::class),
                app(\App\Services\Automation\UnimedCircuitBreakerService::class),
            );

            $credential = UnimedRdaCredential::query()->where('tenant_id', $item->tenant_id)->firstOrFail();
            $this->assertFalse($credential->ativo);
            $this->assertSame($code, $credential->automation_paused_reason);

            AutomacaoExecucao::query()->delete();
            AuditLog::query()->where('acao', 'unimed_rda.automation_paused')->delete();
            $credential->forceFill([
                'ativo' => true,
                'automation_paused_at' => null,
                'automation_paused_reason' => null,
            ])->save();
        }
    }

    public function test_portal_unavailable_so_pausa_apos_retry_limitado(): void
    {
        $item = $this->prepararItemUnimed();
        $execucao = app(AutomacaoService::class)->enfileirar($item->tenant_id, 'gerar_guia', $item);
        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'failed',
            'error_code' => 'PORTAL_UNAVAILABLE',
            'attempt' => 1,
            'max_attempts' => 3,
        ]));

        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(ConsultarStatusUnimedService::class),
            app(\App\Services\Automation\UnimedCircuitBreakerService::class),
        );

        $credential = UnimedRdaCredential::query()->where('tenant_id', $item->tenant_id)->firstOrFail();
        $this->assertTrue($credential->ativo);

        AutomacaoExecucao::query()->delete();
        $execucao = app(AutomacaoService::class)->enfileirar($item->tenant_id, 'gerar_guia', $item);
        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'failed',
            'error_code' => 'PORTAL_UNAVAILABLE',
            'attempt' => 3,
            'max_attempts' => 3,
        ]));

        (new ExecutarAutomacaoUnimedJob($execucao->id))->handle(
            app(AutomacaoService::class),
            app(UnimedWorkerClient::class),
            app(GerarGuiaUnimedService::class),
            app(ConsultarStatusUnimedService::class),
            app(\App\Services\Automation\UnimedCircuitBreakerService::class),
        );

        $credential->refresh();
        $this->assertFalse($credential->ativo);
        $this->assertSame('PORTAL_UNAVAILABLE', $credential->automation_paused_reason);
    }

    public function test_retencao_de_evidencias_suporta_dry_run_e_preserva_documentos_medicos(): void
    {
        Storage::fake('local');
        $execucao = AutomacaoExecucao::query()->create([
            'tenant_id' => User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail()->tenant_id,
            'operacao' => 'consultar_status',
            'status' => 'failed',
            'idempotency_key' => uniqid('retencao-', true),
            'queued_at' => now(),
        ]);
        Storage::disk('local')->put('automacoes/evidencias/falha.html', 'html');
        Storage::disk('local')->put('pedidos-medicos/solicitacoes/pedido.pdf', 'pdf');
        AutomacaoEvento::query()->create([
            'tenant_id' => $execucao->tenant_id,
            'automacao_execucao_id' => $execucao->id,
            'tipo' => 'failed',
            'status' => 'failed',
            'payload' => [],
            'evidencias' => [
                'html' => 'automacoes/evidencias/falha.html',
                'pedido' => 'pedidos-medicos/solicitacoes/pedido.pdf',
            ],
            'registrado_em' => now(),
        ]);

        Artisan::call('automacao:limpar-evidencias', ['--dry-run' => true, '--days' => 0]);
        Storage::disk('local')->assertExists('automacoes/evidencias/falha.html');
        Storage::disk('local')->assertExists('pedidos-medicos/solicitacoes/pedido.pdf');

        Artisan::call('automacao:limpar-evidencias', ['--days' => 0]);
        Storage::disk('local')->assertMissing('automacoes/evidencias/falha.html');
        Storage::disk('local')->assertExists('pedidos-medicos/solicitacoes/pedido.pdf');
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function prepararItemUnimed(bool $comPedidoMedico = true): SolicitacaoItem
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $convenio->update([
            'connector_type' => 'scraping',
            'connector_driver' => 'unimed_rda',
        ]);
        $tenantId = (int) $convenio->tenant_id;

        UnimedRdaCredential::query()->updateOrCreate([
            'tenant_id' => $tenantId,
        ], [
            'login' => 'operador-unimed',
            'password' => 'senha-unimed',
            'base_url' => 'https://portal.unimed.test',
            'ativo' => true,
        ]);

        $paciente = Paciente::query()->where('tenant_id', $tenantId)->where('convenio_id', $convenio->id)->firstOrFail();
        $especialidade = Especialidade::query()->where('tenant_id', $tenantId)->firstOrFail();
        $profissional = Profissional::query()->where('tenant_id', $tenantId)->where('especialidade_id', $especialidade->id)->firstOrFail();
        $medico = Medico::query()->where('tenant_id', $tenantId)->firstOrFail();

        ConvenioEspecialidadeMapeamento::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'convenio_id' => $convenio->id,
            'especialidade_id' => $especialidade->id,
        ], [
            'codigo_procedimento' => '50000470',
            'descricao_operadora' => 'Terapia especializada',
            'quantidade_padrao' => 10,
            'usa_descricao_generica' => false,
            'valor_generico' => null,
            'ativo' => true,
        ]);

        ConvenioProfissionalMapeamento::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'convenio_id' => $convenio->id,
            'profissional_id' => $profissional->id,
        ], [
            'codigo_operadora' => '1234',
            'nome_operadora' => $profissional->nome,
            'ativo' => true,
        ]);

        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $tenantId,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_id' => $medico->id,
            'status' => 'ready_for_automation',
            'solicitado_em' => today(),
            'observacoes' => null,
            'pedido_medico_path' => $comPedidoMedico ? 'pedidos-medicos/teste.pdf' : null,
            'pedido_medico_nome_original' => $comPedidoMedico ? 'pedido.pdf' : null,
            'pedido_medico_mime' => $comPedidoMedico ? 'application/pdf' : null,
        ]);

        if ($comPedidoMedico) {
            $solicitacao->documentos()->create([
                'tenant_id' => $tenantId,
                'solicitacao_item_id' => null,
                'tipo' => 'pedido_medico',
                'nome_original' => 'pedido.pdf',
                'mime' => 'application/pdf',
                'path' => 'pedidos-medicos/teste.pdf',
            ]);
        }

        return $solicitacao->itens()->create([
            'tenant_id' => $tenantId,
            'especialidade_id' => $especialidade->id,
            'profissional_id' => $profissional->id,
            'quantidade' => 10,
            'status_operacional' => 'pending',
        ]);
    }

    private function criarGuiaUnimedPendente(array $overrides = []): Guia
    {
        $item = $this->prepararItemUnimed();
        $solicitacao = $item->solicitacao;

        return Guia::query()->create(array_merge([
            'tenant_id' => $item->tenant_id,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $item->id,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $item->profissional_id,
            'especialidade_id' => $item->especialidade_id,
            'numero_guia' => 'UNI-STATUS-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ], $overrides));
    }
}

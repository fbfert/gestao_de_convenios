<?php

namespace Tests\Feature;

use App\Jobs\EnfileirarConsultasUnimedDueJob;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\ConfiguracaoGlobal;
use App\Models\Convenio;
use App\Models\ConvenioEspecialidadeMapeamento;
use App\Models\ConvenioProfissionalMapeamento;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\PacienteArquivo;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\SolicitacaoItem;
use App\Models\UnimedRdaCredential;
use App\Models\User;
use App\Services\Automation\AutomacaoService;
use App\Services\Automation\CapturarSenhaValidadeUnimedService;
use App\Services\Automation\ConfirmarGuiaIncertaUnimedService;
use App\Services\Automation\ConsultarStatusUnimedService;
use App\Services\Automation\FakeUnimedWorkerClient;
use App\Services\Automation\GerarGuiaUnimedService;
use App\Services\Automation\UnimedCircuitBreakerService;
use App\Services\Automation\UnimedWorkerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfirmarGuiaIncertaUnimedApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_bloqueia_verificacao_quando_item_nao_esta_incerto(): void
    {
        $this->autenticar();
        $item = $this->prepararItemUnimed();

        $this->postJson("/api/solicitacao-itens/{$item->id}/verificar-andamento")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item']);
    }

    public function test_endpoint_enfileira_confirmacao_para_item_incerto(): void
    {
        Queue::fake();
        $this->autenticar();
        [$item] = $this->prepararItemIncerto();

        $this->postJson("/api/solicitacao-itens/{$item->id}/verificar-andamento")
            ->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.operacao', 'confirmar_guia_incerta');

        Queue::assertPushed(ExecutarAutomacaoUnimedJob::class, 1);
    }

    public function test_guia_encontrada_cria_guia_local_e_resolve_execucao_original(): void
    {
        $this->autenticar();
        [$item, $execucaoIncerta] = $this->prepararItemIncerto();
        $execucaoConfirmacao = $this->enfileirarConfirmacao($item, $execucaoIncerta);

        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'succeeded',
            'encontrada' => true,
            'numero_guia' => 'UNI-777',
            'guia_status' => 'approved',
            'unimed_status' => 'Autorizado',
            'senha' => '1234567',
            'validade_senha' => '2026-12-31',
        ]));

        $this->executarJob($execucaoConfirmacao);

        $this->assertSame('succeeded', $execucaoConfirmacao->refresh()->status);
        $this->assertSame('succeeded', $execucaoIncerta->refresh()->status);
        $this->assertSame('guia_generated', $item->refresh()->status_operacional);

        $guia = Guia::query()->where('solicitacao_item_id', $item->id)->firstOrFail();
        $this->assertSame('UNI-777', $guia->numero_guia);
        $this->assertSame('approved', $guia->status);
        $this->assertSame('1234567', $guia->senha);

        // Botao manual continua bloqueado: agora o item tem Guia local.
        $this->postJson("/api/solicitacao-itens/{$item->id}/enviar-unimed")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item']);
    }

    public function test_guia_nao_encontrada_libera_reenvio_manual(): void
    {
        $this->autenticar();
        [$item, $execucaoIncerta] = $this->prepararItemIncerto();
        $execucaoConfirmacao = $this->enfileirarConfirmacao($item, $execucaoIncerta);

        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'succeeded',
            'encontrada' => false,
        ]));

        $this->executarJob($execucaoConfirmacao);

        $this->assertSame('failed', $execucaoIncerta->refresh()->status);
        $this->assertSame('CONFIRMADO_NAO_CRIADA', $execucaoIncerta->erro_codigo);
        $this->assertSame('pending', $item->refresh()->status_operacional);
        $this->assertSame(0, Guia::query()->where('solicitacao_item_id', $item->id)->count());

        // Botao manual destrava de verdade.
        Queue::fake();
        $this->postJson("/api/solicitacao-itens/{$item->id}/enviar-unimed")->assertAccepted();
    }

    public function test_falha_tecnica_na_confirmacao_nao_mexe_no_item_e_reagenda(): void
    {
        $this->autenticar();
        [$item, $execucaoIncerta] = $this->prepararItemIncerto();
        $execucaoConfirmacao = $this->enfileirarConfirmacao($item, $execucaoIncerta);

        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient([
            'status' => 'failed',
            'error_code' => 'LOGIN_ERROR',
            'message' => 'Credencial recusada',
        ]));

        $this->executarJob($execucaoConfirmacao);

        $this->assertSame('failed', $execucaoConfirmacao->refresh()->status);
        $this->assertSame('uncertain', $execucaoIncerta->refresh()->status);
        $this->assertSame('uncertain', $item->refresh()->status_operacional);
        $this->assertSame(0, Guia::query()->where('solicitacao_item_id', $item->id)->count());
    }

    public function test_scheduler_respeita_janela_de_horario_configurada(): void
    {
        Queue::fake();
        [$item] = $this->prepararItemIncerto();

        ConfiguracaoGlobal::doTenant($item->tenant_id)->update([
            'unimed_verificacao_incerta_horario_inicio' => '02:00',
            'unimed_verificacao_incerta_horario_fim' => '03:00',
            'unimed_verificacao_incerta_intervalo_minutos' => 60,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-27 10:00:00'));
        $this->rodarScheduler();
        $this->assertSame(0, AutomacaoExecucao::query()->where('operacao', ConfirmarGuiaIncertaUnimedService::OPERATION)->count());
        $this->assertNull($item->refresh()->unimed_verificacao_next_check_at);

        Carbon::setTestNow(Carbon::parse('2026-08-27 02:30:00'));
        $this->rodarScheduler();
        $this->assertSame(1, AutomacaoExecucao::query()->where('operacao', ConfirmarGuiaIncertaUnimedService::OPERATION)->count());
        $this->assertNotNull($item->refresh()->unimed_verificacao_next_check_at);

        Carbon::setTestNow();
    }

    private function rodarScheduler(): void
    {
        (new EnfileirarConsultasUnimedDueJob())->handle(
            app(ConsultarStatusUnimedService::class),
            app(CapturarSenhaValidadeUnimedService::class),
            app(ConfirmarGuiaIncertaUnimedService::class),
        );
    }

    private function enfileirarConfirmacao(SolicitacaoItem $item, AutomacaoExecucao $execucaoIncerta): AutomacaoExecucao
    {
        return app(AutomacaoService::class)->enfileirar(
            $item->tenant_id,
            ConfirmarGuiaIncertaUnimedService::OPERATION,
            $item,
            null,
            [],
            $execucaoIncerta,
        );
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

    /** @return array{0: SolicitacaoItem, 1: AutomacaoExecucao} */
    private function prepararItemIncerto(): array
    {
        $item = $this->prepararItemUnimed();
        $execucao = app(AutomacaoService::class)->enfileirar($item->tenant_id, 'gerar_guia', $item);
        $this->app->instance(
            UnimedWorkerClient::class,
            new FakeUnimedWorkerClient([
                'status' => 'uncertain',
                'error_code' => 'UNCERTAIN_AFTER_SUBMIT',
                'message' => 'timeout apos submit',
            ]),
        );

        $this->executarJob($execucao);

        return [$item->refresh(), $execucao->refresh()];
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
        ]);

        if ($comPedidoMedico) {
            $arquivo = PacienteArquivo::query()->create([
                'tenant_id' => $tenantId,
                'paciente_id' => $paciente->id,
                'tipo' => 'pedido_medico',
                'nome_original' => 'pedido.pdf',
                'mime' => 'application/pdf',
                'path' => 'pedidos-medicos/teste.pdf',
            ]);

            $solicitacao->documentos()->create([
                'tenant_id' => $tenantId,
                'solicitacao_item_id' => null,
                'paciente_arquivo_id' => $arquivo->id,
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
}

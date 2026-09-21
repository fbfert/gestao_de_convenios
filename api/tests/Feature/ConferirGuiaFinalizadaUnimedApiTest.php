<?php

namespace Tests\Feature;

use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\Convenio;
use App\Models\ConvenioCredencial;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Automation\AutomacaoService;
use App\Services\Automation\ConferirGuiaFinalizadaUnimedService;
use App\Services\Automation\ConsultarStatusUnimedService;
use App\Services\Automation\FakeUnimedWorkerClient;
use App\Services\Automation\GerarGuiaUnimedService;
use App\Services\Automation\UnimedCircuitBreakerService;
use App\Services\Automation\UnimedWorkerClient;
use App\Support\GuiaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A conferência de guias já finalizadas na operadora — ver a spec
 * `conferencia-de-guias-finalizadas`.
 *
 * O worker tem cobertura contra a fixture. Aqui o que importa é o que a API
 * decide: quem entra no lote, e o que cada desfecho grava na guia.
 */
class ConferirGuiaFinalizadaUnimedApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // ------------------------------------------------------------ elegibilidade

    public function test_lote_pega_guia_unimed_ainda_nao_conferida(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed();

        $elegiveis = $this->service()->elegiveisParaLote((int) $guia->tenant_id);

        $this->assertTrue($elegiveis->contains('id', $guia->id));
    }

    public function test_lote_ignora_guia_ja_conferida(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed();
        $guia->forceFill(['conferida_na_operadora_em' => now()])->save();

        $this->assertFalse(
            $this->service()->elegiveisParaLote((int) $guia->tenant_id)->contains('id', $guia->id),
        );
    }

    public function test_lote_ignora_guia_sem_numero_da_operadora(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed();
        $guia->forceFill(['numero_guia' => GuiaStatus::PREFIXO_NUMERO_PLACEHOLDER.'9-9'])->save();

        $this->assertFalse(
            $this->service()->elegiveisParaLote((int) $guia->tenant_id)->contains('id', $guia->id),
        );
    }

    public function test_lote_ignora_guia_de_convenio_sem_automacao(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed();
        $guia->convenio->update(['connector_driver' => null, 'connector_type' => 'manual']);

        $this->assertFalse(
            $this->service()->elegiveisParaLote((int) $guia->tenant_id)->contains('id', $guia->id),
        );
    }

    /**
     * Guia já finalizada pelo fluxo normal ENTRA: saber que a operadora
     * concorda é informação legítima, e a marca não atrapalha nada nela.
     */
    public function test_lote_inclui_guia_ja_finalizada_pelo_fluxo_normal(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed(status: GuiaStatus::FINALIZED);

        $this->assertTrue(
            $this->service()->elegiveisParaLote((int) $guia->tenant_id)->contains('id', $guia->id),
        );
    }

    /**
     * A saída para um lote que correu errado.
     *
     * Sem isso, um lote que marcasse dezenas de guias como "conferida, não
     * estava lá" — por um filtro do portal que não limpou, por exemplo — as
     * tiraria do lote seguinte para sempre, e desfazer custaria uma
     * conferência avulsa por guia.
     */
    public function test_lote_pode_incluir_as_ja_conferidas(): void
    {
        $this->autenticar();
        $this->ignorarGuiasDaSemente();
        $conferida = $this->guiaUnimed();
        $conferida->forceFill(['conferida_na_operadora_em' => now()->subDay()])->save();
        $nova = $this->guiaUnimed();

        $padrao = $this->service()->elegiveisParaLote((int) $nova->tenant_id)->pluck('id')->all();
        $this->assertContains($nova->id, $padrao);
        $this->assertNotContains($conferida->id, $padrao, 'o lote padrão pula quem já foi conferida');

        $todas = $this->service()
            ->elegiveisParaLote((int) $nova->tenant_id, incluirJaConferidas: true)
            ->pluck('id')
            ->all();
        $this->assertContains($nova->id, $todas);
        $this->assertContains($conferida->id, $todas, 'reconferir tem de trazer quem já foi conferida');
    }

    public function test_disparo_do_lote_com_reconferencia_leva_as_ja_conferidas(): void
    {
        Queue::fake();
        $this->autenticar();
        $this->ignorarGuiasDaSemente();
        $conferida = $this->guiaUnimed();
        $conferida->forceFill(['conferida_na_operadora_em' => now()->subDay()])->save();

        // Sem o pedido, não há o que conferir.
        $this->postJson('/api/guias/conferir-finalizadas-unimed')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['guias']);

        // Com o pedido, ela volta para o lote.
        $this->postJson('/api/guias/conferir-finalizadas-unimed', ['incluir_ja_conferidas' => true])
            ->assertAccepted();

        $execucao = AutomacaoExecucao::query()->latest('id')->firstOrFail();
        $this->assertContains(
            $conferida->id,
            array_column($execucao->payload['guias'], 'guia_id'),
            'a guia já conferida tem de estar no payload da reconferência',
        );
    }

    /**
     * Com dois convênios automatizados, o lote cobre um e precisa DIZER que o
     * outro ficou — senão metade do passivo fica para trás em silêncio.
     */
    public function test_lote_nomeia_o_convenio_e_avisa_o_que_sobrou(): void
    {
        Queue::fake();
        $this->autenticar();
        $this->ignorarGuiasDaSemente();

        $daUnimed = $this->guiaUnimed();
        $this->guiaDeOutroConvenioAutomatizado();

        $resposta = $this->postJson('/api/guias/conferir-finalizadas-unimed')->assertAccepted();

        $this->assertSame(1, $resposta->json('data.total_guias'));
        $this->assertNotNull($resposta->json('data.convenio'));
        $this->assertSame(1, $resposta->json('data.restantes_de_outros_convenios'));
        $this->assertSame($daUnimed->convenio->nome, $resposta->json('data.convenio'));
    }

    public function test_com_um_convenio_so_nao_ha_o_que_avisar(): void
    {
        Queue::fake();
        $this->autenticar();
        $this->ignorarGuiasDaSemente();
        $this->guiaUnimed();

        $this->postJson('/api/guias/conferir-finalizadas-unimed')
            ->assertAccepted()
            ->assertJsonPath('data.restantes_de_outros_convenios', 0);
    }

    public function test_lote_sem_guia_elegivel_e_recusado(): void
    {
        $this->autenticar();
        $this->ignorarGuiasDaSemente();
        $guia = $this->guiaUnimed();
        $guia->forceFill(['conferida_na_operadora_em' => now()])->save();

        $this->postJson('/api/guias/conferir-finalizadas-unimed')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['guias']);
    }

    // ------------------------------------------------------------------ disparo

    public function test_disparo_avulso_enfileira_a_conferencia(): void
    {
        Queue::fake();
        $this->autenticar();
        $guia = $this->guiaUnimed();

        $this->postJson("/api/guias/{$guia->id}/conferir-finalizada-unimed")
            ->assertAccepted()
            ->assertJsonPath('data.operacao', 'conferir_guia_finalizada')
            ->assertJsonPath('data.total_guias', 1);

        Queue::assertPushed(ExecutarAutomacaoUnimedJob::class, 1);
    }

    public function test_disparo_em_lote_leva_todas_as_elegiveis(): void
    {
        Queue::fake();
        $this->autenticar();
        $this->ignorarGuiasDaSemente();
        $this->guiaUnimed();
        $this->guiaUnimed();

        $resposta = $this->postJson('/api/guias/conferir-finalizadas-unimed')->assertAccepted();

        $this->assertSame(2, $resposta->json('data.total_guias'));
        $this->assertNull($resposta->json('data.guia_id'), 'o lote não pertence a uma guia');
    }

    public function test_disparo_avulso_recusa_convenio_sem_automacao(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed();
        $guia->convenio->update(['connector_driver' => null, 'connector_type' => 'manual']);

        $this->postJson("/api/guias/{$guia->id}/conferir-finalizada-unimed")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['guia']);
    }

    public function test_lote_exige_permissao_de_gestao(): void
    {
        $this->guiaUnimed();
        Sanctum::actingAs(User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail());

        $this->postJson('/api/guias/conferir-finalizadas-unimed')->assertForbidden();
    }

    // ---------------------------------------------------------------- resultado

    public function test_guia_encontrada_ganha_as_duas_datas(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed();
        $execucao = $this->enfileirar($guia);

        $this->comWorker([
            'status' => 'succeeded',
            'results' => [['guia_id' => $guia->id, 'numero_guia' => $guia->numero_guia, 'desfecho' => 'finalizada']],
            'resumo' => ['finalizadas' => 1, 'nao_finalizadas' => 0, 'falhas' => 0],
        ]);
        $this->executarJob($execucao);

        $guia->refresh();
        $this->assertNotNull($guia->finalizada_na_operadora_em);
        $this->assertNotNull($guia->conferida_na_operadora_em);
        $this->assertTrue($guia->finalizadaNaOperadora());
    }

    public function test_guia_nao_encontrada_ganha_so_a_data_da_conferencia(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed();
        $execucao = $this->enfileirar($guia);

        $this->comWorker([
            'status' => 'succeeded',
            'results' => [['guia_id' => $guia->id, 'desfecho' => 'nao_finalizada']],
            'resumo' => ['finalizadas' => 0, 'nao_finalizadas' => 1, 'falhas' => 0],
        ]);
        $this->executarJob($execucao);

        $guia->refresh();
        $this->assertNull($guia->finalizada_na_operadora_em);
        $this->assertNotNull($guia->conferida_na_operadora_em);
    }

    /** A marca afirma o que o portal diz AGORA, não o que disse uma vez. */
    public function test_guia_que_deixou_de_aparecer_perde_a_marca(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed();
        $guia->forceFill(['finalizada_na_operadora_em' => now()->subMonth(), 'conferida_na_operadora_em' => now()->subMonth()])->save();

        $execucao = $this->enfileirar($guia);
        $this->comWorker([
            'status' => 'succeeded',
            'results' => [['guia_id' => $guia->id, 'desfecho' => 'nao_finalizada']],
        ]);
        $this->executarJob($execucao);

        $this->assertNull($guia->refresh()->finalizada_na_operadora_em);
    }

    /**
     * Falha não é resposta. Escrever "conferida em X" para uma guia que não
     * pôde ser conferida faria o lote seguinte pulá-la para sempre.
     */
    public function test_guia_que_falhou_nao_recebe_marca_nem_data(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed();
        $execucao = $this->enfileirar($guia);

        $this->comWorker([
            'status' => 'succeeded',
            'results' => [['guia_id' => $guia->id, 'desfecho' => 'falhou', 'error_code' => 'FILTRO_DATA_NAO_LIMPO']],
            'resumo' => ['finalizadas' => 0, 'nao_finalizadas' => 0, 'falhas' => 1],
        ]);
        $this->executarJob($execucao);

        $guia->refresh();
        $this->assertNull($guia->finalizada_na_operadora_em);
        $this->assertNull($guia->conferida_na_operadora_em);
    }

    public function test_lote_misto_grava_cada_guia_pelo_proprio_desfecho(): void
    {
        $this->autenticar();
        $this->ignorarGuiasDaSemente();
        $achada = $this->guiaUnimed();
        $ausente = $this->guiaUnimed();
        $falha = $this->guiaUnimed();

        $execucao = $this->enfileirarLote((int) $achada->tenant_id);
        $this->comWorker([
            'status' => 'succeeded',
            'results' => [
                ['guia_id' => $achada->id, 'desfecho' => 'finalizada'],
                ['guia_id' => $ausente->id, 'desfecho' => 'nao_finalizada'],
                ['guia_id' => $falha->id, 'desfecho' => 'falhou', 'error_code' => 'WORKER_INTERNAL_FATAL'],
            ],
            'resumo' => ['finalizadas' => 1, 'nao_finalizadas' => 1, 'falhas' => 1],
        ]);
        $this->executarJob($execucao);

        $this->assertNotNull($achada->refresh()->finalizada_na_operadora_em);
        $this->assertNull($ausente->refresh()->finalizada_na_operadora_em);
        $this->assertNotNull($ausente->conferida_na_operadora_em);
        $this->assertNull($falha->refresh()->conferida_na_operadora_em);
    }

    public function test_execucao_falha_nao_toca_em_guia_nenhuma(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed();
        $execucao = $this->enfileirar($guia);

        $this->comWorker(['status' => 'failed', 'error_code' => 'LOGIN_ERROR', 'message' => 'credencial recusada']);
        $this->executarJob($execucao);

        $guia->refresh();
        $this->assertNull($guia->conferida_na_operadora_em);
        $this->assertSame('failed', $execucao->refresh()->status);
    }

    // -------------------------------------------------- interação com finalizar

    public function test_guia_marcada_nao_pode_ser_finalizada_na_unimed(): void
    {
        $this->autenticar();
        $guia = $this->guiaUnimed();
        $guia->forceFill(['finalizada_na_operadora_em' => now()])->save();

        $resposta = $this->getJson("/api/guias/{$guia->id}/finalizar-unimed/pre-voo")->assertOk();

        $this->assertFalse($resposta->json('data.pode_finalizar'));
        $this->assertContains(
            'A operadora já deu esta guia por finalizada — não há o que finalizar de novo.',
            $resposta->json('data.impedimentos'),
        );

        $this->postJson("/api/guias/{$guia->id}/finalizar-unimed")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['guia']);
    }

    // --------------------------------------------------------------- bastidores

    private function service(): ConferirGuiaFinalizadaUnimedService
    {
        return app(ConferirGuiaFinalizadaUnimedService::class);
    }

    /**
     * Tira as guias da semente do caminho do lote.
     *
     * Elas viram elegíveis assim que o convênio Unimed ganha o driver, e os
     * testes de contagem passariam a medir a semente junto do que criaram.
     * Marcá-las como já conferidas é o mesmo que dizer "essas não são o
     * assunto aqui".
     */
    private function ignorarGuiasDaSemente(): void
    {
        Guia::query()->whereNull('conferida_na_operadora_em')->update([
            'conferida_na_operadora_em' => now()->subYear(),
        ]);
    }

    private function autenticar(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail());
    }

    private function comWorker(array $resultado): void
    {
        $this->app->instance(UnimedWorkerClient::class, new FakeUnimedWorkerClient($resultado));
    }

    private function enfileirar(Guia $guia): AutomacaoExecucao
    {
        return $this->service()->enviar($guia, dispatch: false);
    }

    private function enfileirarLote(int $tenantId): AutomacaoExecucao
    {
        return $this->service()->enviarLote($tenantId, dispatch: false);
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

    /**
     * Um SEGUNDO convênio com automação Unimed, com guia própria.
     *
     * A credencial do portal é por convênio, então o lote só cobre um por vez.
     * Este helper é o que torna esse limite observável num teste.
     */
    private function guiaDeOutroConvenioAutomatizado(): Guia
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();

        $outro = Convenio::query()
            ->where('tenant_id', $tenant->id)
            ->where('nome', '!=', 'Unimed')
            ->firstOrFail();
        $outro->update(['connector_type' => 'scraping', 'connector_driver' => 'unimed_rda']);

        ConvenioCredencial::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'convenio_id' => $outro->id,
        ], [
            'driver' => 'unimed_rda',
            'credenciais' => ['login' => 'op2', 'password' => 'x', 'base_url' => 'https://portal2.test'],
            'ativo' => true,
        ]);

        $especialidade = Especialidade::query()->where('nome', 'Terapia ABA')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->where('convenio_id', $outro->id)->firstOrFail();

        return Guia::query()->create([
            'tenant_id' => $tenant->id,
            'convenio_id' => $outro->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => '6014'.random_int(100000, 999999),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::APPROVED,
            'sessoes_autorizadas' => 10,
            'data_solicitacao' => today(),
        ]);
    }

    private function guiaUnimed(string $status = GuiaStatus::APPROVED): Guia
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
            'numero_guia' => '5014'.random_int(100000, 999999),
            'tipo_terapia' => 'especializada',
            'status' => $status,
            'sessoes_autorizadas' => 10,
            'data_solicitacao' => today(),
            'senha' => 'SENHA-X',
            'validade_senha' => '2026-12-31',
        ]);

        return $guia->load('convenio');
    }
}

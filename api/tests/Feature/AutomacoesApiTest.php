<?php

namespace Tests\Feature;

use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoEvento;
use App\Models\AutomacaoExecucao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutomacoesApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lista_filtra_e_mostra_execucoes_com_timeline(): void
    {
        $this->autenticar();
        $execucao = $this->execucao(['status' => 'failed', 'operacao' => 'consultar_status']);
        AutomacaoEvento::query()->create([
            'tenant_id' => $execucao->tenant_id,
            'automacao_execucao_id' => $execucao->id,
            'tipo' => 'failed',
            'status' => 'failed',
            'payload' => ['erro' => 'timeout'],
            'evidencias' => ['screenshot' => 'private/evidencias/teste.png'],
            'registrado_em' => now(),
        ]);
        $this->execucao(['status' => 'succeeded', 'operacao' => 'gerar_guia']);

        $this->getJson('/api/automacoes?needs_attention=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $execucao->id);

        $this->getJson("/api/automacoes/{$execucao->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $execucao->id)
            ->assertJsonPath('data.eventos.0.tipo', 'failed')
            ->assertJsonPath('data.eventos.0.evidencias.screenshot', 'private/evidencias/teste.png');
    }

    public function test_reprocessa_execucao_com_falha_vinculando_parent(): void
    {
        Queue::fake();
        $this->autenticar();
        $execucao = $this->execucao(['status' => 'failed', 'operacao' => 'consultar_status']);

        $this->postJson("/api/automacoes/{$execucao->id}/reprocessar")
            ->assertAccepted()
            ->assertJsonPath('data.parent_id', $execucao->id)
            ->assertJsonPath('data.status', 'queued');

        Queue::assertPushed(ExecutarAutomacaoUnimedJob::class);
    }

    public function test_bloqueia_reprocessamento_de_gerar_guia_uncertain(): void
    {
        Queue::fake();
        $this->autenticar();
        $execucao = $this->execucao(['status' => 'uncertain', 'operacao' => 'gerar_guia']);

        $this->postJson("/api/automacoes/{$execucao->id}/reprocessar")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['execucao']);

        Queue::assertNothingPushed();
    }

    public function test_isola_execucoes_por_tenant(): void
    {
        $this->autenticar();
        $externa = $this->execucaoExterna();
        $this->execucao(['status' => 'failed']);

        $this->getJson('/api/automacoes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['id' => $externa->id]);

        $this->getJson("/api/automacoes/{$externa->id}")
            ->assertNotFound();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function execucao(array $overrides = []): AutomacaoExecucao
    {
        $tenantId = (int) User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail()->tenant_id;

        return AutomacaoExecucao::query()->create(array_merge([
            'tenant_id' => $tenantId,
            'operacao' => 'operacao_mock',
            'status' => 'queued',
            'idempotency_key' => uniqid('auto-', true),
            'payload' => ['teste' => true],
            'queued_at' => now(),
        ], $overrides));
    }

    private function execucaoExterna(): AutomacaoExecucao
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Tenant Automações Externo',
            'slug' => 'tenant-automacoes-externo',
            'cnpj' => '66.666.666/0001-66',
            'ativo' => true,
        ]);

        return AutomacaoExecucao::query()->create([
            'tenant_id' => $tenant->id,
            'operacao' => 'consultar_status',
            'status' => 'failed',
            'idempotency_key' => uniqid('externa-', true),
            'payload' => [],
            'queued_at' => now(),
        ]);
    }
}

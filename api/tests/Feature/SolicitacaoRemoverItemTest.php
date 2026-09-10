<?php

namespace Tests\Feature;

use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\SolicitacaoItem;
use App\Models\User;
use App\Support\SolicitacaoStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `DELETE /solicitacoes/{solicitacao}/itens/{item}` — corrigir cadastro
 * errado excluindo um item que ainda não gerou Guia.
 */
class SolicitacaoRemoverItemTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $usuario = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        $this->tenantId = (int) $usuario->tenant_id;
        Sanctum::actingAs($usuario);
    }

    private function solicitacaoComDoisItens(string $status = SolicitacaoStatus::READY_FOR_AUTOMATION): Solicitacao
    {
        $convenio = Convenio::query()->where('nome', 'SC Saúde')->firstOrFail();
        $especialidade = Especialidade::query()
            ->whereIn('id', Profissional::query()->distinct()->pluck('especialidade_id'))
            ->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();

        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $this->tenantId,
            'paciente_id' => Paciente::query()->firstOrFail()->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_id' => Medico::query()->firstOrFail()->id,
            'status' => $status,
            'solicitado_em' => today()->subDays(10)->toDateString(),
        ]);
        $solicitacao->cidCadastros()->attach(Cid::query()->firstOrFail()->id);

        foreach ([10, 10] as $quantidade) {
            $solicitacao->itens()->create([
                'tenant_id' => $this->tenantId,
                'especialidade_id' => $especialidade->id,
                'profissional_id' => $profissional->id,
                'quantidade' => $quantidade,
                'status_operacional' => 'pending',
            ]);
        }

        return $solicitacao->refresh();
    }

    public function test_remove_item_sem_guia_com_sucesso(): void
    {
        $solicitacao = $this->solicitacaoComDoisItens();
        $item = $solicitacao->itens()->orderBy('id')->skip(1)->firstOrFail();

        $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/itens/{$item->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.itens');

        $this->assertSame(1, $solicitacao->itens()->count());
        $this->assertNull(SolicitacaoItem::find($item->id));
    }

    public function test_recusa_remover_item_com_guia(): void
    {
        $solicitacao = $this->solicitacaoComDoisItens();
        $item = $solicitacao->itens()->orderBy('id')->skip(1)->firstOrFail();

        Guia::query()->create([
            'tenant_id' => $this->tenantId,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $item->id,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $item->profissional_id,
            'especialidade_id' => $item->especialidade_id,
            'status' => 'under_review',
            'tipo_terapia' => 'especializada',
            'data_solicitacao' => today(),
        ]);

        $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/itens/{$item->id}")
            ->assertStatus(422);

        $this->assertNotNull(SolicitacaoItem::find($item->id));
    }

    public function test_recusa_remover_o_ultimo_item(): void
    {
        $solicitacao = $this->solicitacaoComDoisItens();
        $itens = $solicitacao->itens()->orderBy('id')->get();

        $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/itens/{$itens[1]->id}")->assertOk();
        $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/itens/{$itens[0]->id}")->assertStatus(422);

        $this->assertSame(1, $solicitacao->itens()->count());
    }

    public function test_recusa_remover_em_denied_e_em_historico(): void
    {
        foreach ([SolicitacaoStatus::DENIED, SolicitacaoStatus::HISTORICO] as $status) {
            $solicitacao = $this->solicitacaoComDoisItens($status);
            $item = $solicitacao->itens()->orderBy('id')->skip(1)->firstOrFail();

            $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/itens/{$item->id}")
                ->assertStatus(422);

            $this->assertSame(2, $solicitacao->itens()->count(), "status {$status} removeu item");
        }
    }

    public function test_recusa_item_de_outra_solicitacao(): void
    {
        $solicitacao = $this->solicitacaoComDoisItens();
        $outra = $this->solicitacaoComDoisItens();
        $itemDaOutra = $outra->itens()->orderBy('id')->skip(1)->firstOrFail();

        $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/itens/{$itemDaOutra->id}")
            ->assertStatus(422);

        $this->assertNotNull(SolicitacaoItem::find($itemDaOutra->id));
    }

    public function test_sem_permissao_nao_remove_item(): void
    {
        $solicitacao = $this->solicitacaoComDoisItens();
        $item = $solicitacao->itens()->orderBy('id')->skip(1)->firstOrFail();
        Sanctum::actingAs(User::query()->where('email', '!=', 'admin@clinica-exemplo.test')->firstOrFail());

        $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/itens/{$item->id}")->assertForbidden();
    }
}

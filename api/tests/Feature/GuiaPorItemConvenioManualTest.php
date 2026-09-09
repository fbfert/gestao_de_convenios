<?php

namespace Tests\Feature;

use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\GuiaService;
use App\Services\SolicitacaoService;
use App\Support\GuiaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Convênio SEM automação gera uma guia por ITEM.
 *
 * Antes era `itens()->orderBy('id')->first()`: uma guia só, a do primeiro item.
 * Desde a multi-especialidade os demais itens ficavam sem guia nenhuma, e a
 * solicitação seguia parecendo processada — defeito silencioso.
 *
 * O convênio é SC Saúde de propósito: `sincronizarGuiaDaSolicitacao` sai cedo
 * para `unimed_rda`, onde quem gera a guia é o robô.
 */
class GuiaPorItemConvenioManualTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function autenticar(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail());
    }

    /** @return array{0: int, 1: array<int, int>} */
    private function criarComTresItens(): array
    {
        $convenio = Convenio::query()->where('nome', 'SC Saúde')->firstOrFail();
        $especialidades = Especialidade::query()
            ->whereIn(
                'id',
                Profissional::query()->distinct()->pluck('especialidade_id'),
            )
            ->take(3)
            ->get();

        $this->assertCount(3, $especialidades, 'a semente precisa de 3 especialidades com profissional');

        $itens = $especialidades->map(fn (Especialidade $especialidade) => [
            'especialidade_id' => $especialidade->id,
            'profissional_id' => Profissional::query()
                ->where('especialidade_id', $especialidade->id)
                ->firstOrFail()->id,
            'quantidade' => 5,
        ])->all();

        $resposta = $this->postJson('/api/solicitacoes', [
            'paciente_id' => Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail()->id,
            'convenio_id' => $convenio->id,
            'medico_id' => Medico::query()->firstOrFail()->id,
            'cid_ids' => [Cid::query()->firstOrFail()->id],
            'solicitado_em' => today()->toDateString(),
            'itens' => $itens,
        ])->assertCreated();

        return [
            $resposta->json('data.id'),
            array_column($resposta->json('data.itens'), 'id'),
        ];
    }

    public function test_tres_itens_geram_tres_guias_com_numeros_distintos(): void
    {
        $this->autenticar();
        [$solicitacaoId, $itemIds] = $this->criarComTresItens();

        $this->patchJson("/api/solicitacoes/{$solicitacaoId}/aprovar")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_automation');

        $guias = Guia::query()->where('solicitacao_id', $solicitacaoId)->get();

        $this->assertCount(3, $guias);
        // Uma por item, e cada item com a sua — não três guias no mesmo item.
        $this->assertEqualsCanonicalizing($itemIds, $guias->pluck('solicitacao_item_id')->all());

        // O ponto do change: números distintos. O índice (convenio_id,
        // numero_guia) NÃO é unique, então guias com o mesmo "número" não
        // estouram — só fazem qualquer busca por número devolver a errada.
        $numeros = $guias->pluck('numero_guia');
        $this->assertCount(3, $numeros->unique(), 'as três guias nasceram com o mesmo número');

        foreach ($guias as $guia) {
            $this->assertSame(
                GuiaStatus::PREFIXO_NUMERO_PLACEHOLDER."{$solicitacaoId}-{$guia->solicitacao_item_id}",
                $guia->numero_guia,
            );
            $this->assertSame(GuiaStatus::UNDER_REVIEW, $guia->status);
        }
    }

    public function test_rodar_a_sincronizacao_duas_vezes_nao_duplica_guia(): void
    {
        $this->autenticar();
        [$solicitacaoId] = $this->criarComTresItens();

        $this->patchJson("/api/solicitacoes/{$solicitacaoId}/aprovar")->assertOk();
        $numerosDaPrimeira = Guia::query()
            ->where('solicitacao_id', $solicitacaoId)
            ->pluck('numero_guia')
            ->sort()
            ->values()
            ->all();

        // Volta e avança de novo: é o caminho real de quem devolve a
        // solicitação para análise e depois libera outra vez.
        $this->patchJson("/api/solicitacoes/{$solicitacaoId}/status", ['status' => 'under_review'])->assertOk();
        $this->patchJson("/api/solicitacoes/{$solicitacaoId}/status", ['status' => 'ready_for_automation'])->assertOk();

        $depois = Guia::query()->where('solicitacao_id', $solicitacaoId)->get();

        $this->assertCount(3, $depois);
        $this->assertSame($numerosDaPrimeira, $depois->pluck('numero_guia')->sort()->values()->all());
    }

    public function test_item_que_ja_tem_guia_nao_e_tocado(): void
    {
        $this->autenticar();
        [$solicitacaoId] = $this->criarComTresItens();
        $solicitacao = Solicitacao::query()->findOrFail($solicitacaoId);

        $this->patchJson("/api/solicitacoes/{$solicitacaoId}/aprovar")->assertOk();

        // Uma das guias avança na operadora. Re-sincronizar não pode arrastá-la
        // de volta para under_review: a versão anterior deste método atualizava
        // a guia encontrada, e repetir aquilo dentro de um laço resetaria guias
        // já aprovadas dos outros itens.
        $guia = Guia::query()->where('solicitacao_id', $solicitacaoId)->firstOrFail();
        $guia->numero_guia = '50143966538';
        // Pelo ponto único de escrita: `status` direto no modelo é barrado pela
        // trava do change guia-status-historico.
        app(GuiaService::class)->registrarTransicao($guia, GuiaStatus::APPROVED, [
            'origem' => GuiaStatusHistorico::ORIGEM_MANUAL,
        ]);

        app(SolicitacaoService::class)->alterarStatus($solicitacao->fresh(), 'under_review');
        app(SolicitacaoService::class)->alterarStatus($solicitacao->fresh(), 'ready_for_automation');

        $guia->refresh();
        $this->assertSame(GuiaStatus::APPROVED, $guia->status);
        $this->assertSame('50143966538', $guia->numero_guia);
        $this->assertSame(3, Guia::query()->where('solicitacao_id', $solicitacaoId)->count());
    }
}

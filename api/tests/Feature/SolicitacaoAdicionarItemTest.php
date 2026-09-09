<?php

namespace Tests\Feature;

use App\Models\Cid;
use App\Models\Convenio;
use App\Models\ConvenioRegra;
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
 * `POST /solicitacoes/{solicitacao}/itens` — acrescentar sessões a um pedido
 * que já existe, sem duplicar paciente, médico, CID e anexos.
 */
class SolicitacaoAdicionarItemTest extends TestCase
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

    private function convenio(string $nome = 'SC Saúde'): Convenio
    {
        return Convenio::query()->where('nome', $nome)->firstOrFail();
    }

    private function solicitacao(
        string $status = SolicitacaoStatus::READY_FOR_AUTOMATION,
        string $convenioNome = 'SC Saúde',
    ): Solicitacao {
        $convenio = $this->convenio($convenioNome);
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
            'solicitado_em' => today()->subDays(88)->toDateString(),
        ]);
        $solicitacao->cidCadastros()->attach(Cid::query()->firstOrFail()->id);

        $solicitacao->itens()->create([
            'tenant_id' => $this->tenantId,
            'especialidade_id' => $especialidade->id,
            'profissional_id' => $profissional->id,
            'quantidade' => 10,
            'status_operacional' => 'pending',
        ]);

        return $solicitacao->refresh();
    }

    /** @return array{especialidade_id: int, profissional_id: int} */
    private function mesmoParDe(Solicitacao $solicitacao): array
    {
        $item = $solicitacao->itens()->orderBy('id')->firstOrFail();

        return [
            'especialidade_id' => $item->especialidade_id,
            'profissional_id' => $item->profissional_id,
        ];
    }

    public function test_item_repetido_com_mesma_especialidade_e_profissional_e_aceito(): void
    {
        $solicitacao = $this->solicitacao();

        // O caso de uso principal: 10 sessões já pedidas, mais 10 sob o mesmo
        // pedido médico. Repetir NÃO pode ser bloqueado.
        $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", [
            ...$this->mesmoParDe($solicitacao),
            'quantidade' => 10,
        ])->assertCreated()->assertJsonCount(2, 'data.itens');

        $this->assertSame(2, $solicitacao->itens()->count());
    }

    public function test_renovacao_de_item_de_outra_solicitacao_e_recusada(): void
    {
        $solicitacao = $this->solicitacao();
        $outra = $this->solicitacao();
        $itemDaOutra = $outra->itens()->firstOrFail();

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", [
            ...$this->mesmoParDe($solicitacao),
            'quantidade' => 10,
            'renovacao_de_item_id' => $itemDaOutra->id,
        ])->assertStatus(422)->assertJsonValidationErrors('renovacao_de_item_id');
    }

    public function test_renovacao_apontando_para_item_que_ja_e_renovacao_resolve_para_a_origem(): void
    {
        $solicitacao = $this->solicitacao();
        $origem = $solicitacao->itens()->orderBy('id')->firstOrFail();

        $segundo = $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", [
            ...$this->mesmoParDe($solicitacao),
            'quantidade' => 10,
            'renovacao_de_item_id' => $origem->id,
        ])->assertCreated()->json('data.itens.1');

        $this->assertSame($origem->id, $segundo['renovacao_de_item_id']);

        // Agora aponta para o SEGUNDO, que já é renovação: o servidor tem que
        // gravar a origem. Em árvore, somar o ciclo viraria recursão.
        $terceiro = $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", [
            ...$this->mesmoParDe($solicitacao),
            'quantidade' => 10,
            'renovacao_de_item_id' => $segundo['id'],
        ])->assertCreated()->json('data.itens.2');

        $this->assertSame($origem->id, $terceiro['renovacao_de_item_id']);
        $this->assertSame(3, $terceiro['posicao_na_cadeia']);
    }

    public function test_adicionar_em_denied_e_em_historico_e_recusado(): void
    {
        foreach ([SolicitacaoStatus::DENIED, SolicitacaoStatus::HISTORICO] as $status) {
            $solicitacao = $this->solicitacao($status);

            $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", [
                ...$this->mesmoParDe($solicitacao),
                'quantidade' => 10,
            ])->assertStatus(422);

            $this->assertSame(1, $solicitacao->itens()->count(), "status {$status} aceitou item novo");
        }
    }

    public function test_adicionar_em_approved_e_aceito_e_sincroniza_o_status(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::APPROVED, 'Unimed');
        Convenio::query()->where('nome', 'Unimed')->update(['connector_driver' => 'unimed_rda']);

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", [
            ...$this->mesmoParDe($solicitacao),
            'quantidade' => 10,
        ])->assertCreated();

        // Convênio com automação: o item novo nasce sem guia, então a
        // solicitação volta a "pronta para automatização" e o item pode ser
        // enviado — que é o ponto da feature inteira.
        $this->assertSame(SolicitacaoStatus::READY_FOR_AUTOMATION, $solicitacao->refresh()->status);
    }

    public function test_convenio_sem_regra_vigente_nao_recebe_quantidade_padrao(): void
    {
        // SC Saúde tem regra vigente, mas sem `sessoes_por_guia` — nulo é "não
        // sabemos", e não zero.
        $solicitacao = $this->solicitacao();

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", $this->mesmoParDe($solicitacao))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantidade');

        $contexto = $this->getJson("/api/solicitacoes/{$solicitacao->id}/contexto-adicao")
            ->assertOk()
            ->json('data');

        $this->assertNull($contexto['sessoes_por_guia']);
        $this->assertNull($contexto['quantidade_padrao']);
    }

    /**
     * O TEXTO do 422, não só o código.
     *
     * Asserção só pelo 422 passaria por qualquer outra recusa — e "informe a
     * quantidade" mandava a pessoa contornar o problema para sempre, uma
     * solicitação por vez. A mensagem nomeia o convênio e a tela onde se
     * resolve de uma vez.
     */
    public function test_o_422_nomeia_o_convenio_e_fala_em_sessoes_por_guia(): void
    {
        $solicitacao = $this->solicitacao();

        $resposta = $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", $this->mesmoParDe($solicitacao))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantidade');

        $mensagem = implode(' ', (array) data_get($resposta->json(), 'errors.quantidade', []));

        $this->assertStringContainsString('SC Saúde', $mensagem);
        $this->assertStringContainsString('Sessões por guia', $mensagem);
        $this->assertStringContainsString('Convênios', $mensagem);
    }

    public function test_convenio_com_regra_vigente_traz_a_quantidade_da_regra(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::READY_FOR_AUTOMATION, 'Unimed');

        $item = $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", $this->mesmoParDe($solicitacao))
            ->assertCreated()
            ->json('data.itens.1');

        // Vem de convenio_regras.sessoes_por_guia (10 na semente da Unimed), e
        // não de um `?? 10` no código.
        $this->assertSame(10, $item['quantidade']);

        ConvenioRegra::query()
            ->where('convenio_id', $solicitacao->convenio_id)
            ->update(['sessoes_por_guia' => 6]);

        $outro = $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", $this->mesmoParDe($solicitacao))
            ->assertCreated()
            ->json('data.itens.2');

        $this->assertSame(6, $outro['quantidade']);
    }

    public function test_convenio_manual_gera_guia_do_item_novo_e_unimed_nao(): void
    {
        $manual = $this->solicitacao(SolicitacaoStatus::READY_FOR_AUTOMATION);

        $novoManual = $this->postJson("/api/solicitacoes/{$manual->id}/itens", [
            ...$this->mesmoParDe($manual),
            'quantidade' => 10,
        ])->assertCreated()->json('data.itens.1');

        // Disparo explícito: sincronizarStatusComGuias é pura de status, então
        // sem a chamada de criação no endpoint a guia nunca nasceria.
        $this->assertNotNull($novoManual['guia'], 'convênio manual deveria gerar a guia do item novo');
        $this->assertSame(2, Guia::query()->where('solicitacao_id', $manual->id)->count());

        Convenio::query()->where('nome', 'Unimed')->update(['connector_driver' => 'unimed_rda']);
        $unimed = $this->solicitacao(SolicitacaoStatus::READY_FOR_AUTOMATION, 'Unimed');

        $novoUnimed = $this->postJson("/api/solicitacoes/{$unimed->id}/itens", [
            ...$this->mesmoParDe($unimed),
            'quantidade' => 10,
        ])->assertCreated()->json('data.itens.1');

        // Quem gera guia na Unimed é o robô.
        $this->assertNull($novoUnimed['guia']);
        $this->assertSame(0, Guia::query()->where('solicitacao_id', $unimed->id)->count());
    }

    public function test_adicionar_em_under_review_nao_gera_guia_nem_muda_status(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::UNDER_REVIEW);

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", [
            ...$this->mesmoParDe($solicitacao),
            'quantidade' => 10,
        ])->assertCreated();

        // Gerar guia aqui pularia a análise, que é o que este status representa.
        $this->assertSame(0, Guia::query()->where('solicitacao_id', $solicitacao->id)->count());
        $this->assertSame(SolicitacaoStatus::UNDER_REVIEW, $solicitacao->refresh()->status);
    }

    public function test_contexto_traz_os_dados_dos_quatro_avisos(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::READY_FOR_AUTOMATION, 'Unimed');
        $origem = $solicitacao->itens()->orderBy('id')->firstOrFail();

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", [
            ...$this->mesmoParDe($solicitacao),
            'quantidade' => 10,
            'renovacao_de_item_id' => $origem->id,
        ])->assertCreated();

        $contexto = $this->getJson("/api/solicitacoes/{$solicitacao->id}/contexto-adicao?".http_build_query([
            'especialidade_id' => $origem->especialidade_id,
            'profissional_id' => $origem->profissional_id,
            'renovacao_de_item_id' => $origem->id,
        ]))->assertOk()->json('data');

        $this->assertSame(88, $contexto['pedido_medico_dias']);
        $this->assertSame(10, $contexto['sessoes_por_guia']);
        $this->assertTrue($contexto['ja_existe_item_igual']);
        // Soma da CADEIA (10 da origem + 10 da renovação), e não do par
        // especialidade+profissional.
        $this->assertSame(20, $contexto['sessoes_na_cadeia']);
    }

    public function test_sem_permissao_nao_adiciona_item(): void
    {
        $solicitacao = $this->solicitacao();
        Sanctum::actingAs(User::query()->where('email', '!=', 'admin@clinica-exemplo.test')->firstOrFail());

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", [
            ...$this->mesmoParDe($solicitacao),
            'quantidade' => 10,
        ])->assertForbidden();
    }

    public function test_item_novo_nao_e_renovacao_quando_a_especialidade_e_outra(): void
    {
        $solicitacao = $this->solicitacao();
        $outraEspecialidade = Especialidade::query()
            ->whereIn('id', Profissional::query()->distinct()->pluck('especialidade_id'))
            ->where('id', '!=', $solicitacao->itens()->value('especialidade_id'))
            ->firstOrFail();

        $novo = $this->postJson("/api/solicitacoes/{$solicitacao->id}/itens", [
            'especialidade_id' => $outraEspecialidade->id,
            'profissional_id' => Profissional::query()
                ->where('especialidade_id', $outraEspecialidade->id)
                ->firstOrFail()->id,
            'quantidade' => 8,
        ])->assertCreated()->json('data.itens.1');

        $this->assertNull($novo['renovacao_de_item_id']);
        $this->assertSame(1, $novo['posicao_na_cadeia']);
        $this->assertSame(
            null,
            SolicitacaoItem::query()->findOrFail($novo['id'])->renovacao_de_item_id,
        );
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\SolicitacaoItem;
use App\Models\User;
use App\Services\GuiaService;
use App\Services\SolicitacaoService;
use App\Support\GuiaStatus;
use App\Support\SolicitacaoStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A situação da Solicitação passou a REFLETIR as guias dos itens, e não só a
 * evoluir: pode regredir para `ready_for_automation` quando aparece item sem
 * guia. Sem isso, "Adicionar sessões" nasceria inútil — o item novo entra numa
 * solicitação aprovada e o botão de enviar nunca habilitaria.
 *
 * O convênio é forçado a `unimed_rda` porque o `ConvenioSeeder` cria os três
 * convênios de teste como manuais, e em convênio manual todo item ganha guia
 * sozinho: o estado "item sem guia" só existe onde quem gera a guia é o robô.
 */
class SolicitacaoStatusRefleteItensTest extends TestCase
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

        Convenio::query()->where('nome', 'Unimed')->update(['connector_driver' => 'unimed_rda']);
    }

    private function convenio(): Convenio
    {
        return Convenio::query()->where('nome', 'Unimed')->firstOrFail();
    }

    /** Cria a solicitação já numa situação dada, com N itens e sem guia nenhuma. */
    private function solicitacao(string $status, int $quantidadeDeItens = 2): Solicitacao
    {
        $convenio = $this->convenio();
        $especialidades = Especialidade::query()
            ->whereIn('id', Profissional::query()->distinct()->pluck('especialidade_id'))
            ->take($quantidadeDeItens)
            ->get();

        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $this->tenantId,
            'paciente_id' => Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail()->id,
            'profissional_id' => Profissional::query()->firstOrFail()->id,
            'especialidade_id' => $especialidades[0]->id,
            'convenio_id' => $convenio->id,
            'medico_id' => Medico::query()->firstOrFail()->id,
            'status' => $status,
            'solicitado_em' => today()->toDateString(),
        ]);
        $solicitacao->cidCadastros()->attach(Cid::query()->firstOrFail()->id);

        foreach ($especialidades as $especialidade) {
            $solicitacao->itens()->create([
                'tenant_id' => $this->tenantId,
                'especialidade_id' => $especialidade->id,
                'profissional_id' => Profissional::query()
                    ->where('especialidade_id', $especialidade->id)
                    ->firstOrFail()->id,
                'quantidade' => 5,
                'status_operacional' => 'pending',
            ]);
        }

        return $solicitacao->refresh();
    }

    private function guiaPara(SolicitacaoItem $item, string $status = GuiaStatus::UNDER_REVIEW): Guia
    {
        $solicitacao = $item->solicitacao;

        $guia = Guia::query()->create([
            'tenant_id' => $solicitacao->tenant_id,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $item->id,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $item->profissional_id,
            'especialidade_id' => $item->especialidade_id,
            'numero_guia' => 'GUIA-'.$item->id.'-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::UNDER_REVIEW,
            'data_solicitacao' => today(),
        ]);

        if ($status !== GuiaStatus::UNDER_REVIEW) {
            // Ponto único de escrita: `status` direto no modelo é barrado pela
            // trava do change guia-status-historico.
            app(GuiaService::class)->registrarTransicao($guia, $status, [
                'origem' => GuiaStatusHistorico::ORIGEM_MANUAL,
            ]);
        }

        return $guia->refresh();
    }

    private function sincronizar(Solicitacao $solicitacao): string
    {
        app(SolicitacaoService::class)->sincronizarStatusComGuias($solicitacao);

        return $solicitacao->refresh()->status;
    }

    public function test_approved_que_recebe_item_sem_guia_volta_para_ready_for_automation(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::APPROVED, 1);
        $this->guiaPara($solicitacao->itens()->firstOrFail(), GuiaStatus::APPROVED);

        // O item novo entra depois — é exatamente "Adicionar sessões".
        $solicitacao->itens()->create([
            'tenant_id' => $this->tenantId,
            'especialidade_id' => $solicitacao->itens()->firstOrFail()->especialidade_id,
            'profissional_id' => $solicitacao->itens()->firstOrFail()->profissional_id,
            'quantidade' => 5,
            'status_operacional' => 'pending',
        ]);

        $this->assertSame(SolicitacaoStatus::READY_FOR_AUTOMATION, $this->sincronizar($solicitacao));
    }

    public function test_todos_com_guia_e_nem_todas_aprovadas_vira_guia_gerada(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::READY_FOR_AUTOMATION);
        $itens = $solicitacao->itens()->orderBy('id')->get();

        $this->guiaPara($itens[0], GuiaStatus::APPROVED);
        $this->guiaPara($itens[1], GuiaStatus::UNDER_REVIEW);

        $this->assertSame(SolicitacaoStatus::GUIA_GERADA, $this->sincronizar($solicitacao));
    }

    public function test_todos_aprovados_vira_approved(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::GUIA_GERADA);

        foreach ($solicitacao->itens()->orderBy('id')->get() as $item) {
            $this->guiaPara($item, GuiaStatus::APPROVED);
        }

        $this->assertSame(SolicitacaoStatus::APPROVED, $this->sincronizar($solicitacao));
    }

    public function test_denied_que_recebe_item_nao_muda_de_status(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::DENIED);

        // Regredir aqui reabriria pela porta dos fundos algo que foi encerrado.
        $this->assertSame(SolicitacaoStatus::DENIED, $this->sincronizar($solicitacao));
    }

    public function test_historico_que_recebe_item_nao_muda_de_status(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::HISTORICO);

        $this->assertSame(SolicitacaoStatus::HISTORICO, $this->sincronizar($solicitacao));
    }

    public function test_under_review_nao_muda_de_status(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::UNDER_REVIEW);

        foreach ($solicitacao->itens()->orderBy('id')->get() as $item) {
            $this->guiaPara($item, GuiaStatus::APPROVED);
        }

        // Derivar por cima da análise pularia a etapa que existe para conferir.
        $this->assertSame(SolicitacaoStatus::UNDER_REVIEW, $this->sincronizar($solicitacao));
    }

    public function test_sincronizar_duas_vezes_nao_grava_na_segunda(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::READY_FOR_AUTOMATION);

        foreach ($solicitacao->itens()->orderBy('id')->get() as $item) {
            $this->guiaPara($item, GuiaStatus::APPROVED);
        }

        $this->assertSame(SolicitacaoStatus::APPROVED, $this->sincronizar($solicitacao));

        // `Solicitacao` é Auditable: gravar o mesmo valor de novo encheria a
        // trilha de linhas que não registram mudança nenhuma.
        $auditoriasAntes = AuditLog::query()->where('entidade', 'solicitacao')->count();
        $atualizadoEmAntes = $solicitacao->refresh()->updated_at;

        $this->assertSame(SolicitacaoStatus::APPROVED, $this->sincronizar($solicitacao));

        $this->assertSame($auditoriasAntes, AuditLog::query()->where('entidade', 'solicitacao')->count());
        $this->assertEquals($atualizadoEmAntes, $solicitacao->refresh()->updated_at);
    }

    public function test_rota_de_envio_recusa_item_de_solicitacao_under_review(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::UNDER_REVIEW, 1);
        $item = $solicitacao->itens()->firstOrFail();

        // Interface não é controle de acesso: o botão some, e a rota também tem
        // que recusar.
        $resposta = $this->postJson("/api/solicitacao-itens/{$item->id}/enviar-unimed")
            ->assertStatus(422)
            ->assertJsonValidationErrors('item');

        $this->assertStringContainsString(
            'liberada para automatização',
            implode(' ', (array) data_get($resposta->json(), 'errors.item', [])),
        );
    }

    public function test_rota_de_envio_aceita_item_sem_guia_de_solicitacao_approved(): void
    {
        $solicitacao = $this->solicitacao(SolicitacaoStatus::APPROVED, 1);
        $item = $solicitacao->itens()->firstOrFail();

        $resposta = $this->postJson("/api/solicitacao-itens/{$item->id}/enviar-unimed");

        // O cenário não tem credencial/mapeamento configurados, então o envio
        // ainda pode ser recusado — o que este teste trava é que a recusa NÃO
        // seja mais por causa da situação da solicitação.
        if ($resposta->status() === 422) {
            $motivos = implode(' ', (array) data_get($resposta->json(), 'errors.item', []));
            $this->assertStringNotContainsString('liberada para automatização', $motivos);
            $this->assertStringNotContainsString('pronta para automatização', $motivos);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Antecipacao;
use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GuiaService;
use App\Support\GuiaStatus;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AntecipacoesApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * `TenantContext` é estático por processo — em suíte cheia, um teste
     * anterior pode deixar outro tenant/id sujo se não resetar. As consultas
     * diretas (fora de request HTTP) desta classe dependem dele pra escopar
     * `Convenio`/`Especialidade`/etc., então fixamos aqui, igual
     * CentralDeAlertasTest já faz.
     */
    private function autenticar(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
        TenantContext::set((int) $user->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId((int) $user->tenant_id);

        return $user;
    }

    /** Solicitação com 1 item completo e guia já aprovada — pronta pra virar elegível. */
    private function solicitacaoComGuiaAprovada(): Solicitacao
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        $tenantId = (int) $user->tenant_id;

        // Convênio "Unimed" da suíte é `connector_type: manual` (não é o
        // `unimed_rda` do ambiente real) — então gerar item aqui já cria a
        // guia na hora, o mesmo caminho que qualquer convênio manual segue.
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->firstOrFail();
        $medico = Medico::query()->firstOrFail();

        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $tenantId,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_id' => $medico->id,
            'status' => 'guia_gerada',
            'solicitado_em' => today()->subDays(30)->toDateString(),
        ]);
        $solicitacao->cidCadastros()->attach(Cid::query()->firstOrFail()->id);
        $solicitacao->itens()->create([
            'tenant_id' => $tenantId,
            'especialidade_id' => $especialidade->id,
            'profissional_id' => $profissional->id,
            'quantidade' => 10,
            'status_operacional' => 'pending',
        ]);

        $guia = Guia::query()->create([
            'tenant_id' => $tenantId,
            'solicitacao_id' => $solicitacao->id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'ANTEC-API-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::UNDER_REVIEW,
            'data_solicitacao' => today()->subDays(30)->toDateString(),
            // Vencendo em 10 dias, com o padrão global (20 dias de antecedência
            // sobre validade da senha) já devida há 10 dias.
            'validade_senha' => today()->copy()->addDays(10)->toDateString(),
        ]);
        app(GuiaService::class)->registrarTransicao($guia, GuiaStatus::APPROVED);

        return $solicitacao->refresh();
    }

    public function test_elegiveis_lista_solicitacao_candidata(): void
    {
        $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();

        $data = $this->getJson('/api/antecipacoes/elegiveis')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($solicitacao->id, $data[0]['solicitacao_id']);
        $this->assertCount(1, $data[0]['guias']);
    }

    public function test_elegiveis_exclui_solicitacao_ja_gerada_ou_ignorada(): void
    {
        $user = $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();

        Antecipacao::query()->create([
            'tenant_id' => $user->tenant_id,
            'solicitacao_origem_id' => $solicitacao->id,
            'status' => Antecipacao::STATUS_IGNORADA,
            'ignorado_em' => now(),
        ]);

        $data = $this->getJson('/api/antecipacoes/elegiveis')->assertOk()->json('data');

        $this->assertCount(0, $data);
    }

    /** O coração da correção: gera item+guia NA MESMA solicitação, não uma solicitação nova. */
    public function test_criar_gera_item_e_guia_por_renovacao_na_mesma_solicitacao(): void
    {
        $user = $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();
        $itemOrigem = $solicitacao->itens()->firstOrFail();
        $totalSolicitacoesAntes = Solicitacao::query()->count();

        $criada = $this->postJson('/api/antecipacoes', [
            'solicitacao_origem_id' => $solicitacao->id,
            'itens_selecionados' => [[
                'especialidade_id' => $itemOrigem->especialidade_id,
                'profissional_id' => $itemOrigem->profissional_id,
            ]],
        ])->assertCreated()->json('data');

        $this->assertSame('gerada', $criada['status']);
        $this->assertNotNull($criada['gerado_em']);
        $this->assertSame($user->id, Antecipacao::query()->findOrFail($criada['id'])->criado_por_id);
        $this->assertSame($solicitacao->id, $criada['solicitacao_origem']['id']);

        // Nenhuma solicitação nova foi criada.
        $this->assertSame($totalSolicitacoesAntes, Solicitacao::query()->count());

        $itemGeradoId = $criada['itens_selecionados'][0]['item_gerado_id'];
        $itemGerado = $solicitacao->itens()->findOrFail($itemGeradoId);
        $this->assertSame($itemOrigem->id, $itemGerado->renovacao_de_item_id);
        $this->assertNotNull($itemGerado->guia);
        $this->assertSame($criada['itens_selecionados'][0]['guia_gerada_id'], $itemGerado->guia->id);

        // Já gerada: some da fila de elegíveis.
        $this->getJson('/api/antecipacoes/elegiveis')->assertOk()->assertJsonCount(0, 'data');

        $this->deleteJson("/api/antecipacoes/{$criada['id']}")->assertNoContent();
        $this->assertNull(Antecipacao::query()->find($criada['id']));
        // Excluir o registro de acompanhamento não desfaz o item/guia gerados.
        $this->assertNotNull($solicitacao->itens()->find($itemGeradoId));
    }

    public function test_criar_recusa_item_que_nao_pertence_a_solicitacao(): void
    {
        $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();

        $this->postJson('/api/antecipacoes', [
            'solicitacao_origem_id' => $solicitacao->id,
            'itens_selecionados' => [['especialidade_id' => 999999, 'profissional_id' => 999999]],
        ])->assertJsonValidationErrors('itens_selecionados');
    }

    public function test_ignorar_cria_registro_sem_gerar_nada(): void
    {
        $user = $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();

        $ignorada = $this->postJson('/api/antecipacoes/ignorar', [
            'solicitacao_origem_id' => $solicitacao->id,
            'observacoes' => 'Paciente em pausa no tratamento',
        ])->assertCreated()->json('data');

        $this->assertSame('ignorada', $ignorada['status']);
        $this->assertSame($user->id, Antecipacao::query()->findOrFail($ignorada['id'])->criado_por_id);
        $this->assertSame(1, $solicitacao->itens()->count());
    }

    public function test_atualizar_edita_apenas_observacoes(): void
    {
        $user = $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();

        $antecipacao = Antecipacao::query()->create([
            'tenant_id' => $user->tenant_id,
            'solicitacao_origem_id' => $solicitacao->id,
            'status' => Antecipacao::STATUS_IGNORADA,
            'ignorado_em' => now(),
        ]);

        $this->patchJson("/api/antecipacoes/{$antecipacao->id}", ['observacoes' => 'Revisado depois'])
            ->assertOk()
            ->assertJsonPath('data.observacoes', 'Revisado depois')
            ->assertJsonPath('data.status', 'ignorada');
    }

    public function test_sem_permissao_de_ver_a_listagem_recusa(): void
    {
        $user = $this->autenticar();

        Role::query()->where('name', 'admin')->where('tenant_id', $user->tenant_id)
            ->firstOrFail()->revokePermissionTo('antecipacoes.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson('/api/antecipacoes')->assertForbidden();
        $this->getJson('/api/antecipacoes/elegiveis')->assertForbidden();
    }

    public function test_sem_permissao_de_gerir_nao_cria(): void
    {
        $user = $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();

        Role::query()->where('name', 'admin')->where('tenant_id', $user->tenant_id)
            ->firstOrFail()->revokePermissionTo('antecipacoes.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->postJson('/api/antecipacoes', [
            'solicitacao_origem_id' => $solicitacao->id,
            'itens_selecionados' => [['especialidade_id' => 1, 'profissional_id' => 1]],
        ])->assertForbidden();
    }

    /**
     * O registro de antecipação é só acompanhamento: apagá-lo não pode
     * derrubar o que foi gerado. `test_criar_gera_item_e_guia_...` já exclui no
     * final, mas só confere o item sobreviver — a guia, que é o que de fato
     * vai para a operadora, ficava sem ninguém olhando. Aqui num teste próprio,
     * pra falha apontar a exclusão e não a criação.
     */
    public function test_excluir_do_historico_preserva_item_e_guia_gerados(): void
    {
        $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();
        $itemOrigem = $solicitacao->itens()->firstOrFail();

        $criada = $this->postJson('/api/antecipacoes', [
            'solicitacao_origem_id' => $solicitacao->id,
            'itens_selecionados' => [[
                'especialidade_id' => $itemOrigem->especialidade_id,
                'profissional_id' => $itemOrigem->profissional_id,
            ]],
        ])->assertCreated()->json('data');

        $itemGeradoId = (int) $criada['itens_selecionados'][0]['item_gerado_id'];
        $guiaGeradaId = (int) $criada['itens_selecionados'][0]['guia_gerada_id'];
        $this->assertNotNull(Guia::query()->find($guiaGeradaId));

        $this->deleteJson("/api/antecipacoes/{$criada['id']}")->assertNoContent();

        $this->assertNull(Antecipacao::query()->find($criada['id']));
        $this->assertNotNull($solicitacao->itens()->find($itemGeradoId));
        $this->assertNotNull(Guia::query()->find($guiaGeradaId));
    }

    public function test_historico_filtra_por_status(): void
    {
        $user = $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();

        $ignorada = Antecipacao::query()->create([
            'tenant_id' => $user->tenant_id,
            'solicitacao_origem_id' => $solicitacao->id,
            'status' => Antecipacao::STATUS_IGNORADA,
            'ignorado_em' => now(),
        ]);
        $gerada = Antecipacao::query()->create([
            'tenant_id' => $user->tenant_id,
            'solicitacao_origem_id' => $solicitacao->id,
            'status' => Antecipacao::STATUS_GERADA,
            'gerado_em' => now(),
        ]);

        $this->assertSame(
            [$gerada->id],
            $this->getJson('/api/antecipacoes?status=gerada')->assertOk()->json('data.*.id'),
        );
        $this->assertSame(
            [$ignorada->id],
            $this->getJson('/api/antecipacoes?status=ignorada')->assertOk()->json('data.*.id'),
        );

        $todas = $this->getJson('/api/antecipacoes')->assertOk()->json('data.*.id');
        $this->assertCount(2, $todas);
    }

    /**
     * A fila de elegíveis não passa por `Antecipacao` (que tem BelongsToTenant):
     * ela sai de `Guia::elegiveisParaAntecipacao()`, que derruba o TenantScope e
     * filtra por `tenant_id` na mão. Sem um teste direto, uma troca de escopo
     * ali vazaria paciente de outra clínica sem nada reprovar.
     */
    public function test_elegiveis_nao_vaza_solicitacao_de_outro_tenant(): void
    {
        $this->autenticar();
        $minha = $this->solicitacaoComGuiaAprovada();
        $deOutroTenant = $this->solicitacaoElegivelDeOutroTenant();

        $data = $this->getJson('/api/antecipacoes/elegiveis')->assertOk()->json('data');

        $ids = array_column($data, 'solicitacao_id');
        $this->assertContains($minha->id, $ids);
        $this->assertNotContains($deOutroTenant->id, $ids);

        // Sem isto a asserção acima seria vazia: provaria apenas que uma
        // solicitação inelegível não aparece, o que seria verdade mesmo com o
        // escopo de tenant quebrado. Aqui fica dito que ela É elegível — para a
        // clínica dona dela, consultada de dentro do contexto da outra.
        $this->assertNotEmpty(
            Guia::elegiveisParaAntecipacao((int) $deOutroTenant->tenant_id)
                ->where('solicitacao_id', $deOutroTenant->id),
        );
    }

    /** Mesma receita de `solicitacaoComGuiaAprovada()`, num tenant à parte. */
    private function solicitacaoElegivelDeOutroTenant(): Solicitacao
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Vizinha Antec',
            'slug' => 'clinica-vizinha-antec',
            'cnpj' => '44.444.444/0001-44',
            'ativo' => true,
        ]);

        $especialidade = Especialidade::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Fisioterapia Vizinha',
            'ativo' => true,
        ]);
        $profissional = Profissional::query()->create([
            'tenant_id' => $tenant->id,
            'especialidade_id' => $especialidade->id,
            'nome' => 'Dra. Vizinha Antec',
            'conselho_registro' => 'CREFITO 444444-F',
            'ativo' => true,
        ]);
        $convenio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Vizinho Antec',
            'connector_type' => 'manual',
            'connector_config' => null,
            'ativo' => true,
        ]);
        $paciente = Paciente::query()->create([
            'tenant_id' => $tenant->id,
            'convenio_id' => $convenio->id,
            'nome' => 'Paciente Vizinho Antec',
            'carteirinha' => 'VIZ-2026-0001',
            'ativo' => true,
        ]);
        $medico = Medico::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Dr. Vizinho Antec',
            'especialidade_medica' => 'Neurologia',
            'telefone' => '(11) 97777-0001',
            'ativo' => true,
        ]);

        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $tenant->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_id' => $medico->id,
            'status' => 'guia_gerada',
            'solicitado_em' => today()->subDays(30)->toDateString(),
        ]);
        $solicitacao->itens()->create([
            'tenant_id' => $tenant->id,
            'especialidade_id' => $especialidade->id,
            'profissional_id' => $profissional->id,
            'quantidade' => 10,
            'status_operacional' => 'pending',
        ]);

        Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => $solicitacao->id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'ANTEC-VIZINHA-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::APPROVED,
            'data_solicitacao' => today()->subDays(30)->toDateString(),
            // Mesmo cálculo do helper do tenant próprio: com o padrão global de
            // 20 dias sobre a validade da senha, esta guia está devida há 10.
            'validade_senha' => today()->copy()->addDays(10)->toDateString(),
        ]);

        return $solicitacao;
    }
}

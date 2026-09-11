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

    public function test_elegiveis_exclui_solicitacao_com_antecipacao_pendente(): void
    {
        $user = $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();

        Antecipacao::query()->create([
            'tenant_id' => $user->tenant_id,
            'solicitacao_origem_id' => $solicitacao->id,
            'status' => Antecipacao::STATUS_PENDENTE,
            'itens_selecionados' => [['especialidade_id' => 1, 'profissional_id' => 1]],
        ]);

        $data = $this->getJson('/api/antecipacoes/elegiveis')->assertOk()->json('data');

        $this->assertCount(0, $data);
    }

    public function test_cria_atualiza_marca_gerada_e_remove(): void
    {
        $user = $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();
        $item = $solicitacao->itens()->firstOrFail();

        $criada = $this->postJson('/api/antecipacoes', [
            'solicitacao_origem_id' => $solicitacao->id,
            'itens_selecionados' => [[
                'especialidade_id' => $item->especialidade_id,
                'profissional_id' => $item->profissional_id,
            ]],
        ])->assertCreated()->json('data');

        $this->assertSame('pendente', $criada['status']);
        $this->assertSame($user->id, Antecipacao::query()->findOrFail($criada['id'])->criado_por_id);

        $this->patchJson("/api/antecipacoes/{$criada['id']}", ['observacoes' => 'Aguardando confirmação'])
            ->assertOk()
            ->assertJsonPath('data.observacoes', 'Aguardando confirmação');

        $outraSolicitacao = Solicitacao::query()->create([
            'tenant_id' => $user->tenant_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $solicitacao->profissional_id,
            'especialidade_id' => $solicitacao->especialidade_id,
            'convenio_id' => $solicitacao->convenio_id,
            'medico_id' => $solicitacao->medico_id,
            'status' => 'under_review',
            'solicitado_em' => today()->toDateString(),
        ]);

        $this->patchJson("/api/antecipacoes/{$criada['id']}/marcar-gerada", [
            'solicitacao_gerada_id' => $outraSolicitacao->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'gerada')
            ->assertJsonPath('data.solicitacao_gerada.id', $outraSolicitacao->id);

        $this->deleteJson("/api/antecipacoes/{$criada['id']}")->assertNoContent();
        $this->assertNull(Antecipacao::query()->find($criada['id']));
    }

    public function test_atualizar_para_ignorada_carimba_ignorado_em(): void
    {
        $user = $this->autenticar();
        $solicitacao = $this->solicitacaoComGuiaAprovada();

        $antecipacao = Antecipacao::query()->create([
            'tenant_id' => $user->tenant_id,
            'solicitacao_origem_id' => $solicitacao->id,
            'status' => Antecipacao::STATUS_PENDENTE,
            'itens_selecionados' => [['especialidade_id' => 1, 'profissional_id' => 1]],
        ]);

        $this->patchJson("/api/antecipacoes/{$antecipacao->id}", ['status' => 'ignorada'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ignorada');

        $this->assertNotNull($antecipacao->fresh()->ignorado_em);
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
}

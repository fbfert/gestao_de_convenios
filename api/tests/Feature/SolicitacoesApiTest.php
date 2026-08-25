<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SolicitacoesApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cria_lista_e_mostra_solicitacoes(): void
    {
        $this->autenticar();
        $payload = $this->payloadSolicitacao('Unimed');

        $create = $this->postJson('/api/solicitacoes', $payload);

        $create->assertCreated()
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.convenio_id', $payload['convenio_id'])
            ->assertJsonPath('data.itens.0.quantidade', 10);
        $this->assertSame($payload['medico_id'], $create->json('data.medico_id'));
        $this->assertSame('Dr. Carlos Almeida', $create->json('data.medico.nome'));

        $id = $create->json('data.id');
        $tenantId = Tenant::query()->where('slug', 'clinica-exemplo')->value('id');

        $guia = Guia::query()->create([
            'tenant_id' => $tenantId,
            'solicitacao_id' => $id,
            'convenio_id' => $payload['convenio_id'],
            'paciente_id' => $payload['paciente_id'],
            'profissional_id' => $payload['profissional_id'],
            'especialidade_id' => $payload['especialidade_id'],
            'numero_guia' => 'GUIA-SOLICITACAO-'.uniqid(),
            'tipo_terapia' => 'convencional',
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);

        $this->getJson('/api/solicitacoes?status=under_review&convenio_id='.$payload['convenio_id'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.status', 'under_review')
            ->assertJsonPath('data.0.itens.0.profissional_id', $payload['profissional_id'])
            ->assertJsonPath('data.0.guia.id', $guia->id)
            ->assertJsonPath('data.0.guia.numero_guia', $guia->numero_guia);

        $this->getJson("/api/solicitacoes/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.medico.nome', 'Dr. Carlos Almeida')
            ->assertJsonPath('data.itens.0.especialidade_id', $payload['especialidade_id'])
            ->assertJsonPath('data.guia.id', $guia->id);
    }

    public function test_filtra_solicitacoes_por_nome_de_paciente_profissional_e_medico(): void
    {
        $this->autenticar();
        $payload = $this->payloadSolicitacao('Unimed');
        $create = $this->postJson('/api/solicitacoes', $payload);
        $create->assertCreated();
        $id = $create->json('data.id');

        $paciente = Paciente::query()->findOrFail($payload['paciente_id']);
        $profissional = Profissional::query()->findOrFail($payload['profissional_id']);
        $medico = Medico::query()->findOrFail($payload['medico_id']);

        $trechoPaciente = mb_strtolower(mb_substr($paciente->nome, 0, 4));
        $this->getJson('/api/solicitacoes?paciente='.urlencode($trechoPaciente))
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $trechoProfissional = mb_strtolower(mb_substr($profissional->nome, 0, 4));
        $this->getJson('/api/solicitacoes?profissional='.urlencode($trechoProfissional))
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $trechoMedico = mb_strtolower(mb_substr($medico->nome, 0, 4));
        $this->getJson('/api/solicitacoes?medico='.urlencode($trechoMedico))
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->getJson('/api/solicitacoes?paciente=NomeQueNaoExiste12345')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_cria_solicitacao_com_multiplos_itens(): void
    {
        $this->autenticar();
        $payload = $this->payloadSolicitacao('Unimed');
        $outraEspecialidade = Especialidade::query()
            ->where('nome', '!=', 'Fisioterapia')
            ->firstOrFail();
        $outroProfissional = Profissional::query()
            ->where('especialidade_id', $outraEspecialidade->id)
            ->firstOrFail();

        unset($payload['especialidade_id'], $payload['profissional_id']);
        $fisioterapia = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissionalFisioterapia = Profissional::query()
            ->where('especialidade_id', $fisioterapia->id)
            ->firstOrFail();
        $payload['itens'] = [
            [
                'especialidade_id' => $fisioterapia->id,
                'profissional_id' => $profissionalFisioterapia->id,
                'quantidade' => 8,
            ],
            [
                'especialidade_id' => $outraEspecialidade->id,
                'profissional_id' => $outroProfissional->id,
            ],
        ];

        $response = $this->postJson('/api/solicitacoes', $payload);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.itens')
            ->assertJsonPath('data.itens.0.quantidade', 8)
            ->assertJsonPath('data.itens.1.quantidade', 10);

        $this->assertDatabaseHas('solicitacao_itens', [
            'solicitacao_id' => $response->json('data.id'),
            'profissional_id' => $payload['itens'][1]['profissional_id'],
            'quantidade' => 10,
        ]);
    }

    public function test_nao_permite_item_de_outro_tenant_na_criacao(): void
    {
        $this->autenticar();
        $payload = $this->payloadSolicitacao('Unimed');
        $tenant = Tenant::query()->create([
            'nome' => 'Tenant Item Externo',
            'slug' => 'tenant-item-externo',
            'cnpj' => '66.666.666/0001-66',
            'ativo' => true,
        ]);
        $especialidadeExterna = Especialidade::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Especialidade Externa Item',
            'ativo' => true,
        ]);
        $profissionalExterno = Profissional::query()->create([
            'tenant_id' => $tenant->id,
            'especialidade_id' => $especialidadeExterna->id,
            'nome' => 'Profissional Externo Item',
            'conselho_registro' => 'EXT 123',
            'ativo' => true,
        ]);
        unset($payload['especialidade_id'], $payload['profissional_id']);
        $payload['itens'] = [[
            'especialidade_id' => $especialidadeExterna->id,
            'profissional_id' => $profissionalExterno->id,
        ]];

        $this->postJson('/api/solicitacoes', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'itens.0.especialidade_id',
                'itens.0.profissional_id',
            ]);
    }

    public function test_criacao_valida_campos_obrigatorios(): void
    {
        $this->autenticar();

        $this->postJson('/api/solicitacoes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'paciente_id',
                'profissional_id',
                'especialidade_id',
                'convenio_id',
                'medico_id',
                'cid_ids',
                'solicitado_em',
            ]);
    }

    public function test_altera_status_entre_em_analise_pronta_para_automatizacao_e_negada(): void
    {
        $this->autenticar();

        $payloadAprovada = $this->payloadSolicitacao('Unimed');
        $payloadNegada = $this->payloadSolicitacao('SC Saúde');

        $aprovada = $this->postJson('/api/solicitacoes', $payloadAprovada)
            ->assertCreated()
            ->json('data.id');

        $negada = $this->postJson('/api/solicitacoes', $payloadNegada)
            ->assertCreated()
            ->json('data.id');

        $this->patchJson("/api/solicitacoes/{$aprovada}/aprovar")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_automation');

        $guia = Guia::query()->where('solicitacao_id', $aprovada)->firstOrFail();
        $this->assertSame("GUIA-SOLICITACAO-{$aprovada}", $guia->numero_guia);
        $this->assertSame('under_review', $guia->status);
        $this->assertNotNull($guia->solicitacao_item_id);

        $this->getJson('/api/guias?status=under_review&convenio_id='.$payloadAprovada['convenio_id'])
            ->assertOk()
            ->assertJsonPath('data.0.solicitacao_id', $aprovada)
            ->assertJsonPath('data.0.numero_guia', $guia->numero_guia);

        $this->patchJson("/api/solicitacoes/{$negada}/negar")
            ->assertOk()
            ->assertJsonPath('data.status', 'denied');

        $this->patchJson("/api/solicitacoes/{$negada}/status", ['status' => 'ready_for_automation'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_automation');

        $this->assertDatabaseHas('guias', [
            'solicitacao_id' => $negada,
            'numero_guia' => "GUIA-SOLICITACAO-{$negada}",
            'status' => 'under_review',
        ]);

        $this->patchJson("/api/solicitacoes/{$negada}/status", ['status' => 'under_review'])
            ->assertOk()
            ->assertJsonPath('data.status', 'under_review');

        // 'approved' agora é aprovação real (sincronizada a partir da guia) —
        // não é mais um destino que o usuário escolhe manualmente.
        $this->patchJson("/api/solicitacoes/{$negada}/status", ['status' => 'approved'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->patchJson("/api/solicitacoes/{$negada}/status", ['status' => 'canceled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_solicitacao_vira_aprovada_quando_todas_as_guias_dos_itens_sao_finalizadas(): void
    {
        $this->autenticar();
        $payload = $this->payloadSolicitacao('SC Saúde');
        $primeiraEspecialidade = $payload['especialidade_id'];
        $primeiroProfissional = $payload['profissional_id'];
        $outraEspecialidade = Especialidade::query()->where('nome', '!=', 'Fisioterapia')->firstOrFail();
        $outroProfissional = Profissional::query()->where('especialidade_id', $outraEspecialidade->id)->firstOrFail();

        unset($payload['especialidade_id'], $payload['profissional_id']);
        $payload['itens'] = [
            ['especialidade_id' => $primeiraEspecialidade, 'profissional_id' => $primeiroProfissional, 'quantidade' => 5],
            ['especialidade_id' => $outraEspecialidade->id, 'profissional_id' => $outroProfissional->id, 'quantidade' => 5],
        ];

        $id = $this->postJson('/api/solicitacoes', $payload)->assertCreated()->json('data.id');

        $this->patchJson("/api/solicitacoes/{$id}/aprovar")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_automation');

        // Fluxo legado só cria guia pro primeiro item — reproduz manualmente
        // a guia do segundo item, que na prática viria de outro cadastro.
        $solicitacao = Solicitacao::query()->with('itens')->findOrFail($id);
        $itens = $solicitacao->itens;
        $guiaItem1 = Guia::query()->where('solicitacao_item_id', $itens[0]->id)->firstOrFail();
        $guiaItem2 = Guia::query()->create([
            'tenant_id' => $solicitacao->tenant_id,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $itens[1]->id,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $itens[1]->profissional_id,
            'especialidade_id' => $itens[1]->especialidade_id,
            'numero_guia' => 'GUIA-ITEM-2-'.$id,
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
        ]);

        app(\App\Services\GuiaService::class)->finalizar($guiaItem1, ['senha' => 'SENHA-1']);

        // Item 2 já tem guia (ainda under_review) — todo item já com guia,
        // mas nem todas aprovadas: vira 'guia_gerada', não mais
        // 'ready_for_automation' (senão a tela fica presa dizendo "pronta
        // pra automatizar" com a automação já concluída pros dois itens).
        $this->assertSame('guia_gerada', $solicitacao->refresh()->status);

        app(\App\Services\GuiaService::class)->finalizar($guiaItem2, ['senha' => 'SENHA-2']);

        $this->assertSame('approved', $solicitacao->refresh()->status);
    }

    public function test_solicitacao_vira_guia_gerada_so_quando_todos_os_itens_tem_guia(): void
    {
        $this->autenticar();
        $payload = $this->payloadSolicitacao('SC Saúde');
        $primeiraEspecialidade = $payload['especialidade_id'];
        $primeiroProfissional = $payload['profissional_id'];
        $outraEspecialidade = Especialidade::query()->where('nome', '!=', 'Fisioterapia')->firstOrFail();
        $outroProfissional = Profissional::query()->where('especialidade_id', $outraEspecialidade->id)->firstOrFail();

        unset($payload['especialidade_id'], $payload['profissional_id']);
        $payload['itens'] = [
            ['especialidade_id' => $primeiraEspecialidade, 'profissional_id' => $primeiroProfissional, 'quantidade' => 5],
            ['especialidade_id' => $outraEspecialidade->id, 'profissional_id' => $outroProfissional->id, 'quantidade' => 5],
        ];

        $id = $this->postJson('/api/solicitacoes', $payload)->assertCreated()->json('data.id');
        $this->patchJson("/api/solicitacoes/{$id}/aprovar")->assertOk();

        $solicitacao = Solicitacao::query()->with('itens')->findOrFail($id);
        $itens = $solicitacao->itens;
        $guiaItem1 = Guia::query()->where('solicitacao_item_id', $itens[0]->id)->firstOrFail();

        app(\App\Services\GuiaService::class)->finalizar($guiaItem1, ['senha' => 'SENHA-1']);

        // Item 2 nunca teve guia gerada: mesmo com o item 1 finalizado, a
        // solicitação inteira fica em 'ready_for_automation' — ainda falta
        // enviar o segundo item.
        $this->assertSame('ready_for_automation', $solicitacao->refresh()->status);
    }

    public function test_admin_edita_solicitacao_e_fica_registrado_na_auditoria(): void
    {
        $this->autenticar();
        $payload = $this->payloadSolicitacao('Unimed');
        $id = $this->postJson('/api/solicitacoes', $payload)->assertCreated()->json('data.id');

        $outroMedico = Medico::query()->where('id', '!=', $payload['medico_id'])->firstOrFail();
        $outroCid = Cid::query()->where('codigo', 'F80.1')->firstOrFail();

        $this->patchJson("/api/solicitacoes/{$id}", [
            'medico_id' => $outroMedico->id,
            'cid_ids' => [$outroCid->id],
            'observacoes' => 'Corrigido pelo admin',
        ])
            ->assertOk()
            ->assertJsonPath('data.medico_id', $outroMedico->id)
            ->assertJsonPath('data.cids.0.codigo', 'F80.1')
            // paciente_id/convenio_id nao fazem parte do payload aceito: continuam os mesmos.
            ->assertJsonPath('data.paciente_id', $payload['paciente_id'])
            ->assertJsonPath('data.convenio_id', $payload['convenio_id']);

        $evento = AuditLog::query()
            ->where('entidade', 'solicitacoes')
            ->where('entidade_id', $id)
            ->where('acao', 'updated')
            ->latest('id')
            ->firstOrFail();

        // CID agora e N-pra-N via tabela pivo (cid_solicitacao) — nao e mais
        // coluna do model, entao o Auditable (que so ve getChanges() do
        // proprio model) nao registra essa troca aqui. So medico_id continua
        // sendo coluna de verdade.
        $this->assertSame($payload['medico_id'], $evento->payload['antes']['medico_id']);
        $this->assertSame($outroMedico->id, $evento->payload['depois']['medico_id']);
        $this->assertTrue(
            $outroCid->solicitacoes()->where('solicitacoes.id', $id)->exists(),
            'esperava a solicitacao vinculada ao novo CID via pivo',
        );
    }

    public function test_funcionario_nao_pode_editar_solicitacao(): void
    {
        $this->autenticar();
        $id = $this->postJson('/api/solicitacoes', $this->payloadSolicitacao('Unimed'))
            ->assertCreated()
            ->json('data.id');

        $funcionario = User::query()->where('email', 'funcionario@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($funcionario);

        $this->patchJson("/api/solicitacoes/{$id}", ['cid_ids' => [Cid::query()->where('codigo', 'F84.0')->firstOrFail()->id]])->assertForbidden();
    }

    public function test_usuariode_um_tenant_nao_enxerga_solicitacao_de_outro_tenant_via_http(): void
    {
        $solicitacaoOutroTenant = $this->criarSolicitacaoDeOutroTenant();

        $this->autenticar();

        $this->getJson('/api/solicitacoes/'.$solicitacaoOutroTenant->id)
            ->assertNotFound();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function payloadSolicitacao(string $convenioNome): array
    {
        $convenio = Convenio::query()->where('nome', $convenioNome)->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $medico = Medico::query()->where('nome', 'Dr. Carlos Almeida')->firstOrFail();

        return [
            'paciente_id' => $this->pacienteIdPorConvenio($convenio->id),
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_id' => $medico->id,
            'cid_ids' => [Cid::query()->where('codigo', 'F84.0')->firstOrFail()->id],
            'solicitado_em' => today()->toDateString(),
        ];
    }

    private function pacienteIdPorConvenio(int $convenioId): int
    {
        return Paciente::query()->where('convenio_id', $convenioId)->firstOrFail()->id;
    }

    private function criarSolicitacaoDeOutroTenant(): Solicitacao
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa',
            'slug' => 'clinica-externa',
            'cnpj' => '98.765.432/0001-10',
            'ativo' => true,
        ]);

        $especialidade = Especialidade::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Fisioterapia Externa',
            'ativo' => true,
        ]);

        $profissional = Profissional::query()->create([
            'tenant_id' => $tenant->id,
            'especialidade_id' => $especialidade->id,
            'nome' => 'Dra. Externa',
            'conselho_registro' => 'CREFITO 999999-F',
            'ativo' => true,
        ]);

        $convenio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Externo',
            'connector_type' => 'manual',
            'connector_config' => null,
            'ativo' => true,
        ]);

        $paciente = Paciente::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Paciente Externo',
            'cpf' => '12345678909',
            'carteirinha' => 'EXT-0001',
            'convenio_id' => $convenio->id,
            'telefone' => '(11) 90000-0000',
            'clinica_agil_id' => null,
            'ativo' => true,
        ]);

        return Solicitacao::query()->create([
            'tenant_id' => $tenant->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_id' => Medico::query()->create([
                'tenant_id' => $tenant->id,
                'nome' => 'Dr. Externo',
                'crm' => 'CRM 999999',
                'especialidade_medica' => 'Clínica Geral',
                'telefone' => '(11) 90000-0000',
                'email' => 'externo@clinica-externa.test',
                'ativo' => true,
            ])->id,
            'status' => 'under_review',
            'solicitado_em' => today(),
            'observacoes' => null,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AutomacaoExecucao;
use App\Models\Convenio;
use App\Models\ConciliacaoFinanceira;
use App\Models\Antecipacao;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\SolicitacaoItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuiasApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cria_lista_mostra_finaliza_e_nega_guias(): void
    {
        $this->autenticar();

        $payload = $this->payloadGuia('Unimed');
        $create = $this->postJson('/api/guias', $payload);

        $create->assertCreated()
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.numero_guia', $payload['numero_guia']);

        $id = $create->json('data.id');

        $this->getJson('/api/guias?status=under_review&convenio_id='.$payload['convenio_id'].'&paciente_id='.$payload['paciente_id'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->getJson("/api/guias/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'under_review');

        $this->patchJson("/api/guias/{$id}/finalizar", [
            'senha' => 'ABC123',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.senha', 'ABC123');

        $this->getJson('/api/guias?status=finalized&validade_senha_vencendo_em_dias=30')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->patchJson("/api/guias/{$id}/negar", [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Transição inválida de guia: finalized -> denied.');
    }

    public function test_filtra_guias_por_profissional_e_por_nome_do_paciente(): void
    {
        $this->autenticar();

        $payload = $this->payloadGuia('Unimed');
        $id = $this->postJson('/api/guias', $payload)
            ->assertCreated()
            ->json('data.id');

        $outroPaciente = Paciente::query()
            ->where('convenio_id', $payload['convenio_id'])
            ->where('id', '!=', $payload['paciente_id'])
            ->firstOrFail();

        $outraGuia = $this->payloadGuia('Unimed', 'Fonoaudiologia');
        $outraGuia['paciente_id'] = $outroPaciente->id;
        $outraId = $this->postJson('/api/guias', $outraGuia)
            ->assertCreated()
            ->json('data.id');

        $paciente = Paciente::query()->findOrFail($payload['paciente_id']);

        $porProfissional = $this->getJson('/api/guias?profissional_id='.$payload['profissional_id'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->json('data.*.id');
        $this->assertNotContains($outraId, $porProfissional);

        $porNomePaciente = $this->getJson('/api/guias?paciente_nome='.urlencode(mb_substr($paciente->nome, 0, 4)))
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->json('data.*.id');
        $this->assertNotContains($outraId, $porNomePaciente);
    }

    public function test_finaliza_via_http_sem_validade_senha_calculando_data_automaticamente(): void
    {
        $this->autenticar();

        $guia = $this->postJson('/api/guias', $this->payloadGuia('Unimed'))
            ->assertCreated()
            ->json('data.id');

        $response = $this->patchJson("/api/guias/{$guia}/finalizar", [
            'senha' => 'ABC123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.senha', 'ABC123')
            ->assertJsonPath('data.validade_senha', today()->copy()->addDays(30)->toDateString());
    }

    public function test_filtro_de_validade_senha_vencendo_em_dias_funciona(): void
    {
        $this->autenticar();

        $guiaCurta = $this->postJson('/api/guias', $this->payloadGuia('Unimed'))
            ->assertCreated()
            ->json('data.id');

        $guiaLonga = $this->postJson('/api/guias', $this->payloadGuia('SC Saúde'))
            ->assertCreated()
            ->json('data.id');

        $this->patchJson("/api/guias/{$guiaCurta}/finalizar", [
            'senha' => 'CURTA123',
            'validade_senha' => today()->copy()->addDays(3)->toDateString(),
        ])->assertOk();

        $this->patchJson("/api/guias/{$guiaLonga}/finalizar", [
            'senha' => 'LONGA123',
            'validade_senha' => today()->copy()->addDays(30)->toDateString(),
        ])->assertOk();

        $response = $this->getJson('/api/guias?status=finalized&validade_senha_vencendo_em_dias=7');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $guiaCurta)
            ->assertJsonMissing(['id' => $guiaLonga]);
    }

    public function test_alerta_negacao_lista_so_negadas_nao_ocultadas_e_oculta_via_http(): void
    {
        $this->autenticar();

        $idNegada = $this->postJson('/api/guias', $this->payloadGuia('Unimed'))->assertCreated()->json('data.id');
        $idAprovada = $this->postJson('/api/guias', $this->payloadGuia('SC Saúde'))->assertCreated()->json('data.id');

        $this->patchJson("/api/guias/{$idNegada}/negar", [])->assertOk()->assertJsonPath('data.status', 'denied');
        $this->patchJson("/api/guias/{$idAprovada}/finalizar", ['senha' => 'ABC123'])->assertOk();

        $this->getJson('/api/guias?alerta_negacao_pendente=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $idNegada)
            ->assertJsonMissing(['id' => $idAprovada]);

        $this->patchJson("/api/guias/{$idNegada}/ocultar-alerta-negacao", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'denied');

        $this->getJson('/api/guias?alerta_negacao_pendente=1')
            ->assertOk()
            ->assertJsonMissing(['id' => $idNegada]);
    }

    public function test_alerta_negacao_nao_lista_guia_historica(): void
    {
        $this->autenticar();
        $tenantId = Tenant::query()->where('slug', 'clinica-exemplo')->value('id');

        $idNegada = $this->postJson('/api/guias', $this->payloadGuia('Unimed'))->assertCreated()->json('data.id');
        $this->patchJson("/api/guias/{$idNegada}/negar", [])->assertOk();

        $guia = Guia::query()->findOrFail($idNegada);
        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $tenantId,
            'paciente_id' => $guia->paciente_id,
            'profissional_id' => $guia->profissional_id,
            'especialidade_id' => $guia->especialidade_id,
            'convenio_id' => $guia->convenio_id,
            'medico_id' => null,
            'status' => 'historico',
            'solicitado_em' => today(),
            'observacoes' => 'Rastro histórico de teste',
        ]);
        $item = SolicitacaoItem::query()->create([
            'tenant_id' => $tenantId,
            'solicitacao_id' => $solicitacao->id,
            'especialidade_id' => $guia->especialidade_id,
            'profissional_id' => $guia->profissional_id,
            'quantidade' => 10,
            'status_operacional' => 'guia_generated',
        ]);
        $guia->forceFill(['solicitacao_id' => $solicitacao->id, 'solicitacao_item_id' => $item->id])->save();

        // Negada e pendente de alerta, mas histórica: não é uma negação de
        // verdade pra alguém agir agora, é passado migrado — não deve
        // aparecer pedindo "nova solicitação".
        $this->getJson('/api/guias?alerta_negacao_pendente=1')
            ->assertOk()
            ->assertJsonMissing(['id' => $idNegada]);
    }

    public function test_status_historico_fica_fora_da_listagem_padrao_e_so_aparece_com_mostrar_historico(): void
    {
        $this->autenticar();

        $normal = $this->postJson('/api/guias', $this->payloadGuia('Unimed'))->assertCreated()->json('data.id');
        $historica = $this->postJson('/api/guias', $this->payloadGuia('SC Saúde'))->assertCreated()->json('data.id');
        Guia::query()->whereKey($historica)->update(['status' => 'historico_denied']);

        // assertJsonMissing/assertJsonCount não servem aqui: o tenant pode
        // ter outras guias de seed, e um fragmento {"id": N} pode bater sem
        // querer com um id aninhado (convênio, paciente...). Confere pelos
        // ids de fato retornados na lista.
        $padrao = collect($this->getJson('/api/guias?per_page=100')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($padrao->contains($normal));
        $this->assertFalse($padrao->contains($historica));

        $comHistorico = collect($this->getJson('/api/guias?mostrar_historico=1&per_page=100')->assertOk()->json('data'));
        $this->assertFalse($comHistorico->pluck('id')->contains($normal));
        $this->assertTrue($comHistorico->pluck('id')->contains($historica));
        $this->assertSame('historico_denied', $comHistorico->firstWhere('id', $historica)['status']);
    }

    public function test_historico_mostra_mesmo_a_que_ainda_esta_a_definir(): void
    {
        $this->autenticar();
        $tenantId = Tenant::query()->where('slug', 'clinica-exemplo')->value('id');

        $especialidadeADefinir = Especialidade::query()->create(['tenant_id' => $tenantId, 'nome' => 'A DEFINIR', 'ativo' => true]);
        $profissionalADefinir = Profissional::query()->create([
            'tenant_id' => $tenantId,
            'especialidade_id' => $especialidadeADefinir->id,
            'nome' => 'A DEFINIR',
            'conselho_registro' => null,
            'ativo' => true,
        ]);

        $payload = $this->payloadGuia('Unimed');
        $payload['especialidade_id'] = $especialidadeADefinir->id;
        $payload['profissional_id'] = $profissionalADefinir->id;
        $historicaADefinir = $this->postJson('/api/guias', $payload)->assertCreated()->json('data.id');
        Guia::query()->whereKey($historicaADefinir)->update(['status' => 'historico_approved']);

        // Antes da correção: o filtro padrão de A DEFINIR (ligado o tempo
        // todo, exceto quando A DEFINIR é o que se está pedindo) escondia
        // justamente as guias históricas que mais precisam de revisão.
        $comHistorico = collect($this->getJson('/api/guias?mostrar_historico=1&per_page=100')->assertOk()->json('data'));
        $this->assertTrue($comHistorico->pluck('id')->contains($historicaADefinir));

        // E o botão "Mostrar guias A DEFINIR" sozinho (sem Histórico) não
        // precisa trazer as históricas — cada botão cobre seu próprio caso.
        $soADefinir = collect($this->getJson('/api/guias?mostrar_a_definir=1&per_page=100')->assertOk()->json('data'));
        $this->assertFalse($soADefinir->pluck('id')->contains($historicaADefinir));
    }

    public function test_detalhe_expoe_relacionamentos_da_guia(): void
    {
        $this->autenticar();

        $guia = Guia::query()->create([
            'tenant_id' => Tenant::query()->where('slug', 'clinica-exemplo')->value('id'),
            ...$this->payloadGuia('Unimed'),
            'status' => 'finalized',
            'data_finalizacao' => today(),
            'senha' => 'DETALHE123',
            'validade_senha' => today()->addDays(7),
            'observacoes' => null,
        ]);

        $antecipacao = Antecipacao::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'paciente_id' => $guia->paciente_id,
            'convenio_id' => $guia->convenio_id,
            'ciclo_inicio' => today(),
            'ciclo_fim' => today(),
            'qtd_autorizada' => 10,
            'qtd_utilizada' => 3,
            'status' => 'open',
        ]);

        $conciliacao = ConciliacaoFinanceira::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'profissional_id' => $guia->profissional_id,
            'quantidade' => 3,
            'valor_unitario' => 100,
            'valor_total' => 300,
            'status' => 'pending',
        ]);

        $this->getJson("/api/guias/{$guia->id}")
            ->assertOk()
            ->assertJsonPath('data.paciente.id', $guia->paciente_id)
            ->assertJsonPath('data.paciente.carteirinha', Paciente::query()->findOrFail($guia->paciente_id)->carteirinha)
            ->assertJsonPath('data.convenio.id', $guia->convenio_id)
            ->assertJsonPath('data.profissional.id', $guia->profissional_id)
            ->assertJsonPath('data.especialidade.id', $guia->especialidade_id)
            ->assertJsonPath('data.antecipacoes.0.id', $antecipacao->id)
            ->assertJsonPath('data.antecipacoes.0.qtd_utilizada', 3)
            ->assertJsonPath('data.conciliacoes.0.id', $conciliacao->id)
            ->assertJsonPath('data.conciliacoes.0.status', 'pending');
    }

    public function test_detalhe_expoe_medico_solicitante_e_estrategia_unimed(): void
    {
        $this->autenticar();

        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $payload = $this->payloadGuia('Unimed');

        $medico = Medico::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Dr. Solicitante Teste',
            'crm' => '11223',
            'crm_uf' => 'SC',
            'especialidade_medica' => 'Clínica Geral',
            'telefone' => '(47) 90000-0000',
            'ativo' => true,
        ]);

        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $tenant->id,
            'paciente_id' => $payload['paciente_id'],
            'profissional_id' => $payload['profissional_id'],
            'especialidade_id' => $payload['especialidade_id'],
            'convenio_id' => $payload['convenio_id'],
            'medico_id' => $medico->id,
            'status' => 'under_review',
            'solicitado_em' => today(),
        ]);

        $execucao = AutomacaoExecucao::query()->create([
            'tenant_id' => $tenant->id,
            'operacao' => 'gerar_guia_unimed',
            'status' => 'succeeded',
            'idempotency_key' => 'test-'.uniqid(),
            'resultado' => ['status' => 'succeeded', 'medico_strategy' => 'nao_cooperado'],
        ]);

        $guia = Guia::query()->create([
            'tenant_id' => $tenant->id,
            ...$payload,
            'solicitacao_id' => $solicitacao->id,
            'automacao_execucao_id' => $execucao->id,
            'status' => 'under_review',
        ]);

        $this->getJson("/api/guias/{$guia->id}")
            ->assertOk()
            ->assertJsonPath('data.medico_solicitante.id', $medico->id)
            ->assertJsonPath('data.medico_solicitante.nome', 'Dr. Solicitante Teste')
            ->assertJsonPath('data.medico_solicitante.crm', '11223')
            ->assertJsonPath('data.medico_solicitante.crm_uf', 'SC')
            ->assertJsonPath('data.medico_unimed_strategy', 'nao_cooperado');
    }

    public function test_criacao_valida_campos_obrigatorios(): void
    {
        $this->autenticar();

        $this->postJson('/api/guias', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'convenio_id',
                'paciente_id',
                'profissional_id',
                'especialidade_id',
                'tipo_terapia',
                'data_solicitacao',
            ]);
    }

    public function test_profissional_so_enxerga_guias_proprias_na_listagem(): void
    {
        $user = $this->autenticarProfissional();
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $profissionalProprio = Profissional::query()->findOrFail($user->profissional_id);
        $profissionalOutro = Profissional::query()->where('id', '!=', $profissionalProprio->id)->firstOrFail();

        $guiaPropria = Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => $this->convenioIdPorNome('Unimed'),
            'paciente_id' => $this->pacienteIdPorConvenio($this->convenioIdPorNome('Unimed')),
            'profissional_id' => $profissionalProprio->id,
            'especialidade_id' => $profissionalProprio->especialidade_id,
            'numero_guia' => 'GUIA-PRIVADA-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);

        $guiaOutra = Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => $this->convenioIdPorNome('SC Saúde'),
            'paciente_id' => $this->pacienteIdPorConvenio($this->convenioIdPorNome('SC Saúde')),
            'profissional_id' => $profissionalOutro->id,
            'especialidade_id' => $profissionalOutro->especialidade_id,
            'numero_guia' => 'GUIA-PRIVADA-'.uniqid(),
            'tipo_terapia' => 'convencional',
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);

        $this->getJson('/api/guias')
            ->assertOk()
            ->assertJsonFragment(['id' => $guiaPropria->id])
            ->assertJsonMissing(['id' => $guiaOutra->id]);
    }

    public function test_admin_edita_guia_e_fica_registrado_na_auditoria(): void
    {
        $this->autenticar();
        $payload = $this->payloadGuia('Unimed');
        $id = $this->postJson('/api/guias', $payload)->assertCreated()->json('data.id');

        $this->patchJson("/api/guias/{$id}", [
            'numero_guia' => 'GUIA-CORRIGIDA-1',
            'senha' => 'SENHACORRIGIDA',
            'observacoes' => 'Corrigido pelo admin',
        ])
            ->assertOk()
            ->assertJsonPath('data.numero_guia', 'GUIA-CORRIGIDA-1')
            ->assertJsonPath('data.senha', 'SENHACORRIGIDA')
            // status/paciente/convenio nao fazem parte do payload aceito: continuam os mesmos.
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.paciente_id', $payload['paciente_id']);

        $evento = AuditLog::query()
            ->where('entidade', 'guias')
            ->where('entidade_id', $id)
            ->where('acao', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($payload['numero_guia'], $evento->payload['antes']['numero_guia']);
        $this->assertSame('GUIA-CORRIGIDA-1', $evento->payload['depois']['numero_guia']);
    }

    public function test_admin_pode_editar_guia_mesmo_ja_finalizada(): void
    {
        $this->autenticar();
        $id = $this->postJson('/api/guias', $this->payloadGuia('Unimed'))->assertCreated()->json('data.id');

        $this->patchJson("/api/guias/{$id}/finalizar", ['senha' => 'ABC123'])->assertOk();

        $this->patchJson("/api/guias/{$id}", ['protocolo_operadora' => 'PROTOCOLO-999'])
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.protocolo_operadora', 'PROTOCOLO-999');
    }

    public function test_funcionario_nao_pode_editar_guia(): void
    {
        $this->autenticar();
        $id = $this->postJson('/api/guias', $this->payloadGuia('Unimed'))->assertCreated()->json('data.id');

        $funcionario = User::query()->where('email', 'funcionario@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($funcionario);

        $this->patchJson("/api/guias/{$id}", ['numero_guia' => 'NAO-DEVERIA'])->assertForbidden();
    }

    public function test_usuario_sem_permissao_recebe_403_na_listagem(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Sem Permissão Guia',
            'email' => 'sempermissao.guias@clinica-exemplo.test',
            'password' => 'password',
            'ativo' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/guias')
            ->assertForbidden();
    }

    public function test_usuario_de_um_tenant_nao_enxerga_guia_de_outro_tenant_via_http(): void
    {
        $guiaOutroTenant = $this->criarGuiaDeOutroTenant();

        $this->autenticar();

        $this->getJson('/api/guias/'.$guiaOutroTenant->id)
            ->assertNotFound();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function autenticarProfissional(): User
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function payloadGuia(string $convenioNome, string $especialidadeNome = 'Fisioterapia'): array
    {
        $convenio = Convenio::query()->where('nome', $convenioNome)->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', $especialidadeNome)->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();

        return [
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $this->pacienteIdPorConvenio($convenio->id),
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-API-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'data_solicitacao' => today()->toDateString(),
        ];
    }

    private function convenioIdPorNome(string $nome): int
    {
        return Convenio::query()->where('nome', $nome)->firstOrFail()->id;
    }

    private function pacienteIdPorConvenio(int $convenioId): int
    {
        return Paciente::query()->where('convenio_id', $convenioId)->firstOrFail()->id;
    }

    private function criarGuiaDeOutroTenant(): Guia
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa Guías',
            'slug' => 'clinica-externa-guias',
            'cnpj' => '77.777.777/0001-77',
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
            'nome' => 'Dra. Guia Externa',
            'conselho_registro' => 'CREFITO 888888-F',
            'ativo' => true,
        ]);

        $convenio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Externo Guia',
            'connector_type' => 'manual',
            'connector_config' => null,
            'ativo' => true,
        ]);

        $paciente = Paciente::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Paciente Externo Guia',
            'cpf' => '12345678908',
            'carteirinha' => 'EXT-G-0001',
            'convenio_id' => $convenio->id,
            'telefone' => '(11) 90000-0001',
            'clinica_agil_id' => null,
            'ativo' => true,
        ]);

        return Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-EXTERNO-001',
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);
    }
}

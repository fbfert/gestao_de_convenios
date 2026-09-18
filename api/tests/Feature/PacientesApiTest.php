<?php

namespace Tests\Feature;

use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PacientesApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lista_e_busca_funcionam(): void
    {
        $this->autenticar();

        $this->getJson('/api/pacientes')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Ana Paula Ribeiro')
            ->assertJsonPath('data.0.convenio.nome', 'Unimed');

        $this->getJson('/api/pacientes?busca=Felipe')
            ->assertOk()
            ->assertJsonFragment(['nome' => 'Felipe Gomes Nogueira'])
            ->assertJsonMissing(['nome' => 'Ana Paula Ribeiro']);
    }

    public function test_cria_atualiza_e_desativa_paciente(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente CRUD Local',
            'cpf' => '99988877714',
            'carteirinha' => 'PAC-CRUD-0001',
            'convenio_id' => $convenio->id,
            'telefones' => [
                ['numero' => '(11) 90000-0001', 'rotulo' => 'celular', 'contato_nome' => 'Maria'],
                ['numero' => '1133330002', 'rotulo' => 'fixo', 'principal' => true],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Paciente CRUD Local')
            ->assertJsonPath('data.convenio.id', $convenio->id)
            // Guardado so com digitos, como o CPF: mascara e assunto de tela.
            ->assertJsonPath('data.telefones.0.numero', '11900000001')
            ->assertJsonPath('data.telefones.0.contato_nome', 'Maria')
            ->assertJsonPath('data.telefones.1.principal', true)
            ->assertJsonPath('data.ativo', true);

        $paciente = Paciente::query()->where('carteirinha', 'PAC-CRUD-0001')->firstOrFail();

        $this->patchJson("/api/pacientes/{$paciente->id}", [
            'nome' => 'Paciente CRUD Atualizado',
            'telefones' => [
                ['numero' => '(11) 90000-0099', 'rotulo' => 'whatsapp'],
            ],
            'ativo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Paciente CRUD Atualizado')
            // A lista e regravada inteira: o que sumiu da tela some do banco.
            ->assertJsonCount(1, 'data.telefones')
            ->assertJsonPath('data.telefones.0.numero', '11900000099')
            ->assertJsonPath('data.telefones.0.principal', true)
            ->assertJsonPath('data.ativo', false);
    }

    public function test_nao_permite_convenio_de_outro_tenant_na_criacao(): void
    {
        $convenioExterno = $this->criarConvenioExterno();

        $this->autenticar();

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente Inválido',
            'cpf' => '11122233396',
            'carteirinha' => 'PAC-INV-0001',
            'convenio_id' => $convenioExterno->id,
            'telefone' => '(11) 90000-0002',
            'clinica_agil_id' => 'CA-INV-0001',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['convenio_id']);
    }

    public function test_pagina_pacientes_quando_page_informado(): void
    {
        $this->autenticar();

        $total = Paciente::query()->count();
        $perPage = 4;
        $ultimaPagina = (int) ceil($total / $perPage);
        $naUltimaPagina = $total - ($ultimaPagina - 1) * $perPage;

        // Sem `page`: mantém o comportamento de sempre, lista inteira.
        $this->getJson('/api/pacientes')
            ->assertOk()
            ->assertJsonCount($total, 'data')
            ->assertJsonMissingPath('meta');

        // Com `page`: pagina.
        $this->getJson("/api/pacientes?page=1&per_page={$perPage}")
            ->assertOk()
            ->assertJsonCount($perPage, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', $ultimaPagina)
            ->assertJsonPath('meta.total', $total);

        $this->getJson("/api/pacientes?page={$ultimaPagina}&per_page={$perPage}")
            ->assertOk()
            ->assertJsonCount($naUltimaPagina, 'data');
    }

    public function test_recentes_lista_pacientes_das_solicitacoes_mais_recentes(): void
    {
        $this->autenticar();

        $this->postJson('/api/solicitacoes', $this->payloadSolicitacao('Ana Paula Ribeiro'))
            ->assertCreated();
        $this->postJson('/api/solicitacoes', $this->payloadSolicitacao('Bruno Henrique Lima'))
            ->assertCreated();

        // O mais recente (Bruno) vem antes do mais antigo (Ana) — e um
        // paciente sem nenhuma solicitação (Felipe) não aparece.
        $this->getJson('/api/pacientes/recentes')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Bruno Henrique Lima')
            ->assertJsonPath('data.1.nome', 'Ana Paula Ribeiro')
            ->assertJsonMissing(['nome' => 'Felipe Gomes Nogueira']);
    }

    public function test_recentes_filtra_por_convenio_quando_informado(): void
    {
        $this->autenticar();

        $this->postJson('/api/solicitacoes', $this->payloadSolicitacao('Ana Paula Ribeiro'))
            ->assertCreated();

        $celos = Convenio::query()->where('nome', 'Celos')->firstOrFail();

        $this->getJson("/api/pacientes/recentes?convenio_id={$celos->id}")
            ->assertOk()
            ->assertJsonMissing(['nome' => 'Ana Paula Ribeiro']);
    }

    public function test_isolamento_cross_tenant_em_detalhe_e_atualizacao(): void
    {
        $pacienteExterno = $this->criarPacienteExterno();

        $this->autenticar();

        $this->getJson("/api/pacientes/{$pacienteExterno->id}")
            ->assertNotFound();

        $this->patchJson("/api/pacientes/{$pacienteExterno->id}", [
            'ativo' => false,
        ])
            ->assertNotFound();
    }

    /**
     * O papel `profissional` tem apenas `guias.viewOwn`, `lancamentos.viewOwn`
     * e `conciliacoes.viewOwn` — por desenho, ele vê só o que é dele. As rotas
     * de paciente não checavam permissão nenhuma, então bastava o token dele
     * para varrer a base inteira da clínica: `PacienteResource` devolve CPF,
     * carteirinha e data de nascimento. Escrita e exclusão de anexo idem.
     */
    public function test_profissional_nao_alcanca_a_base_de_pacientes(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $paciente = Paciente::query()->firstOrFail();

        $this->getJson('/api/pacientes')->assertForbidden();
        $this->getJson('/api/pacientes/recentes')->assertForbidden();
        $this->getJson("/api/pacientes/{$paciente->id}")->assertForbidden();
        $this->getJson("/api/pacientes/{$paciente->id}/arquivos")->assertForbidden();

        $this->postJson('/api/pacientes', [])->assertForbidden();
        $this->patchJson("/api/pacientes/{$paciente->id}", ['nome' => 'Alterado'])->assertForbidden();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function payloadSolicitacao(string $pacienteNome): array
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $medico = Medico::query()->where('nome', 'Carlos Almeida')->firstOrFail();
        $paciente = Paciente::query()->where('nome', $pacienteNome)->firstOrFail();

        return [
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_id' => $medico->id,
            'cid_ids' => [Cid::query()->where('codigo', 'F84.0')->firstOrFail()->id],
            'solicitado_em' => today()->toDateString(),
        ];
    }

    private function criarTenantExterno(): Tenant
    {
        return Tenant::query()->create([
            'nome' => 'Clínica Externa Pacientes',
            'slug' => 'clinica-externa-pacientes',
            'cnpj' => '22.222.222/0001-22',
            'ativo' => true,
        ]);
    }

    private function criarPacienteExterno(): Paciente
    {
        $convenio = $this->criarConvenioExterno();

        return Paciente::query()->create([
            'tenant_id' => $convenio->tenant_id,
            'nome' => 'Paciente Externo CRUD',
            'cpf' => '22233344405',
            'carteirinha' => 'PAC-EXT-0001',
            'convenio_id' => $convenio->id,
            'telefone' => '(11) 90000-0003',
            'clinica_agil_id' => 'CA-EXT-0001',
            'ativo' => true,
        ]);
    }

    private function criarConvenioExterno(): Convenio
    {
        $tenant = $this->criarTenantExterno();

        return Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Externo Pacientes',
            'connector_type' => 'manual',
            'connector_config' => null,
            'ativo' => true,
        ]);
    }

    /**
     * Inativo fica de fora quando ninguem pede status.
     *
     * E o caminho dos SELECTS de formulario: `usePacientes` chama `/pacientes`
     * sem `status`, e ate 18/09/2026 recebia inativo junto — dava para abrir
     * guia e solicitacao no nome de quem a clinica ja desligou.
     */
    public function test_listagem_sem_status_esconde_inativo(): void
    {
        $this->autenticar();

        $paciente = Paciente::query()->firstOrFail();
        $paciente->update(['ativo' => false]);

        $ids = $this->getJson('/api/pacientes')->assertOk()->json('data.*.id');

        $this->assertNotContains($paciente->id, $ids);

        // E a assercao que prova que a listagem nao voltou vazia por outro
        // motivo: sem ela, um filtro quebrado passaria igual.
        $this->assertNotEmpty($ids);
    }

    /** A busca por nome usa o mesmo default — e o que o select do formulario faz ao digitar. */
    public function test_busca_por_nome_sem_status_nao_acha_inativo(): void
    {
        $this->autenticar();

        $paciente = Paciente::query()->firstOrFail();
        $paciente->update(['ativo' => false]);

        $ids = $this->getJson('/api/pacientes?busca='.urlencode($paciente->nome))
            ->assertOk()->json('data.*.id');

        $this->assertNotContains($paciente->id, $ids);
    }

    /** `todos` e o pedido explicito da tela de gestao, a unica que precisa ver os dois. */
    public function test_status_todos_traz_ativo_e_inativo(): void
    {
        $this->autenticar();

        $inativo = Paciente::query()->firstOrFail();
        $inativo->update(['ativo' => false]);
        $ativo = Paciente::query()->where('id', '!=', $inativo->id)->firstOrFail();
        $ativo->update(['ativo' => true]);

        $ids = $this->getJson('/api/pacientes?status=todos')->assertOk()->json('data.*.id');

        $this->assertContains($inativo->id, $ids);
        $this->assertContains($ativo->id, $ids);
    }

    public function test_status_inativos_traz_somente_inativo(): void
    {
        $this->autenticar();

        $inativo = Paciente::query()->firstOrFail();
        $inativo->update(['ativo' => false]);
        $ativo = Paciente::query()->where('id', '!=', $inativo->id)->firstOrFail();
        $ativo->update(['ativo' => true]);

        $ids = $this->getJson('/api/pacientes?status=inativos')->assertOk()->json('data.*.id');

        $this->assertSame([$inativo->id], $ids);
    }

    /** Reativar devolve o paciente as listagens que escondem inativo. */
    public function test_paciente_reativado_volta_a_aparecer(): void
    {
        $this->autenticar();

        $paciente = Paciente::query()->firstOrFail();
        $paciente->update(['ativo' => false]);

        $this->assertNotContains(
            $paciente->id,
            $this->getJson('/api/pacientes')->assertOk()->json('data.*.id'),
        );

        $paciente->update(['ativo' => true]);

        $this->assertContains(
            $paciente->id,
            $this->getJson('/api/pacientes')->assertOk()->json('data.*.id'),
        );
    }

    /**
     * Os vizinhos ja escondiam inativo — este teste existe para a correcao de
     * pacientes nao deixar um certo e o resto errado sem ninguem notar.
     */
    public function test_listagens_de_referencia_escondem_inativo_por_padrao(): void
    {
        $this->autenticar();

        $profissional = Profissional::query()->firstOrFail();
        $profissional->update(['ativo' => false]);
        $this->assertNotContains(
            $profissional->id,
            $this->getJson('/api/profissionais')->assertOk()->json('data.*.id'),
        );

        $medico = Medico::query()->firstOrFail();
        $medico->update(['ativo' => false]);
        $this->assertNotContains(
            $medico->id,
            $this->getJson('/api/medicos')->assertOk()->json('data.*.id'),
        );

        $especialidade = Especialidade::query()->firstOrFail();
        $especialidade->update(['ativo' => false]);
        $this->assertNotContains(
            $especialidade->id,
            $this->getJson('/api/especialidades')->assertOk()->json('data.*.id'),
        );

        $convenio = Convenio::query()->firstOrFail();
        $convenio->update(['ativo' => false]);
        $this->assertNotContains(
            $convenio->id,
            $this->getJson('/api/convenios')->assertOk()->json('data.*.id'),
        );
    }
}

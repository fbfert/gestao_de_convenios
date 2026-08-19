<?php

namespace Tests\Feature;

use App\Models\Antecipacao;
use App\Models\AuditLog;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AntecipacaoService;
use App\Services\GuiaService;
use App\Services\LancamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AntecipacoesApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_lista_e_mostra_antecipacoes_com_filtros(): void
    {
        $this->autenticar();

        $aberta = $this->criarAntecipacaoAberta('SC Saúde', 'Fonoaudiologia', 'convencional');
        $fechada = $this->criarAntecipacaoFechada('Unimed', 'Fisioterapia', 'especializada');

        $listadas = $this->getJson('/api/antecipacoes?status=open&paciente_id='.$aberta->paciente_id.'&convenio_id='.$aberta->convenio_id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $aberta->id)
            ->json('data');

        // Compara a lista de ids em vez de procurar o fragmento {"id": N}: o
        // recurso agora aninha convenio e especialidade, que tambem tem id.
        $this->assertNotContains($fechada->id, array_column($listadas, 'id'));

        $this->getJson('/api/antecipacoes?status=closed&paciente_id='.$fechada->paciente_id.'&convenio_id='.$fechada->convenio_id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $fechada->id);

        $this->getJson('/api/antecipacoes/'.$aberta->id)
            ->assertOk()
            ->assertJsonPath('data.id', $aberta->id)
            ->assertJsonPath('data.status', 'open');

        $this->getJson('/api/antecipacoes/'.$fechada->id)
            ->assertOk()
            ->assertJsonPath('data.id', $fechada->id)
            ->assertJsonPath('data.lancamentos.0.data_sessao', today()->toDateString());
    }

    public function test_admin_edita_antecipacao_e_fica_registrado_na_auditoria(): void
    {
        $this->autenticar();
        $antecipacao = $this->criarAntecipacaoAberta('SC Saúde', 'Fonoaudiologia', 'convencional');
        $qtdOriginal = $antecipacao->qtd_autorizada;

        $this->patchJson("/api/antecipacoes/{$antecipacao->id}", [
            'qtd_autorizada' => $qtdOriginal + 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.qtd_autorizada', $qtdOriginal + 5)
            // qtd_utilizada/status nao fazem parte do payload aceito: continuam calculados pelo sistema.
            ->assertJsonPath('data.qtd_utilizada', $antecipacao->qtd_utilizada)
            ->assertJsonPath('data.status', 'open');

        $evento = AuditLog::query()
            ->where('entidade', 'antecipacoes')
            ->where('entidade_id', $antecipacao->id)
            ->where('acao', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($qtdOriginal, $evento->payload['antes']['qtd_autorizada']);
        $this->assertSame($qtdOriginal + 5, $evento->payload['depois']['qtd_autorizada']);
    }

    public function test_aumentar_qtd_autorizada_reabre_antecipacao_fechada(): void
    {
        $this->autenticar();
        $fechada = $this->criarAntecipacaoFechada('Unimed', 'Fisioterapia', 'especializada');
        $this->assertSame('closed', $fechada->status);

        $this->patchJson("/api/antecipacoes/{$fechada->id}", [
            'qtd_autorizada' => $fechada->qtd_autorizada + 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_funcionario_nao_pode_editar_antecipacao(): void
    {
        $this->autenticar();
        $antecipacao = $this->criarAntecipacaoAberta('SC Saúde', 'Fonoaudiologia', 'convencional');

        $funcionario = User::query()->where('email', 'funcionario@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($funcionario);

        $this->patchJson("/api/antecipacoes/{$antecipacao->id}", ['qtd_autorizada' => 999])
            ->assertForbidden();
    }

    public function test_usuario_de_um_tenant_nao_enxerga_antecipacao_de_outro_tenant_via_http(): void
    {
        $antecipacaoOutroTenant = $this->criarAntecipacaoDeOutroTenant();

        $this->autenticar();

        $this->getJson('/api/antecipacoes/'.$antecipacaoOutroTenant->id)
            ->assertNotFound();
    }

    public function test_profissional_so_enxerga_suas_antecipacoes_na_listagem(): void
    {
        $user = $this->autenticarProfissional();
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $profissionalProprio = Profissional::query()->findOrFail($user->profissional_id);
        $profissionalOutro = Profissional::query()->where('id', '!=', $profissionalProprio->id)->firstOrFail();

        $antecipacaoPropria = $this->criarAntecipacaoParaProfissional(
            $tenant,
            $profissionalProprio,
            'Unimed',
            'especializada',
            'ANTE-PRIVADA-'.uniqid()
        );

        $antecipacaoOutra = $this->criarAntecipacaoParaProfissional(
            $tenant,
            $profissionalOutro,
            'SC Saúde',
            'convencional',
            'ANTE-PRIVADA-'.uniqid()
        );

        $this->getJson('/api/antecipacoes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $antecipacaoPropria->id)
            ->assertJsonMissing(['id' => $antecipacaoOutra->id]);
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

    private function criarAntecipacaoAberta(string $convenioNome, string $especialidadeNome, string $tipoTerapia): Antecipacao
    {
        $guia = $this->criarGuiaFinalizada($convenioNome, $especialidadeNome, $tipoTerapia);

        return app(GuiaService::class)->finalizar($guia, [
            'senha' => 'ABC123',
        ])->antecipacoes()->firstOrFail();
    }

    private function criarAntecipacaoFechada(string $convenioNome, string $especialidadeNome, string $tipoTerapia): Antecipacao
    {
        $guia = $this->criarGuiaFinalizada($convenioNome, $especialidadeNome, $tipoTerapia);
        $antecipacao = app(GuiaService::class)->finalizar($guia, [
            'senha' => 'DEF123',
        ])->antecipacoes()->firstOrFail();

        $profissional = Profissional::query()->where('especialidade_id', $antecipacao->guia->especialidade_id)->firstOrFail();
        app(LancamentoService::class)->registrar($antecipacao, $profissional, today());

        return $antecipacao->fresh();
    }

    private function criarGuiaFinalizada(string $convenioNome, string $especialidadeNome, string $tipoTerapia): Guia
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $convenio = Convenio::query()->where('nome', $convenioNome)->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', $especialidadeNome)->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();

        $guia = Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-ANT-'.uniqid(),
            'tipo_terapia' => $tipoTerapia,
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);

        return $guia;
    }

    private function criarAntecipacaoParaProfissional(Tenant $tenant, Profissional $profissional, string $convenioNome, string $tipoTerapia, string $prefixoNumero): Antecipacao
    {
        $convenio = Convenio::query()->where('nome', $convenioNome)->firstOrFail();
        $paciente = Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();

        $guia = Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $profissional->especialidade_id,
            'numero_guia' => $prefixoNumero,
            'tipo_terapia' => $tipoTerapia,
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);

        return app(GuiaService::class)->finalizar($guia, [
            'senha' => 'ABC123',
        ])->antecipacoes()->firstOrFail();
    }

    private function criarAntecipacaoDeOutroTenant(): Antecipacao
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa Ant',
            'slug' => 'clinica-externa-ant',
            'cnpj' => '66.666.666/0001-66',
            'ativo' => true,
        ]);

        $especialidade = Especialidade::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Fisioterapia Externa Ant',
            'ativo' => true,
        ]);

        $profissional = Profissional::query()->create([
            'tenant_id' => $tenant->id,
            'especialidade_id' => $especialidade->id,
            'nome' => 'Dra. Externa Ant',
            'conselho_registro' => 'CREFITO 777777-F',
            'ativo' => true,
        ]);

        $convenio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Externo Ant',
            'connector_type' => 'manual',
            'connector_config' => null,
            'ativo' => true,
        ]);

        $paciente = Paciente::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Paciente Externo Ant',
            'cpf' => '12345678907',
            'carteirinha' => 'EXT-A-0001',
            'convenio_id' => $convenio->id,
            'telefone' => '(11) 90000-0002',
            'clinica_agil_id' => null,
            'ativo' => true,
        ]);

        $guia = Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-EXTERNO-ANT-001',
            'tipo_terapia' => 'especializada',
            'status' => 'finalized',
            'data_solicitacao' => today(),
            'data_finalizacao' => today(),
            'senha' => 'EXT123',
            'validade_senha' => today()->copy()->addDays(30),
            'observacoes' => null,
        ]);

        return Antecipacao::query()->create([
            'tenant_id' => $tenant->id,
            'guia_id' => $guia->id,
            'paciente_id' => $paciente->id,
            'convenio_id' => $convenio->id,
            'ciclo_inicio' => today(),
            'ciclo_fim' => today(),
            'qtd_autorizada' => 1,
            'qtd_utilizada' => 0,
            'status' => 'open',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Antecipacao;
use App\Models\ConciliacaoFinanceira;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GuiaService;
use App\Services\LancamentoService;
use App\Services\ConciliacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConciliacoesApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cria_lista_filtra_e_transiciona_conciliacoes(): void
    {
        $this->autenticar();

        $conciliacaoA = $this->criarConciliacaoFinalizadaComLancamento('Unimed', 'Fisioterapia', 'especializada');
        $conciliacaoB = $this->criarConciliacaoFinalizadaComLancamento('SC Saúde', 'Fonoaudiologia', 'convencional');

        $create = $this->postJson("/api/guias/{$conciliacaoA->guia_id}/conciliacao");

        $create->assertCreated()
            ->assertJsonPath('data.guia_id', $conciliacaoA->guia_id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.percentual_repasse_profissional', '64.00')
            ->assertJsonPath('data.valor_repasse_total', '102.40');

        $novaConciliacaoId = $create->json('data.id');

        $this->getJson('/api/conciliacoes?convenio_id='.$conciliacaoA->guia->convenio_id.'&especialidade_id='.$conciliacaoA->guia->especialidade_id.'&profissional_id='.$conciliacaoA->profissional_id.'&status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.id', $novaConciliacaoId)
            ->assertJsonMissing(['id' => $conciliacaoB->id]);

        $this->patchJson("/api/conciliacoes/{$novaConciliacaoId}/marcar-conferido")
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed')
            ->assertJson(fn ($json) => $json->whereType('data.conferido_em', 'string'));

        $this->patchJson("/api/conciliacoes/{$novaConciliacaoId}/marcar-pago")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_criacao_valida_status_de_listagem(): void
    {
        $this->autenticar();

        $this->getJson('/api/conciliacoes?status=invalid-status')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_nao_deixa_marcar_como_pago_sem_conferencia(): void
    {
        $this->autenticar();

        $conciliacao = $this->criarConciliacaoFinalizadaComLancamento('Unimed', 'Fisioterapia', 'especializada');

        $this->patchJson("/api/conciliacoes/{$conciliacao->id}/marcar-pago")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Transição inválida de conciliação: pending -> paid.');
    }

    public function test_usuario_de_um_tenant_nao_enxerga_conciliacao_de_outro_tenant_via_http(): void
    {
        $conciliacaoOutroTenant = $this->criarConciliacaoOutroTenant();

        $this->autenticar();

        $this->patchJson("/api/conciliacoes/{$conciliacaoOutroTenant->id}/marcar-conferido")
            ->assertNotFound();
    }

    public function test_profissional_so_enxerga_suas_conciliacoes_na_listagem(): void
    {
        $user = $this->autenticarProfissional();
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $profissionalProprio = Profissional::query()->findOrFail($user->profissional_id);
        $profissionalOutro = Profissional::query()->where('id', '!=', $profissionalProprio->id)->firstOrFail();

        $conciliacaoPropria = $this->criarConciliacaoParaProfissional(
            $tenant,
            $profissionalProprio,
            'Unimed',
            'especializada',
            'CONC-PRIVADA-'.uniqid()
        );

        $conciliacaoOutra = $this->criarConciliacaoParaProfissional(
            $tenant,
            $profissionalOutro,
            'SC Saúde',
            'convencional',
            'CONC-PRIVADA-'.uniqid()
        );

        $this->getJson('/api/conciliacoes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conciliacaoPropria->id)
            ->assertJsonMissing(['id' => $conciliacaoOutra->id]);
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

    private function criarConciliacaoFinalizadaComLancamento(string $convenioNome, string $especialidadeNome, string $tipoTerapia): ConciliacaoFinanceira
    {
        $guia = $this->criarGuiaFinalizada($convenioNome, $especialidadeNome, $tipoTerapia);
        $antecipacao = $guia->antecipacoes()->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $guia->especialidade_id)->firstOrFail();

        app(LancamentoService::class)->registrar($antecipacao, $profissional, today());

        return app(ConciliacaoService::class)->gerarParaGuia($guia)->fresh(['guia', 'profissional']);
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
            'numero_guia' => 'GUIA-CONC-'.uniqid(),
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
        ]);
    }

    private function criarConciliacaoParaProfissional(Tenant $tenant, Profissional $profissional, string $convenioNome, string $tipoTerapia, string $prefixoNumero): ConciliacaoFinanceira
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

        $guia = app(GuiaService::class)->finalizar($guia, [
            'senha' => 'ABC123',
        ]);

        $antecipacao = $guia->antecipacoes()->firstOrFail();
        app(LancamentoService::class)->registrar($antecipacao, $profissional, today());

        return app(ConciliacaoService::class)->gerarParaGuia($guia)->fresh(['guia', 'profissional']);
    }

    private function criarConciliacaoOutroTenant(): ConciliacaoFinanceira
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa Conc',
            'slug' => 'clinica-externa-conc',
            'cnpj' => '44.444.444/0001-44',
            'ativo' => true,
        ]);

        $especialidade = Especialidade::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Fisioterapia Externa Conc',
            'ativo' => true,
        ]);

        $profissional = Profissional::query()->create([
            'tenant_id' => $tenant->id,
            'especialidade_id' => $especialidade->id,
            'nome' => 'Dra. Externa Conc',
            'conselho_registro' => 'CREFITO 555555-F',
            'ativo' => true,
        ]);

        $convenio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Externo Conc',
            'connector_type' => 'manual',
            'connector_config' => null,
            'ativo' => true,
        ]);

        $paciente = Paciente::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Paciente Externo Conc',
            'cpf' => '12345678905',
            'carteirinha' => 'EXT-C-0001',
            'convenio_id' => $convenio->id,
            'telefone' => '(11) 90000-0004',
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
            'numero_guia' => 'GUIA-EXTERNO-CONC-001',
            'tipo_terapia' => 'especializada',
            'status' => 'finalized',
            'data_solicitacao' => today(),
            'data_finalizacao' => today(),
            'senha' => 'EXTCONC123',
            'validade_senha' => today()->copy()->addDays(30),
            'observacoes' => null,
        ]);

        $antecipacao = Antecipacao::query()->create([
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

        Lancamento::query()->create([
            'tenant_id' => $tenant->id,
            'antecipacao_id' => $antecipacao->id,
            'profissional_id' => $profissional->id,
            'data_sessao' => today(),
            'status' => 'completed',
            'observacoes' => null,
        ]);

        return ConciliacaoFinanceira::query()->create([
            'tenant_id' => $tenant->id,
            'guia_id' => $guia->id,
            'profissional_id' => $profissional->id,
            'quantidade' => 1,
            'valor_unitario' => '160.00',
            'valor_total' => '160.00',
            'referencia_analitico_convenio' => null,
            'status' => 'pending',
            'conferido_em' => null,
        ]);
    }
}

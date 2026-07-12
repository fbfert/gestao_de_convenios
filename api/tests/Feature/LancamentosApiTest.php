<?php

namespace Tests\Feature;

use App\Models\Antecipacao;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LancamentosApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cria_lista_e_filtra_lancamentos(): void
    {
        $this->autenticar();

        $antecipacao = $this->criarAntecipacaoAberta('SC Saúde', 'Fonoaudiologia', 'convencional');
        $profissionalAlvo = Profissional::query()->where('especialidade_id', $antecipacao->guia->especialidade_id)->firstOrFail();
        $profissionalOutro = Profissional::query()->where('id', '!=', $profissionalAlvo->id)->firstOrFail();

        $create = $this->postJson("/api/antecipacoes/{$antecipacao->id}/lancamentos", [
            'profissional_id' => $profissionalAlvo->id,
            'data_sessao' => today()->toDateString(),
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.antecipacao_id', $antecipacao->id)
            ->assertJsonPath('data.profissional_id', $profissionalAlvo->id)
            ->assertJsonPath('data.status', 'completed');

        $lancamentoId = $create->json('data.id');

        $this->postJson("/api/antecipacoes/{$antecipacao->id}/lancamentos", [
            'profissional_id' => $profissionalOutro->id,
            'data_sessao' => today()->copy()->subDay()->toDateString(),
        ])->assertCreated();

        $this->getJson('/api/lancamentos?profissional_id='.$profissionalAlvo->id.'&data_sessao='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.0.id', $lancamentoId)
            ->assertJsonMissing(['profissional_id' => $profissionalOutro->id]);
    }

    public function test_criacao_valida_campos_obrigatorios(): void
    {
        $this->autenticar();
        $antecipacao = $this->criarAntecipacaoAberta('SC Saúde', 'Fonoaudiologia', 'convencional');

        $this->postJson("/api/antecipacoes/{$antecipacao->id}/lancamentos", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'profissional_id',
                'data_sessao',
            ]);
    }

    public function test_registrar_em_antecipacao_fechada_retorna_422(): void
    {
        $this->autenticar();
        $antecipacao = $this->criarAntecipacaoFechada('Unimed', 'Fisioterapia', 'especializada');
        $profissional = Profissional::query()->where('especialidade_id', $antecipacao->guia->especialidade_id)->firstOrFail();

        $this->postJson("/api/antecipacoes/{$antecipacao->id}/lancamentos", [
            'profissional_id' => $profissional->id,
            'data_sessao' => today()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', "A antecipação {$antecipacao->id} já está fechada ou com a cota esgotada.");

        $this->assertDatabaseCount('lancamentos', 1);
    }

    public function test_usuario_de_um_tenant_nao_enxerga_antecipacao_de_outro_tenant_via_http(): void
    {
        $antecipacaoOutroTenant = $this->criarAntecipacaoDeOutroTenant();

        $this->autenticar();

        $this->postJson("/api/antecipacoes/{$antecipacaoOutroTenant->id}/lancamentos", [
            'profissional_id' => 1,
            'data_sessao' => today()->toDateString(),
        ])->assertNotFound();
    }

    public function test_profissional_so_enxerga_seus_lancamentos_na_listagem(): void
    {
        $user = $this->autenticarProfissional();
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $profissionalProprio = Profissional::query()->findOrFail($user->profissional_id);
        $profissionalOutro = Profissional::query()->where('id', '!=', $profissionalProprio->id)->firstOrFail();

        $lancamentoProprio = $this->criarLancamentoParaProfissional($tenant, $profissionalProprio, 'Unimed', 'especializada', 'LAN-PRIVADO-'.uniqid());
        $lancamentoOutro = $this->criarLancamentoParaProfissional($tenant, $profissionalOutro, 'SC Saúde', 'convencional', 'LAN-PRIVADO-'.uniqid());

        $this->getJson('/api/lancamentos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lancamentoProprio->id)
            ->assertJsonMissing(['id' => $lancamentoOutro->id]);
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

        return Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-LAN-'.uniqid(),
            'tipo_terapia' => $tipoTerapia,
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);
    }

    private function criarLancamentoParaProfissional(Tenant $tenant, Profissional $profissional, string $convenioNome, string $tipoTerapia, string $prefixoNumero): Lancamento
    {
        $guia = Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => Convenio::query()->where('nome', $convenioNome)->firstOrFail()->id,
            'paciente_id' => Paciente::query()->where('convenio_id', Convenio::query()->where('nome', $convenioNome)->firstOrFail()->id)->firstOrFail()->id,
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

        $antecipacao = app(GuiaService::class)->finalizar($guia, [
            'senha' => 'ABC123',
        ])->antecipacoes()->firstOrFail();

        return app(LancamentoService::class)->registrar($antecipacao, $profissional, today());
    }

    private function criarAntecipacaoDeOutroTenant(): Antecipacao
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa Lan',
            'slug' => 'clinica-externa-lan',
            'cnpj' => '55.555.555/0001-55',
            'ativo' => true,
        ]);

        $especialidade = Especialidade::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Fisioterapia Externa Lan',
            'ativo' => true,
        ]);

        $profissional = Profissional::query()->create([
            'tenant_id' => $tenant->id,
            'especialidade_id' => $especialidade->id,
            'nome' => 'Dra. Externa Lan',
            'conselho_registro' => 'CREFITO 666666-F',
            'ativo' => true,
        ]);

        $convenio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Externo Lan',
            'connector_type' => 'manual',
            'connector_config' => null,
            'ativo' => true,
        ]);

        $paciente = Paciente::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Paciente Externo Lan',
            'cpf' => '12345678906',
            'carteirinha' => 'EXT-L-0001',
            'convenio_id' => $convenio->id,
            'telefone' => '(11) 90000-0003',
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
            'numero_guia' => 'GUIA-EXTERNO-LAN-001',
            'tipo_terapia' => 'especializada',
            'status' => 'finalized',
            'data_solicitacao' => today(),
            'data_finalizacao' => today(),
            'senha' => 'EXTLAN123',
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

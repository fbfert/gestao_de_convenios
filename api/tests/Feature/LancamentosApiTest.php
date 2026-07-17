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
use Illuminate\Http\UploadedFile;
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

    public function test_importa_transcricao_e_cria_multiplas_sessoes(): void
    {
        $this->autenticar();

        $antecipacao = $this->criarAntecipacaoAberta('Unimed', 'Fisioterapia', 'especializada');
        $antecipacao->forceFill([
            'qtd_autorizada' => 8,
            'qtd_utilizada' => 0,
            'status' => 'open',
        ])->save();

        $transcricao = <<<'TXT'
GUIA Nº: 521381566206
Clínica: Centro Neuro Kids Ltda
Paciente: E...
Número Cartão: 0220 090000 551.330-8
Profissional Executante: Mariana
Terapia aplicada: ABA - AV. Neuropsicológica

Sessões
1 08/04/26 14:50 15:40 Bruno Marinho Aplicação testes Neuropsicológicos
2 09/04/26 14:50 15:40 Bruno Marinho Denver (Desenvolvimento)
3 28/04/26 14:50 15:40 Bruno Marinho Denver
4 05/05/2026 14:50 15:40 Bruno Marinho Columbia (maturidade mental)
5 12/05/26 14:50 15:40 Bruno Marinho Trilhas pré-escolar (atenção)
6 02/06/2026 14:50 15:40 Bruno Marinho Intervenções com recurso livre
7 09/06/2026 14:30 15:00 Bruno Marinho Intervenções com desenhos livres
8 22/06/2026 15:30 16:20 Bruno Marinho Devolutiva e entrega do laudo
TXT;

        $preview = $this->postJson("/api/antecipacoes/{$antecipacao->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $antecipacao->guia->profissional_id,
            'transcricao' => $transcricao,
        ]);

        $preview->assertOk()
            ->assertJsonPath('data.confirmacao_pendente', true)
            ->assertJsonPath('data.cabecalho.clinica', 'Centro Neuro Kids Ltda')
            ->assertJsonPath('data.cabecalho.profissional_executante', 'Mariana')
            ->assertJsonPath('data.sessoes.0.data_sessao', '2026-04-08')
            ->assertJsonPath('data.sessoes.7.data_sessao', '2026-06-22');

        $this->assertDatabaseCount('lancamentos', 0);

        $this->postJson("/api/antecipacoes/{$antecipacao->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $antecipacao->guia->profissional_id,
            'transcricao' => $transcricao,
            'confirmar_envio' => true,
            'sessoes' => [
                [
                    'data_sessao' => '2026-04-09',
                    'hora_inicio' => '14:50',
                    'hora_fim' => '15:40',
                    'acompanhante' => 'Bruno Marinho',
                    'resumo_atividades' => 'Aplicação testes Neuropsicológicos',
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['pdf_registro_sessoes']);

        $confirmacao = $this->post("/api/antecipacoes/{$antecipacao->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $antecipacao->guia->profissional_id,
            'transcricao' => $transcricao,
            'confirmar_envio' => true,
            'sessoes' => [
                [
                    'data_sessao' => '2026-04-09',
                    'hora_inicio' => '14:50',
                    'hora_fim' => '15:40',
                    'acompanhante' => 'Bruno Marinho',
                    'resumo_atividades' => 'Aplicação testes Neuropsicológicos',
                ],
                [
                    'data_sessao' => '2026-04-09',
                    'hora_inicio' => '14:50',
                    'hora_fim' => '15:40',
                    'acompanhante' => 'Bruno Marinho',
                    'resumo_atividades' => 'Denver (Desenvolvimento)',
                ],
                [
                    'data_sessao' => '2026-04-28',
                    'hora_inicio' => '14:50',
                    'hora_fim' => '15:40',
                    'acompanhante' => 'Bruno Marinho',
                    'resumo_atividades' => 'Denver',
                ],
                [
                    'data_sessao' => '2026-05-05',
                    'hora_inicio' => '14:50',
                    'hora_fim' => '15:40',
                    'acompanhante' => 'Bruno Marinho',
                    'resumo_atividades' => 'Columbia (maturidade mental)',
                ],
                [
                    'data_sessao' => '2026-05-12',
                    'hora_inicio' => '14:50',
                    'hora_fim' => '15:40',
                    'acompanhante' => 'Bruno Marinho',
                    'resumo_atividades' => 'Trilhas pré-escolar (atenção)',
                ],
                [
                    'data_sessao' => '2026-06-02',
                    'hora_inicio' => '14:50',
                    'hora_fim' => '15:40',
                    'acompanhante' => 'Bruno Marinho',
                    'resumo_atividades' => 'Intervenções com recurso livre',
                ],
                [
                    'data_sessao' => '2026-06-09',
                    'hora_inicio' => '14:30',
                    'hora_fim' => '15:00',
                    'acompanhante' => 'Bruno Marinho',
                    'resumo_atividades' => 'Intervenções com desenhos livres',
                ],
                [
                    'data_sessao' => '2026-06-22',
                    'hora_inicio' => '15:30',
                    'hora_fim' => '16:20',
                    'acompanhante' => 'Bruno Marinho',
                    'resumo_atividades' => 'Devolutiva e entrega do laudo',
                ],
            ],
            'pdf_registro_sessoes' => UploadedFile::fake()->create('registro.pdf', 128, 'application/pdf'),
        ]);

        $confirmacao->assertCreated()
            ->assertJsonPath('data.confirmacao_pendente', false)
            ->assertJsonPath('data.cabecalho.clinica', 'Centro Neuro Kids Ltda')
            ->assertJsonPath('data.sessoes.0.data_sessao', '2026-04-09')
            ->assertJsonPath('data.registros.0.hora_inicio', '14:50')
            ->assertJsonPath('data.registros.7.hora_fim', '16:20');

        $this->assertDatabaseCount('lancamentos', 8);
        $this->assertSame(8, Lancamento::query()->where('antecipacao_id', $antecipacao->id)->count());
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

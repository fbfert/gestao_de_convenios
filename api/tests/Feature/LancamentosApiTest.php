<?php

namespace Tests\Feature;

use App\Models\AnaliticoUnimedLinha;
use App\Models\AuditLog;
use App\Models\AnaliticoUnimedLote;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\LancamentoPrintTemplate;
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

        $guia = $this->criarGuiaAprovada('SC Saúde', 'Fonoaudiologia', 'convencional');
        $profissionalAlvo = Profissional::query()->where('especialidade_id', $guia->especialidade_id)->firstOrFail();
        $profissionalOutro = Profissional::query()->where('id', '!=', $profissionalAlvo->id)->firstOrFail();

        $create = $this->postJson("/api/guias/{$guia->id}/lancamentos", [
            'profissional_id' => $profissionalAlvo->id,
            'data_sessao' => today()->toDateString(),
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.guia_id', $guia->id)
            ->assertJsonPath('data.profissional_id', $profissionalAlvo->id)
            ->assertJsonPath('data.status', 'completed');

        $lancamentoId = $create->json('data.id');

        $this->postJson("/api/guias/{$guia->id}/lancamentos", [
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
        $guia = $this->criarGuiaAprovada('SC Saúde', 'Fonoaudiologia', 'convencional');

        $this->postJson("/api/guias/{$guia->id}/lancamentos", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'profissional_id',
                'data_sessao',
            ]);
    }

    public function test_admin_edita_lancamento_e_fica_registrado_na_auditoria(): void
    {
        $this->autenticar();
        $guia = $this->criarGuiaAprovada('SC Saúde', 'Fonoaudiologia', 'convencional');
        $profissionalAlvo = Profissional::query()->where('especialidade_id', $guia->especialidade_id)->firstOrFail();

        $lancamentoId = $this->postJson("/api/guias/{$guia->id}/lancamentos", [
            'profissional_id' => $profissionalAlvo->id,
            'data_sessao' => today()->toDateString(),
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/lancamentos/{$lancamentoId}", [
            'acompanhante' => 'Mãe da paciente',
            'resumo_atividades' => 'Corrigido pelo admin',
        ])
            ->assertOk()
            ->assertJsonPath('data.acompanhante', 'Mãe da paciente')
            ->assertJsonPath('data.guia_id', $guia->id);

        $evento = AuditLog::query()
            ->where('entidade', 'lancamentos')
            ->where('entidade_id', $lancamentoId)
            ->where('acao', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Mãe da paciente', $evento->payload['depois']['acompanhante']);
    }

    public function test_funcionario_nao_pode_editar_lancamento(): void
    {
        $this->autenticar();
        $guia = $this->criarGuiaAprovada('SC Saúde', 'Fonoaudiologia', 'convencional');
        $profissionalAlvo = Profissional::query()->where('especialidade_id', $guia->especialidade_id)->firstOrFail();

        $lancamentoId = $this->postJson("/api/guias/{$guia->id}/lancamentos", [
            'profissional_id' => $profissionalAlvo->id,
            'data_sessao' => today()->toDateString(),
        ])->assertCreated()->json('data.id');

        $funcionario = User::query()->where('email', 'funcionario@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($funcionario);

        $this->patchJson("/api/lancamentos/{$lancamentoId}", ['acompanhante' => 'Não deveria'])
            ->assertForbidden();
    }

    public function test_registrar_sem_sessoes_disponiveis_retorna_422(): void
    {
        $this->autenticar();
        $guia = $this->criarGuiaComCotaEsgotada('Unimed', 'Fisioterapia', 'especializada');
        $profissional = Profissional::query()->where('especialidade_id', $guia->especialidade_id)->firstOrFail();

        $this->postJson("/api/guias/{$guia->id}/lancamentos", [
            'profissional_id' => $profissional->id,
            'data_sessao' => today()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['guia']);

        $this->assertDatabaseCount('lancamentos', 1);
    }

    public function test_usuario_de_um_tenant_nao_enxerga_guia_de_outro_tenant_via_http(): void
    {
        $guiaOutroTenant = $this->criarGuiaDeOutroTenant();

        $this->autenticar();

        $this->postJson("/api/guias/{$guiaOutroTenant->id}/lancamentos", [
            'profissional_id' => 1,
            'data_sessao' => today()->toDateString(),
        ])->assertNotFound();
    }

    public function test_importa_transcricao_e_cria_multiplas_sessoes(): void
    {
        $this->autenticar();

        $guia = $this->criarGuiaAprovada('Unimed', 'Fisioterapia', 'especializada', sessoesAutorizadas: 8);

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

        $preview = $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'transcricao' => $transcricao,
        ]);

        $preview->assertOk()
            ->assertJsonPath('data.confirmacao_pendente', true)
            ->assertJsonPath('data.cabecalho.clinica', 'Centro Neuro Kids Ltda')
            ->assertJsonPath('data.cabecalho.profissional_executante', 'Mariana')
            ->assertJsonPath('data.sessoes.0.data_sessao', '2026-04-08')
            ->assertJsonPath('data.sessoes.7.data_sessao', '2026-06-22');

        $this->assertDatabaseCount('lancamentos', 0);

        $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
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

        $confirmacao = $this->post("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
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
        $this->assertSame(8, Lancamento::query()->where('guia_id', $guia->id)->count());
    }

    public function test_importa_analitico_unimed_e_normaliza_linhas(): void
    {
        $this->autenticar();

        $arquivo = $this->criarArquivoAnaliticoUnimed();

        $response = $this->post('/api/lancamentos/importar-analitico', [
            'arquivo' => $arquivo,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.arquivo', 'analitico-unimed.xlsx')
            ->assertJsonPath('data.planilhas.0.nome', 'Analítico')
            ->assertJsonPath('data.planilhas.0.linhas', 1)
            ->assertJsonPath('data.planilhas.1.nome', 'Glosa')
            ->assertJsonPath('data.analitico.cabecalho.unimed_executante.codigo', '220')
            ->assertJsonPath('data.analitico.linhas.0.numero_guia_operadora', '50137394772')
            ->assertJsonPath('data.analitico.linhas.0.valor', '45,00')
            ->assertJsonPath('data.glosas.linhas.0.motivo', 'Cobranca de procedimento em duplicidade')
            ->assertJsonPath('data.conciliacao.totais.pago', '45,00')
            ->assertJsonPath('data.conciliacao.totais.glosado', '45,00')
            ->assertJsonPath('data.conciliacao.resumo_por_guia.0.numero_guia_operadora', '50137394772')
            ->assertJsonPath('data.conciliacao.resumo_por_guia.0.valor_pago', '45,00');

        $this->assertDatabaseCount('analitico_unimed_lotes', 1);
        $this->assertDatabaseCount('analitico_unimed_linhas', 2);

        $lote = AnaliticoUnimedLote::query()->firstOrFail();
        $response->assertJsonPath('data.lote.id', $lote->id)
            ->assertJsonPath('data.lote.status', 'importado')
            ->assertJsonPath('data.lote.total_linhas_analitico', 1)
            ->assertJsonPath('data.lote.total_linhas_glosa', 1)
            ->assertJsonPath('data.lote.total_linhas_conciliacao', 2);

        $this->assertSame('analitico-unimed.xlsx', $lote->arquivo_nome_original);
        $this->assertSame('importado', $lote->status);
        $this->assertSame('45.00', $lote->total_pago);
        $this->assertSame('45.00', $lote->total_glosado);
        $this->assertSame('0.00', $lote->saldo_total);

        $linhaAnalitico = AnaliticoUnimedLinha::query()->where('origem', 'analitico')->firstOrFail();
        $linhaGlosa = AnaliticoUnimedLinha::query()->where('origem', 'glosa')->firstOrFail();

        $this->assertSame('pago', $linhaAnalitico->natureza);
        $this->assertSame('glosado', $linhaGlosa->natureza);
    }

    public function test_profissional_so_enxerga_seus_lancamentos_na_listagem(): void
    {
        $user = $this->autenticarProfissional();
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $profissionalProprio = Profissional::query()->findOrFail($user->profissional_id);
        $profissionalOutro = Profissional::query()->where('id', '!=', $profissionalProprio->id)->firstOrFail();

        $lancamentoProprio = $this->criarLancamentoParaProfissional($tenant, $profissionalProprio, 'Unimed', 'especializada', 'LAN-PRIVADO-'.uniqid());
        $lancamentoOutro = $this->criarLancamentoParaProfissional($tenant, $profissionalOutro, 'SC Saúde', 'convencional', 'LAN-PRIVADO-'.uniqid());

        // assertJsonCount(1) + o id certo na única linha já provam a
        // exclusão do outro — um assertJsonMissing(['id' => ...]) a mais
        // aqui colide em falso positivo com o guia.id aninhado no recurso.
        $this->getJson('/api/lancamentos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lancamentoProprio->id);

        $this->assertNotSame($lancamentoProprio->id, $lancamentoOutro->id);
    }

    public function test_consulta_e_atualiza_template_de_impressao_do_registro_de_sessoes(): void
    {
        $this->autenticar();

        $show = $this->getJson('/api/lancamentos/templates/registro-sessoes');

        $show->assertOk()
            ->assertJsonPath('data.chave', 'registro_sessoes')
            ->assertJsonPath('data.nome', 'Registro de sessões');

        $this->assertDatabaseCount('lancamento_print_templates', 1);

        $update = $this->putJson('/api/lancamentos/templates/registro-sessoes', [
            'nome' => 'Registro Unimed',
            'html' => '<h1>{{paciente}}</h1>{{#sessoes}}<p>{{data_sessao}}</p>{{/sessoes}}',
            'ativo' => true,
        ]);

        $update->assertOk()
            ->assertJsonPath('data.nome', 'Registro Unimed')
            ->assertJsonPath('data.html', '<h1>{{paciente}}</h1>{{#sessoes}}<p>{{data_sessao}}</p>{{/sessoes}}');

        $this->assertSame(
            '<h1>{{paciente}}</h1>{{#sessoes}}<p>{{data_sessao}}</p>{{/sessoes}}',
            LancamentoPrintTemplate::query()->where('chave', 'registro_sessoes')->firstOrFail()->html
        );
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

    /** Guia finalizada, já aceitando lançamento, com a cota que o teste pedir. */
    private function criarGuiaAprovada(string $convenioNome, string $especialidadeNome, string $tipoTerapia, int $sessoesAutorizadas = 10): Guia
    {
        $guia = $this->criarGuiaBase($convenioNome, $especialidadeNome, $tipoTerapia, $sessoesAutorizadas);

        return app(GuiaService::class)->finalizar($guia, [
            'senha' => 'ABC123',
        ]);
    }

    /** Guia com 1 sessão autorizada e já lançada — sessoesDisponiveis() = 0. */
    private function criarGuiaComCotaEsgotada(string $convenioNome, string $especialidadeNome, string $tipoTerapia): Guia
    {
        $guia = $this->criarGuiaAprovada($convenioNome, $especialidadeNome, $tipoTerapia, sessoesAutorizadas: 1);
        $profissional = Profissional::query()->where('especialidade_id', $guia->especialidade_id)->firstOrFail();
        app(LancamentoService::class)->registrar($guia, $profissional, today());

        return $guia->fresh();
    }

    private function criarGuiaBase(string $convenioNome, string $especialidadeNome, string $tipoTerapia, ?int $sessoesAutorizadas = null): Guia
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
            'sessoes_autorizadas' => $sessoesAutorizadas,
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);
    }

    private function criarLancamentoParaProfissional(Tenant $tenant, Profissional $profissional, string $convenioNome, string $tipoTerapia, string $prefixoNumero): Lancamento
    {
        $convenioId = Convenio::query()->where('nome', $convenioNome)->firstOrFail()->id;

        $guia = Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => $convenioId,
            'paciente_id' => Paciente::query()->where('convenio_id', $convenioId)->firstOrFail()->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $profissional->especialidade_id,
            'numero_guia' => $prefixoNumero,
            'tipo_terapia' => $tipoTerapia,
            'status' => 'under_review',
            'sessoes_autorizadas' => 10,
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);

        $guiaFinalizada = app(GuiaService::class)->finalizar($guia, [
            'senha' => 'ABC123',
        ]);

        return app(LancamentoService::class)->registrar($guiaFinalizada, $profissional, today());
    }

    private function criarArquivoAnaliticoUnimed(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'analitico_unimed_');

        if ($path === false) {
            throw new \RuntimeException('Não foi possível criar arquivo temporário para o analítico.');
        }

        $arquivo = $path.'.xlsx';
        @unlink($path);

        $zip = new \ZipArchive();
        if ($zip->open($arquivo, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível montar o xlsx de teste.');
        }

        $zip->addFromString('[Content_Types].xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/workbook.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Analítico" sheetId="1" r:id="rId1"/>
    <sheet name="Glosa" sheetId="2" r:id="rId2"/>
  </sheets>
</workbook>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->montarWorksheetXml([
            [1, ['A' => 'Unimed Executante: 220 - COOPERATIVA DE TRABALHO MEDICO DO PLANALTO SERRANO / CNPJ: 85246916000189']],
            [2, ['A' => 'Prestador Executante: 22099808 - CENTRO NEUROKIDS LTDA']],
            [3, [
                'A' => 'Número Guia da Operadora',
                'B' => 'Número Guia do Prestador',
                'C' => 'Código',
                'D' => 'Usuário',
                'E' => 'Data Autorização',
                'F' => 'Data Realização',
                'G' => 'Proced.',
                'H' => 'Tabela',
                'I' => 'Descrição do Proced.',
                'J' => 'Qtd.',
                'K' => 'Filme',
                'L' => 'Custo',
                'M' => 'Hono',
                'N' => 'Valor',
                'O' => 'Local Realização',
            ]],
            [4, [
                'A' => '50137394772',
                'B' => '50137394772',
                'E' => '20/03/2026',
                'F' => '06/04/2026',
                'G' => '50000470',
                'H' => 'Procedimentos e eventos em saúde',
                'I' => 'SESSÃO DE PSICOTERAPIA INDIVIDUAL POR PSICÓLOGO',
                'J' => '1',
                'K' => '0,00',
                'L' => '0,00',
                'M' => '45,00',
                'N' => '45,00',
                'O' => '',
            ]],
            [5, ['A' => 'TOTAL DO PRESTADOR', 'N' => '45,00']],
            [6, ['A' => 'TOTAL DO LOTE', 'N' => '45,00']],
        ]));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->montarWorksheetXml([
            [1, [
                'A' => 'Número Guia da Operadora',
                'B' => 'Número Guia do Prestador',
                'C' => 'Código',
                'D' => 'Usuário',
                'E' => 'Data Autorização',
                'F' => 'Data Realização',
                'G' => 'Proced.',
                'H' => 'Tabela',
                'I' => 'Descrição do Proced.',
                'J' => 'Qtd.',
                'K' => 'Tipo',
                'L' => 'Motivo',
                'M' => 'Valor',
                'N' => 'Local Realização',
            ]],
            [2, [
                'A' => '50137394772',
                'E' => '20/03/2026',
                'F' => '06/04/2026',
                'G' => '50000470',
                'H' => 'Procedimentos e eventos em saúde',
                'I' => 'SESSÃO DE PSICOTERAPIA INDIVIDUAL POR PSICÓLOGO',
                'J' => '1',
                'K' => '1.0',
                'L' => 'Cobranca de procedimento em duplicidade',
                'M' => '45,00',
                'N' => '',
            ]],
            [3, ['A' => 'TOTAL:', 'M' => '45,00']],
        ]));

        $zip->close();

        return UploadedFile::fake()->createWithContent('analitico-unimed.xlsx', file_get_contents($arquivo));
    }

    /**
     * @param array<int, array{0: int, 1: array<string, string>}> $rows
     */
    private function montarWorksheetXml(array $rows): string
    {
        $rowsXml = '';

        foreach ($rows as [$rowNumber, $cells]) {
            $cellsXml = '';
            foreach ($cells as $column => $value) {
                $escaped = e($value);
                $cellsXml .= sprintf(
                    '<c r="%s%d" t="inlineStr"><is><t>%s</t></is></c>',
                    $column,
                    $rowNumber,
                    $escaped
                );
            }

            $rowsXml .= sprintf('<row r="%d">%s</row>', $rowNumber, $cellsXml);
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    {$rowsXml}
  </sheetData>
</worksheet>
XML;
    }

    private function criarGuiaDeOutroTenant(): Guia
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

        return Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-EXTERNO-LAN-001',
            'tipo_terapia' => 'especializada',
            'status' => 'finalized',
            'sessoes_autorizadas' => 1,
            'data_solicitacao' => today(),
            'data_finalizacao' => today(),
            'senha' => 'EXTLAN123',
            'validade_senha' => today()->copy()->addDays(30),
            'observacoes' => null,
        ]);
    }

    public function test_le_registro_de_sessoes_escaneado_por_ia(): void
    {
        $guia = $this->criarGuiaAprovada('SC Saúde', 'Fonoaudiologia', 'convencional');
        $this->autenticar();
        $tenantId = (int) $guia->tenant_id;

        \App\Models\AiPromptTemplate::garantirPadroes($tenantId);
        \App\Models\AiOpenaiSetting::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            ['api_key' => 'sk-teste', 'base_url' => 'https://api.openai.com/v1', 'ativo' => true],
        );

        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Http::fake([
            '*/responses' => \Illuminate\Support\Facades\Http::response([
                'output_text' => json_encode([
                    'cabecalho' => [
                        'paciente' => 'Ana Ribeiro',
                        'numero_cartao' => '0220 090000 551.330-8',
                        'profissional_executante' => 'Mariana',
                    ],
                    'sessoes' => [
                        [
                            'data_sessao' => '2026-04-08',
                            'hora_inicio' => '14:50',
                            'hora_fim' => '15:40',
                            'acompanhante' => 'Bruno Marinho',
                            'resumo_atividades' => 'Aplicação de testes',
                        ],
                        // Linha sem data nem horario: posicao em branco na
                        // folha, preservada (nao mais descartada).
                        ['data_sessao' => null, 'hora_inicio' => null, 'resumo_atividades' => 'rodapé'],
                    ],
                ]),
            ]),
        ]);

        $this->postJson("/api/guias/{$guia->id}/lancamentos/ler-registro", [
            'arquivo' => \Illuminate\Http\UploadedFile::fake()->image('registro.jpg'),
        ])
            ->assertOk()
            ->assertJsonPath('data.confirmacao_pendente', true)
            // A folha tem 10 linhas fixas: a IA devolveu 2, as 8 restantes
            // vem preenchidas com null, preservando a posicao das duas.
            ->assertJsonCount(10, 'data.sessoes')
            ->assertJsonPath('data.sessoes.0.data_sessao', '2026-04-08')
            ->assertJsonPath('data.sessoes.0.hora_inicio', '14:50')
            ->assertJsonPath('data.sessoes.1.data_sessao', null)
            ->assertJsonPath('data.sessoes.1.resumo_atividades', 'rodapé')
            ->assertJsonPath('data.sessoes.9.data_sessao', null)
            ->assertJsonPath('data.cabecalho.paciente', 'Ana Ribeiro');

        // A leitura nao grava nada: a confirmacao continua sendo outro passo.
        $this->assertSame(0, $guia->lancamentos()->count());
    }

    public function test_confirma_sessoes_da_grade_sem_transcricao_ignorando_linhas_em_branco(): void
    {
        $this->autenticar();

        $guia = $this->criarGuiaAprovada('Unimed', 'Fisioterapia', 'especializada', sessoesAutorizadas: 8);

        // Grade fixa de 10 linhas: so 2 preenchidas (as demais, em branco,
        // simulam linhas da folha que nao foram lidas ou preenchidas ainda).
        $sessoes = array_fill(0, 10, [
            'data_sessao' => null,
            'hora_inicio' => null,
            'hora_fim' => null,
            'acompanhante' => null,
            'resumo_atividades' => null,
        ]);
        $sessoes[2] = [
            'data_sessao' => '2026-04-09',
            'hora_inicio' => '14:50',
            'hora_fim' => '15:40',
            'acompanhante' => 'Bruno Marinho',
            'resumo_atividades' => 'Aplicação testes',
        ];
        $sessoes[7] = [
            'data_sessao' => '2026-04-10',
            'hora_inicio' => '15:00',
            'hora_fim' => '15:50',
            'acompanhante' => null,
            'resumo_atividades' => 'Sessão sem acompanhante',
        ];

        $confirmacao = $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => $sessoes,
        ]);

        $confirmacao->assertCreated()
            ->assertJsonPath('data.confirmacao_pendente', false)
            ->assertJsonCount(2, 'data.registros');

        $this->assertSame(2, Lancamento::query()->where('guia_id', $guia->id)->count());
    }

    public function test_confirma_sessoes_exige_pdf_quando_numero_cartao_e_da_regional_0220(): void
    {
        $this->autenticar();

        $guia = $this->criarGuiaAprovada('Unimed', 'Fisioterapia', 'especializada', sessoesAutorizadas: 8);

        $sessoes = array_fill(0, 10, [
            'data_sessao' => null,
            'hora_inicio' => null,
            'hora_fim' => null,
            'acompanhante' => null,
            'resumo_atividades' => null,
        ]);
        $sessoes[0] = [
            'data_sessao' => '2026-04-09',
            'hora_inicio' => '14:50',
            'hora_fim' => '15:40',
            'acompanhante' => null,
            'resumo_atividades' => null,
        ];

        // numero_cartao vem explicito no payload (lido por IA ou digitado),
        // sem depender de transcricao nenhuma.
        $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'numero_cartao' => '0220 090000 551.330-8',
            'sessoes' => $sessoes,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pdf_registro_sessoes']);

        $this->assertDatabaseCount('lancamentos', 0);
    }

    public function test_sessoes_da_grade_recusa_mais_de_dez_linhas(): void
    {
        $this->autenticar();

        $guia = $this->criarGuiaAprovada('SC Saúde', 'Fonoaudiologia', 'convencional');

        $sessoes = array_fill(0, 11, [
            'data_sessao' => '2026-04-09',
            'hora_inicio' => null,
            'hora_fim' => null,
            'acompanhante' => null,
            'resumo_atividades' => null,
        ]);

        $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => $sessoes,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sessoes']);
    }
}

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

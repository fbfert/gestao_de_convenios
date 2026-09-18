<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\TabelaValor;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Relatorios\RelatorioAba;
use App\Services\Relatorios\RelatorioExportService;
use App\Support\GuiaStatus;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Exportação das tabelas de relatório.
 *
 * O arquivo é o que sai do sistema e passa a viver fora dele, então os testes
 * aqui cobrem três coisas: quem pode levar, o que vai escrito, e o registro de
 * quem levou.
 */
class RelatorioExportApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const DE = '2026-09-01';

    private const ATE = '2026-09-30';

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $agora = CarbonImmutable::parse('2026-09-20 09:00:00', 'America/Sao_Paulo');
        Carbon::setTestNow($agora);
        CarbonImmutable::setTestNow($agora);

        $this->tenantId = (int) Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
        TenantContext::set($this->tenantId);

        Lancamento::query()->withoutGlobalScopes()->delete();
        GuiaStatusHistorico::query()->withoutGlobalScopes()->delete();
        Guia::query()->withoutGlobalScopes()->delete();
        Solicitacao::query()->withoutGlobalScopes()->delete();
        TabelaValor::query()->withoutGlobalScopes()->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    // ── Permissão ───────────────────────────────────────────────────────────

    public function test_sem_a_permissao_da_aba_a_exportacao_e_negada(): void
    {
        $this->autenticar('funcionario@clinica-exemplo.test');

        $this->get($this->url(RelatorioAba::FINANCEIRO, 'por_convenio'))->assertForbidden();
    }

    public function test_tabela_desconhecida_devolve_404(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->get($this->url(RelatorioAba::OPERACAO, 'tabela_que_nao_existe'))->assertNotFound();
    }

    public function test_formato_invalido_e_recusado(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, 'por_convenio', 'pdf'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['formato']);
    }

    public function test_periodo_invalido_e_recusado_tambem_na_exportacao(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson('/api/relatorios/operacao/export?tabela=por_convenio&formato=csv')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['de', 'ate']);
    }

    // ── CSV ─────────────────────────────────────────────────────────────────

    public function test_csv_traz_cabecalho_e_linhas_no_formato_brasileiro(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');
        $this->dadosDeOperacao();

        $resposta = $this->get($this->url(RelatorioAba::OPERACAO, 'por_convenio'));

        $resposta->assertOk();
        $resposta->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $resposta->assertDownload('relatorio-operacao-por-convenio-2026-09-01-2026-09-30.csv');

        $conteudo = $resposta->streamedContent();

        // BOM, senão o Excel em pt-BR come os acentos.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $conteudo);

        $linhas = $this->linhasDoCsv($conteudo);

        $this->assertSame(
            ['Convênio', 'Solicitações', 'Guias', 'Autorizadas', 'Negadas', '% aprovação', 'Sessões', 'Faltas'],
            $linhas[0],
        );

        $unimed = collect($linhas)->firstWhere(0, 'Unimed');

        $this->assertNotNull($unimed, 'a linha do convênio precisa estar no arquivo');
        $this->assertSame('2', $unimed[2], 'guias');
        $this->assertSame('1', $unimed[3], 'autorizadas');
        $this->assertSame('50,0', $unimed[5], 'percentual com vírgula');
    }

    public function test_csv_usa_ponto_e_virgula_como_separador(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');
        $this->dadosDeOperacao();

        $conteudo = $this->get($this->url(RelatorioAba::OPERACAO, 'por_convenio'))->streamedContent();

        $primeiraLinha = strtok(substr($conteudo, 3), "\n");

        $this->assertStringContainsString(';', $primeiraLinha);
        $this->assertStringNotContainsString('Convênio,', $primeiraLinha);
    }

    public function test_dinheiro_sai_com_virgula_decimal_e_milhar_com_ponto(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');
        $this->dadosFinanceiros();

        $conteudo = $this->get($this->url(RelatorioAba::FINANCEIRO, 'por_convenio'))->streamedContent();
        $linhas = $this->linhasDoCsv($conteudo);
        $unimed = collect($linhas)->firstWhere(0, 'Unimed');

        $this->assertSame('2.500,00', $unimed[2], 'duas sessões de R$ 1.250,00');
    }

    /** Ausência de dado continua ausência no arquivo — nunca zero. */
    public function test_indicador_ausente_sai_como_celula_vazia(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        // Guia criada, nenhuma decisão: a taxa de aprovação não tem base.
        $this->guia();

        $conteudo = $this->get($this->url(RelatorioAba::OPERACAO, 'por_convenio'))->streamedContent();
        $unimed = collect($this->linhasDoCsv($conteudo))->firstWhere(0, 'Unimed');

        $this->assertSame('', $unimed[5], '% aprovação sem base de cálculo');
    }

    /**
     * Fórmula plantada num campo de texto não pode ser executada pelo Excel de
     * quem abrir o arquivo.
     */
    public function test_texto_que_comeca_com_igual_e_neutralizado(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        Convenio::query()->where('nome', 'Unimed')->update(['nome' => '=HYPERLINK("http://x","Unimed")']);
        $this->dadosDeOperacao();

        $conteudo = $this->get($this->url(RelatorioAba::OPERACAO, 'por_convenio'))->streamedContent();

        $this->assertStringContainsString('\'=HYPERLINK', $conteudo);
    }

    // ── XLSX ────────────────────────────────────────────────────────────────

    public function test_xlsx_abre_e_tem_as_linhas(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');
        $this->dadosDeOperacao();

        $resposta = $this->get($this->url(RelatorioAba::OPERACAO, 'por_convenio', 'xlsx'));

        $resposta->assertOk();
        $resposta->assertDownload('relatorio-operacao-por-convenio-2026-09-01-2026-09-30.xlsx');

        $linhas = $this->linhasDoXlsx($resposta->streamedContent());

        $this->assertSame('Convênio', $linhas[0][0]);
        $this->assertSame('Unimed', $linhas[1][0]);
        $this->assertSame(2, (int) $linhas[1][2], 'guias');
    }

    /**
     * No XLSX o número é NÚMERO, e dinheiro sai em reais: é o que permite somar
     * a coluna, que é a razão de oferecer planilha além do CSV.
     */
    public function test_xlsx_traz_dinheiro_como_numero_em_reais(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');
        $this->dadosFinanceiros();

        $linhas = $this->linhasDoXlsx(
            $this->get($this->url(RelatorioAba::FINANCEIRO, 'por_convenio', 'xlsx'))->streamedContent()
        );

        $executado = $linhas[1][2];

        $this->assertIsNumeric($executado);
        $this->assertEqualsWithDelta(2500.0, (float) $executado, 0.001);
    }

    // ── Auditoria ───────────────────────────────────────────────────────────

    public function test_a_exportacao_fica_registrada_na_trilha(): void
    {
        $usuario = $this->autenticar('admin@clinica-exemplo.test');
        $this->dadosDeOperacao();

        AuditLog::query()->withoutGlobalScopes()->delete();

        $this->get($this->url(RelatorioAba::OPERACAO, 'por_convenio'))->assertOk();

        $registro = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('acao', RelatorioExportService::ACAO_AUDITORIA)
            ->firstOrFail();

        $this->assertSame((int) $usuario->id, (int) $registro->user_id);
        $this->assertSame($this->tenantId, (int) $registro->tenant_id);
        $this->assertSame('relatorios', $registro->entidade);
        $this->assertSame(RelatorioAba::OPERACAO, $registro->payload['aba']);
        $this->assertSame('por_convenio', $registro->payload['tabela']);
        $this->assertSame('csv', $registro->payload['formato']);
        $this->assertSame(self::DE, $registro->payload['filtros']['de']);
        $this->assertSame(self::ATE, $registro->payload['filtros']['ate']);
    }

    /** Os filtros da consulta entram no registro: é o que reproduz o recorte levado. */
    public function test_a_trilha_guarda_os_filtros_aplicados(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');
        $this->dadosDeOperacao();

        AuditLog::query()->withoutGlobalScopes()->delete();

        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->get($this->url(RelatorioAba::OPERACAO, 'por_convenio').'&convenio_id='.$convenio->id)->assertOk();

        $registro = AuditLog::query()
            ->withoutGlobalScopes()
            ->where('acao', RelatorioExportService::ACAO_AUDITORIA)
            ->firstOrFail();

        $this->assertSame((int) $convenio->id, $registro->payload['filtros']['convenio_id']);
    }

    /** Negada por permissão, nada é registrado: não houve exportação. */
    public function test_exportacao_negada_nao_registra_nada(): void
    {
        $this->autenticar('funcionario@clinica-exemplo.test');

        AuditLog::query()->withoutGlobalScopes()->delete();

        $this->get($this->url(RelatorioAba::FINANCEIRO, 'por_convenio'))->assertForbidden();

        $this->assertSame(
            0,
            AuditLog::query()->withoutGlobalScopes()->where('acao', RelatorioExportService::ACAO_AUDITORIA)->count(),
        );
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    private function url(string $aba, string $tabela, string $formato = 'csv'): string
    {
        return "/api/relatorios/{$aba}/export?".http_build_query([
            'de' => self::DE,
            'ate' => self::ATE,
            'tabela' => $tabela,
            'formato' => $formato,
        ]);
    }

    private function autenticar(string $email): User
    {
        $usuario = User::query()->where('email', $email)->firstOrFail();

        Sanctum::actingAs($usuario);
        TenantContext::set((int) $usuario->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId((int) $usuario->tenant_id);

        return $usuario;
    }

    /** Duas guias no período: uma autorizada, uma negada — 50% de aprovação. */
    private function dadosDeOperacao(): void
    {
        foreach ([GuiaStatus::APPROVED, GuiaStatus::DENIED] as $i => $status) {
            $guia = $this->guia();

            $this->transicao($guia, GuiaStatus::UNDER_REVIEW, '2026-09-0'.($i + 5).' 08:00:00');
            $this->transicao($guia, $status, '2026-09-0'.($i + 5).' 12:00:00');
        }
    }

    /** Duas sessões realizadas a R$ 1.250,00. */
    private function dadosFinanceiros(): void
    {
        TabelaValor::query()->create([
            'tenant_id' => $this->tenantId,
            'convenio_id' => Convenio::query()->where('nome', 'Unimed')->firstOrFail()->id,
            'valor' => '1250.00',
            'vigente_desde' => '2026-01-01',
        ]);

        $guia = $this->guia();

        foreach (['2026-09-05', '2026-09-06'] as $dia) {
            Lancamento::query()->create([
                'tenant_id' => $this->tenantId,
                'guia_id' => $guia->id,
                'profissional_id' => $guia->profissional_id,
                'data_sessao' => $dia,
                'status' => 'completed',
            ]);
        }
    }

    private function guia(): Guia
    {
        $convenio = Convenio::query()->where('connector_type', 'manual')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->firstOrFail();

        $guia = Guia::query()->create([
            'tenant_id' => $this->tenantId,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'EXP-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::UNDER_REVIEW,
            'data_solicitacao' => '2026-09-01',
        ]);

        GuiaStatusHistorico::query()->where('guia_id', $guia->id)->delete();

        return $guia;
    }

    private function transicao(Guia $guia, string $para, string $quando): void
    {
        GuiaStatusHistorico::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'para' => $para,
            'ocorrido_em' => $quando,
            'origem' => GuiaStatusHistorico::ORIGEM_MANUAL,
        ]);
    }

    /** @return array<int, array<int, string>> */
    private function linhasDoCsv(string $conteudo): array
    {
        $linhas = [];
        $ponteiro = fopen('php://memory', 'r+');
        fwrite($ponteiro, substr($conteudo, 3)); // sem o BOM
        rewind($ponteiro);

        while (($campos = fgetcsv($ponteiro, 0, ';')) !== false) {
            $linhas[] = $campos;
        }

        fclose($ponteiro);

        return $linhas;
    }

    /** @return array<int, array<int, mixed>> */
    private function linhasDoXlsx(string $conteudo): array
    {
        $caminho = tempnam(sys_get_temp_dir(), 'rel').'.xlsx';
        file_put_contents($caminho, $conteudo);

        $leitor = new XlsxReader;
        $leitor->open($caminho);

        $linhas = [];

        foreach ($leitor->getSheetIterator() as $planilha) {
            foreach ($planilha->getRowIterator() as $linha) {
                $linhas[] = $linha->toArray();
            }

            break;
        }

        $leitor->close();
        @unlink($caminho);

        return $linhas;
    }
}

<?php

namespace Tests\Unit;

use App\Models\AiOpenaiSetting;
use App\Models\AiPromptTemplate;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\LancamentoImportLote;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\LancamentoImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LancamentoImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /** @param array<int, array<string, string>> $linhas */
    private function arquivoXlsx(array $linhas): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $cabecalho = ['numero_guia', 'convenio', 'profissional', 'data_sessao', 'hora_inicio', 'hora_fim', 'acompanhante', 'resumo_atividades', 'status', 'observacoes'];

        $sheet->fromArray($cabecalho, null, 'A1');
        $numeroLinha = 2;
        foreach ($linhas as $linha) {
            $sheet->fromArray(array_map(fn ($chave) => $linha[$chave] ?? '', $cabecalho), null, "A{$numeroLinha}");
            $numeroLinha++;
        }

        $caminho = tempnam(sys_get_temp_dir(), 'lancamentos-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($caminho);

        return new UploadedFile($caminho, 'sessoes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function tenantId(): int
    {
        return Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
    }

    /** @return array{guia: Guia, profissional: Profissional} */
    private function criarGuiaComSessoesAutorizadas(int $sessoesAutorizadas = 10): array
    {
        $tenantId = $this->tenantId();
        $convenio = Convenio::query()->where('tenant_id', $tenantId)->where('nome', 'Unimed')->firstOrFail();
        $paciente = Paciente::query()->where('tenant_id', $tenantId)->where('convenio_id', $convenio->id)->firstOrFail();
        $especialidade = Especialidade::query()->where('tenant_id', $tenantId)->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('tenant_id', $tenantId)->where('especialidade_id', $especialidade->id)->firstOrFail();

        $guia = Guia::query()->create([
            'tenant_id' => $tenantId,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-LANC-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => 'finalized',
            'sessoes_autorizadas' => $sessoesAutorizadas,
            'data_solicitacao' => '2026-01-01',
        ]);

        return ['guia' => $guia, 'profissional' => $profissional];
    }

    public function test_previsualizar_resolve_a_guia_pelo_numero_e_convenio(): void
    {
        $service = app(LancamentoImportService::class);
        $tenantId = $this->tenantId();
        $setup = $this->criarGuiaComSessoesAutorizadas();

        $arquivo = $this->arquivoXlsx([[
            'numero_guia' => $setup['guia']->numero_guia,
            'convenio' => 'Unimed',
            'profissional' => $setup['profissional']->nome,
            'data_sessao' => '20/01/2026',
            'hora_inicio' => '14:00',
        ]]);

        $resultado = $service->previsualizar($arquivo, $tenantId);

        $this->assertSame('valida', $resultado['linhas'][0]['status']);
        $this->assertSame($setup['guia']->id, $resultado['linhas'][0]['dados']['guia_id']);
    }

    public function test_confirmar_grava_sessao_direto_na_guia(): void
    {
        $service = app(LancamentoImportService::class);
        $tenantId = $this->tenantId();
        $setup = $this->criarGuiaComSessoesAutorizadas();

        $arquivo = $this->arquivoXlsx([[
            'numero_guia' => $setup['guia']->numero_guia,
            'convenio' => 'Unimed',
            'profissional' => $setup['profissional']->nome,
            'data_sessao' => '20/01/2026',
            'hora_inicio' => '14:00',
        ]]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = LancamentoImportLote::query()->findOrFail($preview['lote']['id']);
        $service->confirmar($lote, collect($preview['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(9, $setup['guia']->fresh()->sessoesDisponiveis());
        $this->assertSame(1, Lancamento::query()->where('tenant_id', $tenantId)->where('guia_id', $setup['guia']->id)->count());
    }

    public function test_confirmar_nao_bloqueia_quando_ultrapassa_a_cota_disponivel(): void
    {
        $service = app(LancamentoImportService::class);
        $tenantId = $this->tenantId();
        $setup = $this->criarGuiaComSessoesAutorizadas(sessoesAutorizadas: 1);

        $arquivo = $this->arquivoXlsx([
            ['numero_guia' => $setup['guia']->numero_guia, 'convenio' => 'Unimed', 'profissional' => $setup['profissional']->nome, 'data_sessao' => '20/01/2026', 'hora_inicio' => '14:00'],
            ['numero_guia' => $setup['guia']->numero_guia, 'convenio' => 'Unimed', 'profissional' => $setup['profissional']->nome, 'data_sessao' => '21/01/2026', 'hora_inicio' => '14:00'],
        ]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = LancamentoImportLote::query()->findOrFail($preview['lote']['id']);
        $resultado = $service->confirmar($lote, collect($preview['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(2, $resultado['lote']['total_importados']);
        $this->assertSame(2, Lancamento::query()->where('guia_id', $setup['guia']->id)->count());
        $this->assertSame(0, $setup['guia']->fresh()->sessoesDisponiveis());
    }

    public function test_confirmar_reimporta_mesma_sessao_atualiza_em_vez_de_duplicar(): void
    {
        $service = app(LancamentoImportService::class);
        $tenantId = $this->tenantId();
        $setup = $this->criarGuiaComSessoesAutorizadas();

        $linha = ['numero_guia' => $setup['guia']->numero_guia, 'convenio' => 'Unimed', 'profissional' => $setup['profissional']->nome, 'data_sessao' => '20/01/2026', 'hora_inicio' => '14:00'];

        $preview1 = $service->previsualizar($this->arquivoXlsx([$linha]), $tenantId);
        $lote1 = LancamentoImportLote::query()->findOrFail($preview1['lote']['id']);
        $service->confirmar($lote1, collect($preview1['linhas'])->pluck('id')->all(), [], $tenantId);

        $linha['acompanhante'] = 'Pai';
        $preview2 = $service->previsualizar($this->arquivoXlsx([$linha]), $tenantId);
        $lote2 = LancamentoImportLote::query()->findOrFail($preview2['lote']['id']);
        $resultado2 = $service->confirmar($lote2, collect($preview2['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(1, $resultado2['lote']['total_atualizados']);
        $this->assertSame(1, Lancamento::query()->where('tenant_id', $tenantId)->where('guia_id', $setup['guia']->id)->count());
        $this->assertSame('Pai', Lancamento::query()->where('guia_id', $setup['guia']->id)->firstOrFail()->acompanhante);
    }

    public function test_confirmar_rejeita_lote_ja_confirmado(): void
    {
        $service = app(LancamentoImportService::class);
        $tenantId = $this->tenantId();
        $setup = $this->criarGuiaComSessoesAutorizadas();

        $arquivo = $this->arquivoXlsx([[
            'numero_guia' => $setup['guia']->numero_guia,
            'convenio' => 'Unimed',
            'profissional' => $setup['profissional']->nome,
            'data_sessao' => '20/01/2026',
        ]]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = LancamentoImportLote::query()->findOrFail($preview['lote']['id']);
        $linhaIds = collect($preview['linhas'])->pluck('id')->all();
        $service->confirmar($lote, $linhaIds, [], $tenantId);

        $this->expectException(ValidationException::class);
        $service->confirmar($lote->fresh(), $linhaIds, [], $tenantId);
    }

    private function configurarIa(int $tenantId): void
    {
        AiOpenaiSetting::query()->create([
            'tenant_id' => $tenantId,
            'api_key' => 'sk-teste',
            'base_url' => 'https://api.openai.com/v1',
            'ativo' => true,
        ]);

        AiPromptTemplate::garantirPadroes($tenantId);
    }

    public function test_previsualizar_usa_ia_para_mapear_cabecalho_fora_do_modelo(): void
    {
        $tenantId = $this->tenantId();
        $this->configurarIa($tenantId);
        $setup = $this->criarGuiaComSessoesAutorizadas();

        Http::fake([
            '*/responses' => Http::response([
                'output_text' => json_encode([
                    'Nº Guia' => 'numero_guia',
                    'Plano' => 'convenio',
                    'Executante' => 'profissional',
                    'Data' => 'data_sessao',
                ]),
            ], 200),
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Nº Guia', 'Plano', 'Executante', 'Data'],
            [$setup['guia']->numero_guia, 'Unimed', $setup['profissional']->nome, '20/01/2026'],
        ], null, 'A1');

        $caminho = tempnam(sys_get_temp_dir(), 'lancamentos-import-livre-').'.xlsx';
        (new Xlsx($spreadsheet))->save($caminho);
        $arquivo = new UploadedFile($caminho, 'sessoes-livre.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $service = app(LancamentoImportService::class);
        $resultado = $service->previsualizar($arquivo, $tenantId);

        $this->assertSame(1, $resultado['lote']['total_validas']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/responses'));
    }
}

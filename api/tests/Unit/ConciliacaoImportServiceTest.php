<?php

namespace Tests\Unit;

use App\Models\ConciliacaoFinanceira;
use App\Models\ConciliacaoImportLote;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\ConciliacaoImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ConciliacaoImportServiceTest extends TestCase
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
        $cabecalho = ['numero_guia', 'convenio', 'profissional', 'quantidade', 'valor_unitario', 'valor_total', 'referencia_analitico_convenio', 'status', 'conferido_em'];

        $sheet->fromArray($cabecalho, null, 'A1');
        $numeroLinha = 2;
        foreach ($linhas as $linha) {
            $sheet->fromArray(array_map(fn ($chave) => $linha[$chave] ?? '', $cabecalho), null, "A{$numeroLinha}");
            $numeroLinha++;
        }

        $caminho = tempnam(sys_get_temp_dir(), 'conciliacoes-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($caminho);

        return new UploadedFile($caminho, 'conciliacoes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function tenantId(): int
    {
        return Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
    }

    private function criarGuia(): Guia
    {
        $tenantId = $this->tenantId();
        $convenio = Convenio::query()->where('tenant_id', $tenantId)->where('nome', 'Unimed')->firstOrFail();
        $paciente = Paciente::query()->where('tenant_id', $tenantId)->where('convenio_id', $convenio->id)->firstOrFail();
        $especialidade = Especialidade::query()->where('tenant_id', $tenantId)->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('tenant_id', $tenantId)->where('especialidade_id', $especialidade->id)->firstOrFail();

        return Guia::query()->create([
            'tenant_id' => $tenantId,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-CONC-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => 'finalized',
            'data_solicitacao' => '2026-01-01',
        ]);
    }

    public function test_confirmar_cria_conciliacao_calculando_valor_total(): void
    {
        $service = app(ConciliacaoImportService::class);
        $tenantId = $this->tenantId();
        $guia = $this->criarGuia();
        $profissional = Profissional::query()->find($guia->profissional_id);

        $arquivo = $this->arquivoXlsx([[
            'numero_guia' => $guia->numero_guia,
            'convenio' => 'Unimed',
            'profissional' => $profissional->nome,
            'quantidade' => '10',
            'valor_unitario' => '80,00',
        ]]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $this->assertSame([], $preview['linhas'][0]['erros']);
        // dados_json passa por JSON (array cast) — 800.0 vira int 800 no
        // round-trip, já que JSON não distingue float "redondo" de int.
        $this->assertEquals(800.0, $preview['linhas'][0]['dados']['valor_total']);

        $lote = ConciliacaoImportLote::query()->findOrFail($preview['lote']['id']);
        $resultado = $service->confirmar($lote, collect($preview['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(1, $resultado['lote']['total_importados']);
        $this->assertDatabaseHas('conciliacoes_financeiras', ['guia_id' => $guia->id, 'quantidade' => 10, 'status' => 'pending']);
    }

    public function test_confirmar_reimporta_mesma_guia_atualiza_em_vez_de_duplicar(): void
    {
        $service = app(ConciliacaoImportService::class);
        $tenantId = $this->tenantId();
        $guia = $this->criarGuia();
        $profissional = Profissional::query()->find($guia->profissional_id);

        $linha = ['numero_guia' => $guia->numero_guia, 'convenio' => 'Unimed', 'profissional' => $profissional->nome, 'quantidade' => '10', 'valor_unitario' => '80,00'];

        $preview1 = $service->previsualizar($this->arquivoXlsx([$linha]), $tenantId);
        $lote1 = ConciliacaoImportLote::query()->findOrFail($preview1['lote']['id']);
        $service->confirmar($lote1, collect($preview1['linhas'])->pluck('id')->all(), [], $tenantId);

        $linha['status'] = 'Paga';
        $preview2 = $service->previsualizar($this->arquivoXlsx([$linha]), $tenantId);
        $lote2 = ConciliacaoImportLote::query()->findOrFail($preview2['lote']['id']);
        $resultado2 = $service->confirmar($lote2, collect($preview2['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(1, $resultado2['lote']['total_atualizados']);
        $this->assertSame(1, ConciliacaoFinanceira::query()->where('guia_id', $guia->id)->count());
        $this->assertSame('paid', ConciliacaoFinanceira::query()->where('guia_id', $guia->id)->firstOrFail()->status);
    }

    public function test_confirmar_rejeita_lote_ja_confirmado(): void
    {
        $service = app(ConciliacaoImportService::class);
        $tenantId = $this->tenantId();
        $guia = $this->criarGuia();
        $profissional = Profissional::query()->find($guia->profissional_id);

        $arquivo = $this->arquivoXlsx([[
            'numero_guia' => $guia->numero_guia,
            'convenio' => 'Unimed',
            'profissional' => $profissional->nome,
            'quantidade' => '10',
        ]]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = ConciliacaoImportLote::query()->findOrFail($preview['lote']['id']);
        $linhaIds = collect($preview['linhas'])->pluck('id')->all();
        $service->confirmar($lote, $linhaIds, [], $tenantId);

        $this->expectException(ValidationException::class);
        $service->confirmar($lote->fresh(), $linhaIds, [], $tenantId);
    }
}

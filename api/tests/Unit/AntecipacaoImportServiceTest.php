<?php

namespace Tests\Unit;

use App\Models\AntecipacaoImportLote;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\AntecipacaoImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AntecipacaoImportServiceTest extends TestCase
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
        $cabecalho = ['numero_guia', 'convenio', 'ciclo_inicio', 'ciclo_fim', 'qtd_autorizada', 'qtd_utilizada', 'status'];

        $sheet->fromArray($cabecalho, null, 'A1');
        $numeroLinha = 2;
        foreach ($linhas as $linha) {
            $sheet->fromArray(array_map(fn ($chave) => $linha[$chave] ?? '', $cabecalho), null, "A{$numeroLinha}");
            $numeroLinha++;
        }

        $caminho = tempnam(sys_get_temp_dir(), 'antecipacoes-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($caminho);

        return new UploadedFile($caminho, 'antecipacoes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
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
            'numero_guia' => 'GUIA-ANTEC-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => 'finalized',
            'data_solicitacao' => '2026-01-01',
        ]);
    }

    public function test_confirmar_cria_antecipacao_com_status_derivado(): void
    {
        $service = app(AntecipacaoImportService::class);
        $tenantId = $this->tenantId();
        $guia = $this->criarGuia();

        $arquivo = $this->arquivoXlsx([[
            'numero_guia' => $guia->numero_guia,
            'convenio' => 'Unimed',
            'ciclo_inicio' => '01/01/2026',
            'ciclo_fim' => '31/12/2026',
            'qtd_autorizada' => '10',
            'qtd_utilizada' => '10',
        ]]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $this->assertSame([], $preview['linhas'][0]['erros']);

        $lote = AntecipacaoImportLote::query()->findOrFail($preview['lote']['id']);
        $resultado = $service->confirmar($lote, collect($preview['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(1, $resultado['lote']['total_importados']);
        $this->assertDatabaseHas('antecipacoes', ['guia_id' => $guia->id, 'qtd_autorizada' => 10, 'qtd_utilizada' => 10, 'status' => 'closed']);
    }

    public function test_confirmar_reimporta_mesmo_ciclo_atualiza_em_vez_de_duplicar(): void
    {
        $service = app(AntecipacaoImportService::class);
        $tenantId = $this->tenantId();
        $guia = $this->criarGuia();

        $linha = ['numero_guia' => $guia->numero_guia, 'convenio' => 'Unimed', 'ciclo_inicio' => '01/01/2026', 'ciclo_fim' => '31/12/2026', 'qtd_autorizada' => '10'];

        $preview1 = $service->previsualizar($this->arquivoXlsx([$linha]), $tenantId);
        $lote1 = AntecipacaoImportLote::query()->findOrFail($preview1['lote']['id']);
        $service->confirmar($lote1, collect($preview1['linhas'])->pluck('id')->all(), [], $tenantId);

        $linha['qtd_autorizada'] = '15';
        $preview2 = $service->previsualizar($this->arquivoXlsx([$linha]), $tenantId);
        $lote2 = AntecipacaoImportLote::query()->findOrFail($preview2['lote']['id']);
        $resultado2 = $service->confirmar($lote2, collect($preview2['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(1, $resultado2['lote']['total_atualizados']);
        $this->assertSame(1, \App\Models\Antecipacao::query()->where('guia_id', $guia->id)->count());
        $this->assertSame(15, \App\Models\Antecipacao::query()->where('guia_id', $guia->id)->firstOrFail()->qtd_autorizada);
    }

    public function test_confirmar_rejeita_lote_ja_confirmado(): void
    {
        $service = app(AntecipacaoImportService::class);
        $tenantId = $this->tenantId();
        $guia = $this->criarGuia();

        $arquivo = $this->arquivoXlsx([[
            'numero_guia' => $guia->numero_guia,
            'convenio' => 'Unimed',
            'ciclo_inicio' => '01/01/2026',
            'ciclo_fim' => '31/12/2026',
            'qtd_autorizada' => '10',
        ]]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = AntecipacaoImportLote::query()->findOrFail($preview['lote']['id']);
        $linhaIds = collect($preview['linhas'])->pluck('id')->all();
        $service->confirmar($lote, $linhaIds, [], $tenantId);

        $this->expectException(ValidationException::class);
        $service->confirmar($lote->fresh(), $linhaIds, [], $tenantId);
    }
}

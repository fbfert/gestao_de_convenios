<?php

namespace Tests\Unit;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\GuiaImportLote;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\GuiaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class GuiaImportServiceTest extends TestCase
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
        $cabecalho = [
            'numero_guia', 'convenio', 'paciente_cpf', 'paciente_carteirinha', 'profissional',
            'especialidade', 'tipo_terapia', 'data_solicitacao', 'status', 'senha', 'validade_senha',
            'data_finalizacao', 'sessoes_solicitadas', 'sessoes_autorizadas', 'protocolo_operadora',
            'solicitacao_protocolo', 'observacoes',
        ];

        $sheet->fromArray($cabecalho, null, 'A1');
        $numeroLinha = 2;
        foreach ($linhas as $linha) {
            $sheet->fromArray(array_map(fn ($chave) => $linha[$chave] ?? '', $cabecalho), null, "A{$numeroLinha}");
            $numeroLinha++;
        }

        $caminho = tempnam(sys_get_temp_dir(), 'guias-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($caminho);

        return new UploadedFile($caminho, 'guias.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function tenantId(): int
    {
        return Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
    }

    /** @return array<string, string> */
    private function linhaBasica(array $overrides = []): array
    {
        $convenio = Convenio::query()->where('tenant_id', $this->tenantId())->where('nome', 'Unimed')->firstOrFail();
        $paciente = Paciente::query()->where('tenant_id', $this->tenantId())->where('convenio_id', $convenio->id)->firstOrFail();
        $especialidade = Especialidade::query()->where('tenant_id', $this->tenantId())->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('tenant_id', $this->tenantId())->where('especialidade_id', $especialidade->id)->firstOrFail();

        return array_merge([
            'numero_guia' => '111222333',
            'convenio' => 'Unimed',
            'paciente_cpf' => $paciente->cpf ?? '',
            'paciente_carteirinha' => $paciente->cpf ? '' : $paciente->carteirinha,
            'profissional' => $profissional->nome,
            'especialidade' => 'Fisioterapia',
            'tipo_terapia' => 'especializada',
            'data_solicitacao' => '15/01/2026',
            'status' => '',
            'senha' => '',
            'validade_senha' => '',
            'data_finalizacao' => '',
            'sessoes_solicitadas' => '10',
            'sessoes_autorizadas' => '10',
            'protocolo_operadora' => '',
            'solicitacao_protocolo' => '',
            'observacoes' => '',
        ], $overrides);
    }

    public function test_previsualizar_linha_valida_fica_pronta_para_importar(): void
    {
        $service = app(GuiaImportService::class);
        $arquivo = $this->arquivoXlsx([$this->linhaBasica()]);

        $resultado = $service->previsualizar($arquivo, $this->tenantId());

        $this->assertSame('valida', $resultado['linhas'][0]['status']);
        $this->assertSame([], $resultado['linhas'][0]['erros']);
    }

    public function test_previsualizar_marca_erro_quando_tipo_terapia_invalido(): void
    {
        $service = app(GuiaImportService::class);
        $arquivo = $this->arquivoXlsx([$this->linhaBasica(['tipo_terapia' => 'algo_invalido'])]);

        $resultado = $service->previsualizar($arquivo, $this->tenantId());

        $this->assertSame('erro', $resultado['linhas'][0]['status']);
        $this->assertArrayHasKey('tipo_terapia', $resultado['linhas'][0]['erros']);
    }

    public function test_confirmar_cria_guia_nova(): void
    {
        $service = app(GuiaImportService::class);
        $tenantId = $this->tenantId();
        $arquivo = $this->arquivoXlsx([$this->linhaBasica(['numero_guia' => 'GUIA-E2E-1'])]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = GuiaImportLote::query()->findOrFail($preview['lote']['id']);
        $resultado = $service->confirmar($lote, collect($preview['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(1, $resultado['lote']['total_importados']);
        $this->assertDatabaseHas('guias', ['tenant_id' => $tenantId, 'numero_guia' => 'GUIA-E2E-1', 'status' => 'under_review']);
    }

    public function test_confirmar_reimporta_mesmo_numero_atualiza_em_vez_de_duplicar(): void
    {
        $service = app(GuiaImportService::class);
        $tenantId = $this->tenantId();

        $arquivo1 = $this->arquivoXlsx([$this->linhaBasica(['numero_guia' => 'GUIA-E2E-2'])]);
        $preview1 = $service->previsualizar($arquivo1, $tenantId);
        $lote1 = GuiaImportLote::query()->findOrFail($preview1['lote']['id']);
        $service->confirmar($lote1, collect($preview1['linhas'])->pluck('id')->all(), [], $tenantId);

        $arquivo2 = $this->arquivoXlsx([$this->linhaBasica(['numero_guia' => 'GUIA-E2E-2', 'status' => 'Autorizado', 'observacoes' => 'Reimportado'])]);
        $preview2 = $service->previsualizar($arquivo2, $tenantId);
        $lote2 = GuiaImportLote::query()->findOrFail($preview2['lote']['id']);
        $resultado2 = $service->confirmar($lote2, collect($preview2['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(1, $resultado2['lote']['total_atualizados']);
        $this->assertSame(1, Guia::query()->where('tenant_id', $tenantId)->where('numero_guia', 'GUIA-E2E-2')->count());
        $guia = Guia::query()->where('tenant_id', $tenantId)->where('numero_guia', 'GUIA-E2E-2')->firstOrFail();
        $this->assertSame('approved', $guia->status);
        $this->assertSame('Reimportado', $guia->observacoes);
    }

    public function test_confirmar_rejeita_lote_ja_confirmado(): void
    {
        $service = app(GuiaImportService::class);
        $tenantId = $this->tenantId();
        $arquivo = $this->arquivoXlsx([$this->linhaBasica(['numero_guia' => 'GUIA-E2E-3'])]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = GuiaImportLote::query()->findOrFail($preview['lote']['id']);
        $linhaIds = collect($preview['linhas'])->pluck('id')->all();
        $service->confirmar($lote, $linhaIds, [], $tenantId);

        $this->expectException(ValidationException::class);
        $service->confirmar($lote->fresh(), $linhaIds, [], $tenantId);
    }
}

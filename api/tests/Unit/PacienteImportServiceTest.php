<?php

namespace Tests\Unit;

use App\Models\AiOpenaiSetting;
use App\Models\AiPromptTemplate;
use App\Models\Convenio;
use App\Models\Paciente;
use App\Models\PacienteImportLote;
use App\Models\Tenant;
use App\Services\PacienteImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PacienteImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * @param array<int, array<string, string>> $linhas
     */
    private function arquivoXlsx(array $linhas): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $cabecalho = ['nome', 'cpf', 'carteirinha', 'convenio', 'validade_carteirinha', 'data_nascimento', 'telefone', 'ativo'];

        $sheet->fromArray($cabecalho, null, 'A1');

        $numeroLinha = 2;
        foreach ($linhas as $linha) {
            $sheet->fromArray(array_map(fn ($chave) => $linha[$chave] ?? '', $cabecalho), null, "A{$numeroLinha}");
            $numeroLinha++;
        }

        $caminho = tempnam(sys_get_temp_dir(), 'pacientes-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($caminho);

        return new UploadedFile($caminho, 'pacientes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function tenantId(): int
    {
        return Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
    }

    private function convenioUnimedId(): int
    {
        return Convenio::query()->where('tenant_id', $this->tenantId())->where('nome', 'Unimed')->firstOrFail()->id;
    }

    public function test_previsualizar_linha_valida_fica_pronta_para_importar(): void
    {
        $service = app(PacienteImportService::class);
        $tenantId = $this->tenantId();

        $arquivo = $this->arquivoXlsx([[
            'nome' => 'Paciente Teste Import',
            'cpf' => '111.444.777-35',
            'carteirinha' => '99988877766',
            'convenio' => 'Unimed',
            'ativo' => 'Sim',
        ]]);

        $resultado = $service->previsualizar($arquivo, $tenantId);

        $this->assertSame(1, $resultado['lote']['total_linhas']);
        $this->assertSame(1, $resultado['lote']['total_validas']);
        $this->assertSame('valida', $resultado['linhas'][0]['status']);
        $this->assertSame([], $resultado['linhas'][0]['erros']);
        $this->assertNull($resultado['linhas'][0]['matched_paciente_id']);
    }

    public function test_previsualizar_marca_erro_quando_convenio_nao_existe(): void
    {
        $service = app(PacienteImportService::class);
        $tenantId = $this->tenantId();

        $arquivo = $this->arquivoXlsx([[
            'nome' => 'Paciente Sem Convenio',
            'carteirinha' => '123',
            'convenio' => 'Convenio Que Nao Existe',
        ]]);

        $resultado = $service->previsualizar($arquivo, $tenantId);

        $this->assertSame('erro', $resultado['linhas'][0]['status']);
        $this->assertArrayHasKey('convenio', $resultado['linhas'][0]['erros']);
    }

    public function test_previsualizar_casa_paciente_existente_pelo_cpf(): void
    {
        $service = app(PacienteImportService::class);
        $tenantId = $this->tenantId();
        $convenioId = $this->convenioUnimedId();

        $existente = Paciente::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Paciente Ja Cadastrado',
            'cpf' => '11144477735',
            'carteirinha' => '00011122233',
            'convenio_id' => $convenioId,
            'ativo' => true,
        ]);

        $arquivo = $this->arquivoXlsx([[
            'nome' => 'Paciente Ja Cadastrado (nome atualizado)',
            'cpf' => '111.444.777-35',
            'carteirinha' => '00011122233',
            'convenio' => 'Unimed',
        ]]);

        $resultado = $service->previsualizar($arquivo, $tenantId);

        $this->assertSame($existente->id, $resultado['linhas'][0]['matched_paciente_id']);
    }

    public function test_previsualizar_casa_por_carteirinha_e_convenio_quando_sem_cpf(): void
    {
        $service = app(PacienteImportService::class);
        $tenantId = $this->tenantId();
        $convenioId = $this->convenioUnimedId();

        $existente = Paciente::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Paciente Sem Cpf Cadastrado',
            'cpf' => null,
            'carteirinha' => '55566677788',
            'convenio_id' => $convenioId,
            'ativo' => true,
        ]);

        $arquivo = $this->arquivoXlsx([[
            'nome' => 'Paciente Sem Cpf Cadastrado',
            'carteirinha' => '55566677788',
            'convenio' => 'Unimed',
        ]]);

        $resultado = $service->previsualizar($arquivo, $tenantId);

        $this->assertSame($existente->id, $resultado['linhas'][0]['matched_paciente_id']);
    }

    public function test_confirmar_cria_paciente_novo_e_ignora_linha_nao_selecionada(): void
    {
        $service = app(PacienteImportService::class);
        $tenantId = $this->tenantId();

        $arquivo = $this->arquivoXlsx([
            ['nome' => 'Paciente Um', 'carteirinha' => '11111111111', 'convenio' => 'Unimed'],
            ['nome' => 'Paciente Dois', 'carteirinha' => '22222222222', 'convenio' => 'Unimed'],
        ]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = PacienteImportLote::query()->findOrFail($preview['lote']['id']);
        $primeiraLinhaId = $preview['linhas'][0]['id'];

        $resultado = $service->confirmar($lote, [$primeiraLinhaId], [], $tenantId);

        $this->assertSame(1, $resultado['lote']['total_importados']);
        $this->assertSame(1, $resultado['lote']['total_ignorados']);
        $this->assertSame('confirmado', $resultado['lote']['status']);
        $this->assertDatabaseHas('pacientes', ['tenant_id' => $tenantId, 'nome' => 'Paciente Um']);
        $this->assertDatabaseMissing('pacientes', ['tenant_id' => $tenantId, 'nome' => 'Paciente Dois']);
    }

    public function test_confirmar_atualiza_paciente_existente_em_vez_de_duplicar(): void
    {
        $service = app(PacienteImportService::class);
        $tenantId = $this->tenantId();
        $convenioId = $this->convenioUnimedId();

        $existente = Paciente::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Nome Antigo',
            'cpf' => '11144477735',
            'carteirinha' => '00011122233',
            'convenio_id' => $convenioId,
            'ativo' => true,
        ]);

        $arquivo = $this->arquivoXlsx([[
            'nome' => 'Nome Atualizado',
            'cpf' => '111.444.777-35',
            'carteirinha' => '00011122233',
            'convenio' => 'Unimed',
        ]]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = PacienteImportLote::query()->findOrFail($preview['lote']['id']);
        $linhaId = $preview['linhas'][0]['id'];

        $resultado = $service->confirmar($lote, [$linhaId], [], $tenantId);

        $this->assertSame(1, $resultado['lote']['total_atualizados']);
        $this->assertSame(0, $resultado['lote']['total_importados']);
        $this->assertSame(1, Paciente::query()->where('tenant_id', $tenantId)->where('cpf', '11144477735')->count());
        $this->assertSame('Nome Atualizado', $existente->fresh()->nome);
    }

    public function test_confirmar_aplica_edicoes_antes_de_gravar(): void
    {
        $service = app(PacienteImportService::class);
        $tenantId = $this->tenantId();

        $arquivo = $this->arquivoXlsx([[
            'nome' => 'Nome Original',
            'carteirinha' => '33333333333',
            'convenio' => 'Unimed',
        ]]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = PacienteImportLote::query()->findOrFail($preview['lote']['id']);
        $linhaId = $preview['linhas'][0]['id'];

        $service->confirmar($lote, [$linhaId], [$linhaId => ['nome' => 'Nome Corrigido Na Revisao']], $tenantId);

        $this->assertDatabaseHas('pacientes', ['tenant_id' => $tenantId, 'nome' => 'Nome Corrigido Na Revisao']);
    }

    public function test_confirmar_rejeita_lote_ja_confirmado(): void
    {
        $service = app(PacienteImportService::class);
        $tenantId = $this->tenantId();

        $arquivo = $this->arquivoXlsx([[
            'nome' => 'Paciente Duplo Confirm',
            'carteirinha' => '44444444444',
            'convenio' => 'Unimed',
        ]]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = PacienteImportLote::query()->findOrFail($preview['lote']['id']);
        $linhaId = $preview['linhas'][0]['id'];

        $service->confirmar($lote, [$linhaId], [], $tenantId);

        $this->expectException(ValidationException::class);
        $service->confirmar($lote->fresh(), [$linhaId], [], $tenantId);
    }

    /**
     * @param array<int, string> $cabecalho
     * @param array<int, array<int, string>> $linhas
     */
    private function arquivoXlsxCabecalhoLivre(array $cabecalho, array $linhas): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($cabecalho, null, 'A1');

        $numeroLinha = 2;
        foreach ($linhas as $linha) {
            $sheet->fromArray($linha, null, "A{$numeroLinha}");
            $numeroLinha++;
        }

        $caminho = tempnam(sys_get_temp_dir(), 'pacientes-import-livre-').'.xlsx';
        (new Xlsx($spreadsheet))->save($caminho);

        return new UploadedFile($caminho, 'pacientes-livre.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
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
        $this->configurarIa($this->tenantId());

        Http::fake([
            '*/responses' => Http::response([
                'output_text' => json_encode([
                    'Nome do Paciente' => 'nome',
                    'Documento CPF' => 'cpf',
                    'Nº da Carteirinha' => 'carteirinha',
                    'Operadora' => 'convenio',
                ]),
            ], 200),
        ]);

        $service = app(PacienteImportService::class);
        $tenantId = $this->tenantId();

        $arquivo = $this->arquivoXlsxCabecalhoLivre(
            ['Nome do Paciente', 'Documento CPF', 'Nº da Carteirinha', 'Operadora'],
            [['Paciente Planilha Da Clinica', '111.444.777-35', '99988877766', 'Unimed']],
        );

        $resultado = $service->previsualizar($arquivo, $tenantId);

        $this->assertSame(1, $resultado['lote']['total_validas']);
        $this->assertSame('Paciente Planilha Da Clinica', $resultado['linhas'][0]['dados']['nome']);
        $this->assertSame('Unimed', $resultado['linhas'][0]['dados']['convenio']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/responses'));
    }

    public function test_previsualizar_sem_ia_configurada_mantem_erro_de_colunas(): void
    {
        // Sem configurarIa(): nenhuma AiOpenaiSetting ativa pro tenant.
        $service = app(PacienteImportService::class);
        $tenantId = $this->tenantId();

        $arquivo = $this->arquivoXlsxCabecalhoLivre(
            ['Nome do Paciente', 'Documento CPF', 'Nº da Carteirinha', 'Operadora'],
            [['Paciente Sem Ia', '111.444.777-35', '99988877766', 'Unimed']],
        );

        $this->expectException(ValidationException::class);
        $service->previsualizar($arquivo, $tenantId);
    }
}

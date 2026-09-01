<?php

namespace Tests\Unit;

use App\Models\AiOpenaiSetting;
use App\Models\AiPromptTemplate;
use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\SolicitacaoImportLote;
use App\Models\Tenant;
use App\Services\SolicitacaoImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SolicitacaoImportServiceTest extends TestCase
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
        $cabecalho = [
            'protocolo', 'paciente_cpf', 'paciente_carteirinha', 'convenio', 'medico', 'cids',
            'solicitado_em', 'status', 'observacoes', 'especialidade', 'profissional', 'quantidade',
            'item_observacoes',
        ];

        $sheet->fromArray($cabecalho, null, 'A1');

        $numeroLinha = 2;
        foreach ($linhas as $linha) {
            $sheet->fromArray(array_map(fn ($chave) => $linha[$chave] ?? '', $cabecalho), null, "A{$numeroLinha}");
            $numeroLinha++;
        }

        $caminho = tempnam(sys_get_temp_dir(), 'solicitacoes-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($caminho);

        return new UploadedFile($caminho, 'solicitacoes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
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
            'protocolo' => '',
            'paciente_cpf' => $paciente->cpf ?? '',
            'paciente_carteirinha' => $paciente->cpf ? '' : $paciente->carteirinha,
            'convenio' => 'Unimed',
            'medico' => 'Dr. Carlos Almeida',
            'cids' => 'F84.0',
            'solicitado_em' => '15/01/2026',
            'status' => '',
            'observacoes' => '',
            'especialidade' => 'Fisioterapia',
            'profissional' => $profissional->nome,
            'quantidade' => '10',
            'item_observacoes' => '',
        ], $overrides);
    }

    public function test_previsualizar_linha_valida_fica_pronta_para_importar(): void
    {
        $service = app(SolicitacaoImportService::class);
        $arquivo = $this->arquivoXlsx([$this->linhaBasica()]);

        $resultado = $service->previsualizar($arquivo, $this->tenantId());

        $this->assertSame(1, $resultado['lote']['total_validas']);
        $this->assertSame('valida', $resultado['linhas'][0]['status']);
        $this->assertSame([], $resultado['linhas'][0]['erros']);
    }

    public function test_previsualizar_marca_erro_quando_medico_nao_existe(): void
    {
        $service = app(SolicitacaoImportService::class);
        $arquivo = $this->arquivoXlsx([$this->linhaBasica(['medico' => 'Dr. Inexistente'])]);

        $resultado = $service->previsualizar($arquivo, $this->tenantId());

        $this->assertSame('erro', $resultado['linhas'][0]['status']);
        $this->assertArrayHasKey('medico', $resultado['linhas'][0]['erros']);
    }

    public function test_confirmar_agrupa_linhas_do_mesmo_protocolo_em_uma_solicitacao_com_varios_itens(): void
    {
        $service = app(SolicitacaoImportService::class);
        $tenantId = $this->tenantId();
        $fono = Especialidade::query()->where('tenant_id', $tenantId)->where('nome', 'Fonoaudiologia')->first()
            ?? Especialidade::query()->where('tenant_id', $tenantId)->firstOrFail();
        $profissionalFono = Profissional::query()->where('tenant_id', $tenantId)->where('especialidade_id', $fono->id)->first();

        $linhas = [$this->linhaBasica(['protocolo' => 'PROT-1'])];
        if ($profissionalFono) {
            $linhas[] = $this->linhaBasica([
                'protocolo' => 'PROT-1',
                'especialidade' => $fono->nome,
                'profissional' => $profissionalFono->nome,
            ]);
        } else {
            $linhas[] = $this->linhaBasica(['protocolo' => 'PROT-1']);
        }

        $arquivo = $this->arquivoXlsx($linhas);
        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = SolicitacaoImportLote::query()->findOrFail($preview['lote']['id']);
        $linhaIds = collect($preview['linhas'])->pluck('id')->all();

        $resultado = $service->confirmar($lote, $linhaIds, [], $tenantId);

        // total_importados conta linhas (itens), não solicitações — as duas
        // linhas do protocolo viram os 2 itens de UMA solicitação só.
        $this->assertSame(2, $resultado['lote']['total_importados']);
        $solicitacao = Solicitacao::query()->where('protocolo_importacao', 'PROT-1')->firstOrFail();
        $this->assertSame(2, $solicitacao->itens()->count());
    }

    public function test_confirmar_reimporta_mesmo_protocolo_atualiza_em_vez_de_duplicar(): void
    {
        $service = app(SolicitacaoImportService::class);
        $tenantId = $this->tenantId();

        $arquivo1 = $this->arquivoXlsx([$this->linhaBasica(['protocolo' => 'PROT-2'])]);
        $preview1 = $service->previsualizar($arquivo1, $tenantId);
        $lote1 = SolicitacaoImportLote::query()->findOrFail($preview1['lote']['id']);
        $service->confirmar($lote1, collect($preview1['linhas'])->pluck('id')->all(), [], $tenantId);

        $arquivo2 = $this->arquivoXlsx([$this->linhaBasica(['protocolo' => 'PROT-2', 'observacoes' => 'Reimportado'])]);
        $preview2 = $service->previsualizar($arquivo2, $tenantId);
        $lote2 = SolicitacaoImportLote::query()->findOrFail($preview2['lote']['id']);
        $resultado2 = $service->confirmar($lote2, collect($preview2['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(1, $resultado2['lote']['total_atualizados']);
        $this->assertSame(0, $resultado2['lote']['total_importados']);
        $this->assertSame(1, Solicitacao::query()->where('protocolo_importacao', 'PROT-2')->count());
        $this->assertSame('Reimportado', Solicitacao::query()->where('protocolo_importacao', 'PROT-2')->firstOrFail()->observacoes);
    }

    public function test_previsualizar_marca_erro_quando_linhas_do_mesmo_protocolo_nao_batem(): void
    {
        $service = app(SolicitacaoImportService::class);
        $tenantId = $this->tenantId();
        $outroConvenio = Convenio::query()->where('tenant_id', $tenantId)->where('nome', '!=', 'Unimed')->first();

        $linhas = [$this->linhaBasica(['protocolo' => 'PROT-3'])];
        $linhas[] = $outroConvenio
            ? $this->linhaBasica(['protocolo' => 'PROT-3', 'convenio' => $outroConvenio->nome, 'paciente_cpf' => '', 'paciente_carteirinha' => ''])
            : $this->linhaBasica(['protocolo' => 'PROT-3', 'solicitado_em' => '20/01/2026']);

        $arquivo = $this->arquivoXlsx($linhas);
        $resultado = $service->previsualizar($arquivo, $tenantId);

        $comErroDeGrupo = collect($resultado['linhas'])->filter(fn ($linha) => isset($linha['erros']['grupo']));
        $this->assertGreaterThanOrEqual(1, $comErroDeGrupo->count());
    }

    public function test_confirmar_rejeita_lote_ja_confirmado(): void
    {
        $service = app(SolicitacaoImportService::class);
        $tenantId = $this->tenantId();

        $arquivo = $this->arquivoXlsx([$this->linhaBasica()]);
        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = SolicitacaoImportLote::query()->findOrFail($preview['lote']['id']);
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

        Http::fake([
            '*/responses' => Http::response([
                'output_text' => json_encode([
                    'Convênio de Saúde' => 'convenio',
                    'Médico Solicitante' => 'medico',
                    'Código CID' => 'cids',
                    'Data do Pedido' => 'solicitado_em',
                    'Área de Atendimento' => 'especialidade',
                    'Terapeuta' => 'profissional',
                    'CPF do Paciente' => 'paciente_cpf',
                ]),
            ], 200),
        ]);

        $paciente = Paciente::query()->where('tenant_id', $tenantId)
            ->where('convenio_id', Convenio::query()->where('tenant_id', $tenantId)->where('nome', 'Unimed')->firstOrFail()->id)
            ->firstOrFail();
        $profissional = Profissional::query()->where('tenant_id', $tenantId)
            ->where('especialidade_id', Especialidade::query()->where('tenant_id', $tenantId)->where('nome', 'Fisioterapia')->firstOrFail()->id)
            ->firstOrFail();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Convênio de Saúde', 'Médico Solicitante', 'Código CID', 'Data do Pedido', 'Área de Atendimento', 'Terapeuta', 'CPF do Paciente'],
            ['Unimed', 'Dr. Carlos Almeida', 'F84.0', '15/01/2026', 'Fisioterapia', $profissional->nome, $paciente->cpf ?? '39053344705'],
        ], null, 'A1');

        $caminho = tempnam(sys_get_temp_dir(), 'solicitacoes-import-livre-').'.xlsx';
        (new Xlsx($spreadsheet))->save($caminho);
        $arquivo = new UploadedFile($caminho, 'solicitacoes-livre.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $service = app(SolicitacaoImportService::class);
        $resultado = $service->previsualizar($arquivo, $tenantId);

        $this->assertSame(1, $resultado['lote']['total_validas']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/responses'));
    }
}

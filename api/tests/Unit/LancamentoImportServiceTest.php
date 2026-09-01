<?php

namespace Tests\Unit;

use App\Models\Antecipacao;
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

    /** @return array{guia: Guia, antecipacao: Antecipacao, profissional: Profissional} */
    private function criarGuiaComAntecipacao(int $qtdAutorizada = 10): array
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
            'data_solicitacao' => '2026-01-01',
        ]);

        $antecipacao = Antecipacao::query()->create([
            'tenant_id' => $tenantId,
            'guia_id' => $guia->id,
            'paciente_id' => $paciente->id,
            'convenio_id' => $convenio->id,
            'ciclo_inicio' => '2026-01-01',
            'ciclo_fim' => '2026-12-31',
            'qtd_autorizada' => $qtdAutorizada,
            'qtd_utilizada' => 0,
            'status' => 'open',
        ]);

        return ['guia' => $guia, 'antecipacao' => $antecipacao, 'profissional' => $profissional];
    }

    public function test_previsualizar_resolve_antecipacao_pela_guia(): void
    {
        $service = app(LancamentoImportService::class);
        $tenantId = $this->tenantId();
        $setup = $this->criarGuiaComAntecipacao();

        $arquivo = $this->arquivoXlsx([[
            'numero_guia' => $setup['guia']->numero_guia,
            'convenio' => 'Unimed',
            'profissional' => $setup['profissional']->nome,
            'data_sessao' => '20/01/2026',
            'hora_inicio' => '14:00',
        ]]);

        $resultado = $service->previsualizar($arquivo, $tenantId);

        $this->assertSame('valida', $resultado['linhas'][0]['status']);
        $this->assertSame($setup['antecipacao']->id, $resultado['linhas'][0]['dados']['antecipacao_id']);
    }

    public function test_confirmar_grava_sessao_e_incrementa_cota(): void
    {
        $service = app(LancamentoImportService::class);
        $tenantId = $this->tenantId();
        $setup = $this->criarGuiaComAntecipacao();

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

        $this->assertSame(1, $setup['antecipacao']->fresh()->qtd_utilizada);
        $this->assertSame(1, Lancamento::query()->where('tenant_id', $tenantId)->where('antecipacao_id', $setup['antecipacao']->id)->count());
    }

    public function test_confirmar_nao_bloqueia_quando_ultrapassa_cota(): void
    {
        $service = app(LancamentoImportService::class);
        $tenantId = $this->tenantId();
        $setup = $this->criarGuiaComAntecipacao(qtdAutorizada: 1);

        $arquivo = $this->arquivoXlsx([
            ['numero_guia' => $setup['guia']->numero_guia, 'convenio' => 'Unimed', 'profissional' => $setup['profissional']->nome, 'data_sessao' => '20/01/2026', 'hora_inicio' => '14:00'],
            ['numero_guia' => $setup['guia']->numero_guia, 'convenio' => 'Unimed', 'profissional' => $setup['profissional']->nome, 'data_sessao' => '21/01/2026', 'hora_inicio' => '14:00'],
        ]);

        $preview = $service->previsualizar($arquivo, $tenantId);
        $lote = LancamentoImportLote::query()->findOrFail($preview['lote']['id']);
        $resultado = $service->confirmar($lote, collect($preview['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(2, $resultado['lote']['total_importados']);
        $this->assertSame(2, $setup['antecipacao']->fresh()->qtd_utilizada);
        $this->assertSame('closed', $setup['antecipacao']->fresh()->status);
    }

    public function test_confirmar_reimporta_mesma_sessao_atualiza_sem_reconsumir_cota(): void
    {
        $service = app(LancamentoImportService::class);
        $tenantId = $this->tenantId();
        $setup = $this->criarGuiaComAntecipacao();

        $linha = ['numero_guia' => $setup['guia']->numero_guia, 'convenio' => 'Unimed', 'profissional' => $setup['profissional']->nome, 'data_sessao' => '20/01/2026', 'hora_inicio' => '14:00'];

        $preview1 = $service->previsualizar($this->arquivoXlsx([$linha]), $tenantId);
        $lote1 = LancamentoImportLote::query()->findOrFail($preview1['lote']['id']);
        $service->confirmar($lote1, collect($preview1['linhas'])->pluck('id')->all(), [], $tenantId);

        $linha['acompanhante'] = 'Pai';
        $preview2 = $service->previsualizar($this->arquivoXlsx([$linha]), $tenantId);
        $lote2 = LancamentoImportLote::query()->findOrFail($preview2['lote']['id']);
        $resultado2 = $service->confirmar($lote2, collect($preview2['linhas'])->pluck('id')->all(), [], $tenantId);

        $this->assertSame(1, $resultado2['lote']['total_atualizados']);
        $this->assertSame(1, $setup['antecipacao']->fresh()->qtd_utilizada);
        $this->assertSame(1, Lancamento::query()->where('tenant_id', $tenantId)->where('antecipacao_id', $setup['antecipacao']->id)->count());
        $this->assertSame('Pai', Lancamento::query()->where('antecipacao_id', $setup['antecipacao']->id)->firstOrFail()->acompanhante);
    }

    public function test_confirmar_rejeita_lote_ja_confirmado(): void
    {
        $service = app(LancamentoImportService::class);
        $tenantId = $this->tenantId();
        $setup = $this->criarGuiaComAntecipacao();

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
}

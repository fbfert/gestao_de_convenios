<?php

namespace Tests\Unit;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\MovimentoFinanceiro;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\AntecipacaoService;
use App\Services\ConciliacaoService;
use App\Services\LancamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConciliacaoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_gerar_para_guia_soma_lancamentos_e_calcula_valor_total(): void
    {
        $antecipacaoService = app(AntecipacaoService::class);
        $lancamentoService = app(LancamentoService::class);
        $service = app(ConciliacaoService::class);

        $guia = $this->novaGuia('SC Saúde', 'Fonoaudiologia', 'convencional');
        $antecipacao = $antecipacaoService->abrirCiclo($guia);
        $profissional = Profissional::query()->where('nome', 'Dra. Paula Menezes')->firstOrFail();

        $lancamentoService->registrar($antecipacao, $profissional, today());
        $lancamentoService->registrar($antecipacao, $profissional, today()->copy()->addDay());

        $conciliacao = $service->gerarParaGuia($guia);

        $this->assertSame('pending', $conciliacao->status);
        $this->assertSame(2, $conciliacao->quantidade);
        $this->assertSame('110.00', $conciliacao->valor_unitario);
        $this->assertSame('220.00', $conciliacao->valor_total);
        $this->assertSame('64.00', $service->calcularRepasse($guia, $profissional->id, 2)['percentual_repasse_profissional']);
        $this->assertSame('140.80', $service->calcularRepasse($guia, $profissional->id, 2)['valor_repasse_total']);
    }

    public function test_calcula_repasse_usando_percentual_configurado_no_profissional(): void
    {
        $service = app(ConciliacaoService::class);

        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();
        $profissional->forceFill(['percentual_repasse' => '72.50'])->save();

        $repasse = $service->calcularRepasse($guia, $profissional->id, 3);

        $this->assertSame('72.50', $repasse['percentual_repasse_profissional']);
        $this->assertSame('27.50', $repasse['percentual_retencao_clinica']);
        $this->assertSame('116.00', $repasse['valor_repasse_unitario']);
        $this->assertSame('348.00', $repasse['valor_repasse_total']);
        $this->assertSame('44.00', $repasse['valor_retencao_unitario']);
        $this->assertSame('132.00', $repasse['valor_retencao_total']);
    }

    public function test_registra_movimentos_com_distincao_entre_profissional_informado_e_executor(): void
    {
        $antecipacaoService = app(AntecipacaoService::class);
        $lancamentoService = app(LancamentoService::class);
        $service = app(ConciliacaoService::class);

        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');
        $antecipacao = $antecipacaoService->abrirCiclo($guia);

        $profissionalInformado = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();
        $profissionalExecutor = Profissional::query()->create([
            'tenant_id' => $profissionalInformado->tenant_id,
            'especialidade_id' => $profissionalInformado->especialidade_id,
            'nome' => 'Dra. Execucao Temporaria',
            'conselho_registro' => 'CREFITO 999999-F',
            'ativo' => true,
            'percentual_repasse' => '70.00',
        ]);

        $lancamentoService->registrar($antecipacao, $profissionalExecutor, today());

        $conciliacao = $service->gerarParaGuia($guia);

        $this->assertSame(2, MovimentoFinanceiro::query()->where('conciliacao_financeira_id', $conciliacao->id)->count());
        $this->assertSame(1, $conciliacao->movimentosFinanceiros->where('tipo', 'entrada')->count());
        $saida = $conciliacao->movimentosFinanceiros->firstWhere('tipo', 'saida');
        $this->assertNotNull($saida);
        $this->assertSame($profissionalInformado->id, $saida->profissional_informado_id);
        $this->assertSame($profissionalExecutor->id, $saida->profissional_executor_id);
        $this->assertSame('98.00', $saida->valor_unitario);
    }

    private function novaGuia(string $convenioNome, string $especialidadeNome, string $tipoTerapia): Guia
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
            'numero_guia' => 'GUIA-'.uniqid(),
            'tipo_terapia' => $tipoTerapia,
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);
    }
}

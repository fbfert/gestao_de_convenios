<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\Tenant;
use App\Services\AntecipacaoService;
use App\Services\ConciliacaoService;
use App\Services\GuiaService;
use App\Services\LancamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FluxoConvenioCompletoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_fluxo_ponta_a_ponta_calcula_conciliacao_com_valor_correto(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();
        $paciente = Paciente::query()->where('nome', 'Ana Paula Ribeiro')->firstOrFail();

        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $tenant->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_solicitante' => 'Dr. Carlos Almeida',
            'status' => 'approved',
            'solicitado_em' => today(),
            'observacoes' => null,
        ]);

        $guia = Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => $solicitacao->id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-FEATURE-001',
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);

        $guiaService = app(GuiaService::class);
        $antecipacaoService = app(AntecipacaoService::class);
        $lancamentoService = app(LancamentoService::class);
        $conciliacaoService = app(ConciliacaoService::class);

        $guiaFinalizada = $guiaService->finalizar($guia, [
            'senha' => 'ABC123',
        ]);

        $antecipacao = $guiaFinalizada->antecipacoes()->firstOrFail();
        $this->assertSame('open', $antecipacao->status);
        $this->assertSame(1, $antecipacao->qtd_autorizada);
        $this->assertSame(0, $antecipacao->qtd_utilizada);

        $lancamento = $lancamentoService->registrar($antecipacao, $profissional, today());

        $antecipacao->refresh();

        $this->assertSame('completed', $lancamento->status);
        $this->assertSame('closed', $antecipacao->status);
        $this->assertSame(1, $antecipacao->qtd_utilizada);
        $this->assertSame(1, Lancamento::query()->where('antecipacao_id', $antecipacao->id)->count());

        $conciliacao = $conciliacaoService->gerarParaGuia($guiaFinalizada);

        $this->assertSame('pending', $conciliacao->status);
        $this->assertSame(1, $conciliacao->quantidade);
        $this->assertSame('160.00', $conciliacao->valor_unitario);
        $this->assertSame('160.00', $conciliacao->valor_total);
    }
}

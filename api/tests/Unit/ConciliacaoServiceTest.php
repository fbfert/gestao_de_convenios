<?php

namespace Tests\Unit;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
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

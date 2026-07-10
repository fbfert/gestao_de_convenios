<?php

namespace Tests\Unit;

use App\Exceptions\AntecipacaoCotaEsgotadaException;
use App\Models\Antecipacao;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\AntecipacaoService;
use App\Services\LancamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LancamentoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_registrar_cria_lancamento_completado_e_consumo_de_cota(): void
    {
        $antecipacaoService = app(AntecipacaoService::class);
        $service = app(LancamentoService::class);

        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');
        $antecipacao = $antecipacaoService->abrirCiclo($guia);
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();

        $lancamento = $service->registrar($antecipacao, $profissional, today());

        $antecipacao->refresh();

        $this->assertInstanceOf(Lancamento::class, $lancamento);
        $this->assertSame('completed', $lancamento->status);
        $this->assertSame($antecipacao->id, $lancamento->antecipacao_id);
        $this->assertSame($profissional->id, $lancamento->profissional_id);
        $this->assertSame(1, $antecipacao->qtd_utilizada);
        $this->assertSame('closed', $antecipacao->status);
    }

    public function test_registrar_desfaz_lancamento_quando_cota_ja_esta_fechada(): void
    {
        $antecipacaoService = app(AntecipacaoService::class);
        $service = app(LancamentoService::class);

        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');
        $antecipacao = $antecipacaoService->abrirCiclo($guia);
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();

        $antecipacao->forceFill([
            'status' => 'closed',
        ])->save();

        $this->expectException(AntecipacaoCotaEsgotadaException::class);

        try {
            $service->registrar($antecipacao, $profissional, today());
        } finally {
            $this->assertSame(0, Lancamento::query()->where('antecipacao_id', $antecipacao->id)->count());
            $this->assertSame('closed', $antecipacao->fresh()->status);
            $this->assertSame(0, $antecipacao->fresh()->qtd_utilizada);
        }
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

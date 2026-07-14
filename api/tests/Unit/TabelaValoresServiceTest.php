<?php

namespace Tests\Unit;

use App\Exceptions\TabelaValorNaoEncontradaException;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Profissional;
use App\Services\TabelaValoresService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabelaValoresServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_obtem_valor_mais_especifico_quando_profissional_bate(): void
    {
        $service = app(TabelaValoresService::class);

        $guia = $this->novaGuia('Unimed', 'Fisioterapia');
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();

        $valor = $service->obterValorVigente($guia, $profissional->id);

        $this->assertSame('160.00', $valor);
    }

    public function test_obtem_valor_do_nivel_intermediario_quando_profissional_nao_bate(): void
    {
        $service = app(TabelaValoresService::class);

        $guia = $this->novaGuia('Unimed', 'Fisioterapia');
        $profissional = Profissional::query()->where('nome', 'Dra. Paula Menezes')->firstOrFail();

        $valor = $service->obterValorVigente($guia, $profissional->id);

        $this->assertSame('140.00', $valor);
    }

    public function test_obtem_valor_geral_quando_nao_existe_esp_prof_especifico(): void
    {
        $service = app(TabelaValoresService::class);

        $guia = $this->novaGuia('Unimed', 'Terapia ABA');

        $valor = $service->obterValorVigente($guia);

        $this->assertSame('120.00', $valor);
    }

    public function test_lanca_excecao_quando_nao_encontra_nenhum_valor_vigente(): void
    {
        $service = app(TabelaValoresService::class);

        $guia = $this->novaGuia('Celos', 'Fisioterapia');

        $this->expectException(TabelaValorNaoEncontradaException::class);

        $service->obterValorVigente($guia);
    }

    public function test_criar_encerra_valor_vigente_em_cada_nivel_da_cascata(): void
    {
        $service = app(TabelaValoresService::class);
        $convenio = Convenio::query()->where('nome', 'Celos')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();

        foreach ([[null, null], [$especialidade->id, null], [$especialidade->id, $profissional->id]] as [$especialidadeId, $profissionalId]) {
            $anterior = $service->criar($convenio, ['especialidade_id' => $especialidadeId, 'profissional_id' => $profissionalId, 'valor' => 100, 'vigente_desde' => '2026-01-01']);
            $nova = $service->criar($convenio, ['especialidade_id' => $especialidadeId, 'profissional_id' => $profissionalId, 'valor' => 200, 'vigente_desde' => '2026-02-01']);
            $this->assertSame('2026-01-31', $anterior->fresh()->vigente_ate->toDateString());
            $this->assertNull($nova->vigente_ate);
        }
    }

    private function novaGuia(string $convenioNome, string $especialidadeNome): Guia
    {
        $convenio = Convenio::query()->where('nome', $convenioNome)->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', $especialidadeNome)->firstOrFail();

        return new Guia([
            'convenio_id' => $convenio->id,
            'especialidade_id' => $especialidade->id,
        ]);
    }
}

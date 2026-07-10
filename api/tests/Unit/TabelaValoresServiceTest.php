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

<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\Relatorios\RelatorioAba;
use App\Services\Relatorios\RelatorioFiltros;
use App\Services\Relatorios\RelatorioPeriodo;
use App\Support\TenantContext;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As quatro abas contra o seed de demonstração, que é o dado mais parecido com
 * o real que a suíte tem.
 *
 * Não confere número nenhum — os números têm teste próprio, com valores
 * conhecidos. O que este arquivo pega é outra coisa: consulta que passa no
 * cenário mínimo do teste e estoura sobre dado de verdade, por causa de um
 * `groupBy` incompleto, de um join que duplica linha, ou de uma coluna nula que
 * só aparece quando existe volume.
 */
class RelatorioContraSeedDemoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** @dataProvider abas */
    public function test_a_aba_responde_sobre_o_seed_de_demonstracao(string $aba): void
    {
        $this->seed(DemoDataSeeder::class);

        $tenantId = (int) Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
        TenantContext::set($tenantId);

        $relatorio = RelatorioAba::servico($aba)->montar(new RelatorioFiltros(
            // Um ano para trás: o seed espalha histórico por meses, e um recorte
            // curto poderia cair numa janela vazia e não exercitar nada.
            periodo: RelatorioPeriodo::entre(today()->subDays(364)->toDateString(), today()->toDateString()),
            tenantId: $tenantId,
            comparar: true,
        ));

        $this->assertNotEmpty($relatorio['kpis'], "aba {$aba}: sem KPIs");
        $this->assertNotEmpty($relatorio['series'], "aba {$aba}: sem séries");
        $this->assertNotEmpty($relatorio['tabelas'], "aba {$aba}: sem tabelas");

        foreach ($relatorio['kpis'] as $kpi) {
            $this->assertArrayHasKey('key', $kpi);
            $this->assertArrayHasKey('valor', $kpi);
            $this->assertArrayHasKey('anterior', $kpi);
            $this->assertContains($kpi['formato'], ['inteiro', 'percentual', 'moeda', 'horas'], $kpi['key']);
        }

        foreach ($relatorio['series'] as $serie) {
            $this->assertContains($serie['tipo'], ['linha', 'area', 'barras', 'funil', 'pizza'], $serie['key']);
            $this->assertIsArray($serie['pontos']);
        }
    }

    /** A visão do super admin atravessa os tenants sem quebrar consulta nenhuma. */
    public function test_a_visao_de_todas_as_clinicas_responde(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach (RelatorioAba::TODAS as $aba) {
            $relatorio = RelatorioAba::servico($aba)->montar(new RelatorioFiltros(
                periodo: RelatorioPeriodo::entre(today()->subDays(364)->toDateString(), today()->toDateString()),
                tenantId: null,
            ));

            $this->assertNotEmpty($relatorio['kpis'], "aba {$aba}");
        }
    }

    /** @return array<string, array{string}> */
    public static function abas(): array
    {
        return [
            'operação' => [RelatorioAba::OPERACAO],
            'financeiro' => [RelatorioAba::FINANCEIRO],
            'automações' => [RelatorioAba::AUTOMACOES],
            'uso' => [RelatorioAba::USO],
        ];
    }
}

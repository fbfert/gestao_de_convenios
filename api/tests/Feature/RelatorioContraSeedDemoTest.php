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

    /**
     * O seed precisa deixar a tela VIVA, não só sintaticamente válida.
     *
     * Este é o teste que pega o defeito silencioso do seed: dado que existe mas
     * cai todo na data errada, ou numa coluna que o relatório não consulta. Nos
     * dois casos as consultas passam e os gráficos nascem vazios — e ninguém
     * descobre até abrir a tela.
     */
    public function test_o_seed_deixa_as_quatro_abas_com_conteudo(): void
    {
        $this->seed(DemoDataSeeder::class);

        $tenantId = (int) Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
        TenantContext::set($tenantId);

        $esperado = [
            RelatorioAba::OPERACAO => ['guias_geradas', 'taxa_aprovacao', 'sessoes_realizadas', 'antecipacoes_geradas'],
            RelatorioAba::FINANCEIRO => ['valor_executado', 'valor_pago', 'valor_glosado'],
            RelatorioAba::AUTOMACOES => ['execucoes', 'taxa_sucesso', 'horas_fora_do_ar'],
            RelatorioAba::USO => ['usuarios_ativos', 'acessos', 'acoes', 'importacoes'],
        ];

        foreach ($esperado as $aba => $chaves) {
            $relatorio = RelatorioAba::servico($aba)->montar(new RelatorioFiltros(
                periodo: RelatorioPeriodo::entre(today()->subDays(89)->toDateString(), today()->toDateString()),
                tenantId: $tenantId,
            ));

            $kpis = collect($relatorio['kpis'])->keyBy('key');

            foreach ($chaves as $chave) {
                $this->assertNotNull($kpis[$chave]['valor'], "{$aba}.{$chave} ficou ausente");
                $this->assertGreaterThan(0, $kpis[$chave]['valor'], "{$aba}.{$chave} ficou zerado");
            }

            // Ao menos uma série com ponto: é o que a tela desenha.
            $this->assertTrue(
                collect($relatorio['series'])->contains(fn (array $serie) => $serie['pontos'] !== []),
                "aba {$aba}: nenhuma série tem ponto",
            );

            $this->assertTrue(
                collect($relatorio['tabelas'])->contains(fn (array $tabela) => $tabela['linhas'] !== []),
                "aba {$aba}: nenhuma tabela tem linha",
            );
        }
    }

    /**
     * A decisão das guias tem que estar espalhada no tempo.
     *
     * O hook de criação de `Guia` grava a primeira transição com a hora de
     * agora; sem o seeder reescrever o histórico, todas as guias apareceriam
     * decididas no dia em que alguém rodou o seed — um pico único, e a série
     * inteira mentindo.
     */
    public function test_as_decisoes_do_seed_estao_espalhadas_no_tempo(): void
    {
        $this->seed(DemoDataSeeder::class);

        $tenantId = (int) Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
        TenantContext::set($tenantId);

        $relatorio = RelatorioAba::servico(RelatorioAba::OPERACAO)->montar(new RelatorioFiltros(
            periodo: RelatorioPeriodo::entre(today()->subDays(89)->toDateString(), today()->toDateString()),
            tenantId: $tenantId,
        ));

        $serie = collect($relatorio['series'])->firstWhere('key', 'guias_por_status');

        $diasComDecisao = collect($serie['pontos'])
            ->filter(fn (array $ponto) => $ponto['aprovadas'] > 0 || $ponto['negadas'] > 0)
            ->count();

        $this->assertGreaterThan(10, $diasComDecisao, 'as decisões estão concentradas em poucos dias');
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

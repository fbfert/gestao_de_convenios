<?php

namespace Tests\Unit;

use App\Services\Relatorios\RelatorioPeriodo;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * O período é a peça que todo número do relatório atravessa. Um erro de um dia
 * aqui não quebra nada — só desloca a série inteira, em silêncio.
 */
class RelatorioPeriodoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Quinta-feira, para o preset de mês anterior cair num mês de 31 dias
        // (agosto) e o de 30 dias atravessar a virada de mês.
        $agora = CarbonImmutable::parse('2026-09-18 10:30:00', RelatorioPeriodo::FUSO);
        Carbon::setTestNow($agora);
        CarbonImmutable::setTestNow($agora);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_granularidade_automatica_nos_tres_limites(): void
    {
        $this->assertSame(RelatorioPeriodo::DIA, RelatorioPeriodo::granularidadeAutomatica(1));
        $this->assertSame(RelatorioPeriodo::DIA, RelatorioPeriodo::granularidadeAutomatica(31));
        $this->assertSame(RelatorioPeriodo::SEMANA, RelatorioPeriodo::granularidadeAutomatica(32));
        $this->assertSame(RelatorioPeriodo::SEMANA, RelatorioPeriodo::granularidadeAutomatica(120));
        $this->assertSame(RelatorioPeriodo::MES, RelatorioPeriodo::granularidadeAutomatica(121));
        $this->assertSame(RelatorioPeriodo::MES, RelatorioPeriodo::granularidadeAutomatica(366));
    }

    public function test_granularidade_informada_prevalece_sobre_a_automatica(): void
    {
        $periodo = RelatorioPeriodo::entre('2026-09-01', '2026-09-10', RelatorioPeriodo::MES);

        $this->assertSame(RelatorioPeriodo::MES, $periodo->granularidade);
    }

    /** O período inclui os dois extremos: 1 a 30 de setembro são 30 dias, não 29. */
    public function test_o_periodo_inclui_o_primeiro_e_o_ultimo_dia(): void
    {
        $this->assertSame(30, RelatorioPeriodo::entre('2026-09-01', '2026-09-30')->dias());
        $this->assertSame(1, RelatorioPeriodo::entre('2026-09-01', '2026-09-01')->dias());
    }

    public function test_recusa_periodo_invertido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RelatorioPeriodo::entre('2026-09-30', '2026-09-01');
    }

    public function test_recusa_periodo_acima_do_teto(): void
    {
        // 367 dias corridos.
        $this->expectException(InvalidArgumentException::class);

        RelatorioPeriodo::entre('2025-09-18', '2026-09-19');
    }

    public function test_aceita_exatamente_o_teto(): void
    {
        $periodo = RelatorioPeriodo::entre('2025-09-19', '2026-09-19');

        $this->assertSame(RelatorioPeriodo::MAX_DIAS, $periodo->dias());
    }

    public function test_periodo_anterior_tem_o_mesmo_tamanho_e_termina_na_vespera(): void
    {
        $anterior = RelatorioPeriodo::entre('2026-09-01', '2026-09-30')->anterior();

        $this->assertSame('2026-08-02', $anterior->de->toDateString());
        $this->assertSame('2026-08-31', $anterior->ate->toDateString());
        $this->assertSame(30, $anterior->dias());
    }

    /** @dataProvider presets */
    public function test_presets_e_o_periodo_anterior_de_cada_um(
        string $preset,
        string $de,
        string $ate,
        string $anteriorDe,
        string $anteriorAte,
    ): void {
        $periodo = RelatorioPeriodo::doPreset($preset);

        $this->assertSame($de, $periodo->de->toDateString(), "preset {$preset}: início");
        $this->assertSame($ate, $periodo->ate->toDateString(), "preset {$preset}: fim");

        $anterior = $periodo->anterior();

        $this->assertSame($anteriorDe, $anterior->de->toDateString(), "preset {$preset}: início do anterior");
        $this->assertSame($anteriorAte, $anterior->ate->toDateString(), "preset {$preset}: fim do anterior");
    }

    /** @return array<string, array{string, string, string, string, string}> */
    public static function presets(): array
    {
        return [
            // Hoje comparado com ontem.
            'hoje' => ['hoje', '2026-09-18', '2026-09-18', '2026-09-17', '2026-09-17'],
            // Sete dias CONTANDO hoje, e os sete imediatamente anteriores.
            '7 dias' => ['7dias', '2026-09-12', '2026-09-18', '2026-09-05', '2026-09-11'],
            '30 dias' => ['30dias', '2026-08-20', '2026-09-18', '2026-07-21', '2026-08-19'],
            // Mês atual é parcial (1 a 18); o anterior tem os mesmos 18 dias,
            // e não agosto inteiro — comparar 18 dias com 31 inflaria tudo.
            'mês atual' => ['mes_atual', '2026-09-01', '2026-09-18', '2026-08-14', '2026-08-31'],
            // Já o preset de mês anterior é fechado, e aí os dois recortes casam.
            'mês anterior' => ['mes_anterior', '2026-08-01', '2026-08-31', '2026-07-01', '2026-07-31'],
        ];
    }

    public function test_preset_desconhecido_e_recusado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RelatorioPeriodo::doPreset('trimestre');
    }

    /**
     * Data pura é lida em America/Sao_Paulo, e não no fuso do processo — senão
     * o primeiro e o último dia do intervalo pegam três horas do dia vizinho.
     */
    public function test_datas_sao_lidas_no_fuso_da_clinica(): void
    {
        $periodo = RelatorioPeriodo::entre('2026-09-01', '2026-09-30');

        $this->assertSame(RelatorioPeriodo::FUSO, $periodo->de->timezoneName);
        $this->assertSame('2026-09-01 00:00:00', $periodo->inicio()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-30 23:59:59', $periodo->fim()->format('Y-m-d H:i:s'));
    }
}

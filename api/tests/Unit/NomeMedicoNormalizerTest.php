<?php

namespace Tests\Unit;

use App\Support\NomeMedicoNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Mesma lógica do worker-unimed (`compararNomes` em worker-unimed/src/portal.js) —
 * mantenha os dois arquivos de teste em sincronia.
 *
 * Fecha a lacuna que deixou passar o bug do item 2414 (Volnei Corrêa da
 * Silva, achado ao vivo em 14/09/2026): `similaridadeAproximada` filtrava
 * conectores ("da", "de"...) só do lado candidato, nunca do lado lido — um
 * nome com conector no meio (ex. "Corrêa DA Silva") nunca pontuava 100 mesmo
 * quando a única diferença real era acentuação, ficando abaixo do limiar de
 * auto-aceite (90) e caindo pra confirmação manual sem necessidade.
 */
class NomeMedicoNormalizerTest extends TestCase
{
    public function test_nome_identico_pontua_100(): void
    {
        $this->assertSame(100.0, NomeMedicoNormalizer::similaridadeAproximada('Carlos Almeida', 'CARLOS ALMEIDA'));
    }

    public function test_conector_no_meio_do_nome_lido_nao_derruba_o_score_quando_so_difere_no_acento(): void
    {
        $score = NomeMedicoNormalizer::similaridadeAproximada('Volnei Corrêa da Silva', 'VOLNEI CORREA DA SILVA');

        $this->assertSame(100.0, $score);
    }

    public function test_nome_abreviado_com_iniciais_do_meio_pontua_acima_do_limiar_de_auto_aceite(): void
    {
        $score = NomeMedicoNormalizer::similaridadeAproximada('Edison T. F. A. Westarb', 'EDISON TEODORO FERREIRA DE ANDRADE WESTARB');

        $this->assertGreaterThanOrEqual(90.0, $score);
    }

    public function test_sobrenome_diferente_nunca_pontua_alto(): void
    {
        $this->assertSame(0.0, NomeMedicoNormalizer::similaridadeAproximada('Carlos Almeida', 'CARLOS PEREIRA'));
    }

    public function test_nome_do_meio_nao_verificavel_fica_na_faixa_ambigua(): void
    {
        $score = NomeMedicoNormalizer::similaridadeAproximada('Carlos Eduardo Almeida', 'CARLOS ALMEIDA');

        $this->assertGreaterThanOrEqual(60.0, $score);
        $this->assertLessThan(90.0, $score);
    }

    public function test_sem_tokens_do_meio_no_nome_lido_pontua_100_direto(): void
    {
        $this->assertSame(100.0, NomeMedicoNormalizer::similaridadeAproximada('Edison Westarb', 'EDISON TEODORO WESTARB'));
    }
}

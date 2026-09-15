<?php

namespace Tests\Unit;

use App\Support\ConvenioDriverCatalog;
use PHPUnit\Framework\TestCase;

class ConvenioDriverCatalogTest extends TestCase
{
    public function test_unimed_rda_tem_os_mesmos_quatro_campos_de_hoje(): void
    {
        $chaves = ConvenioDriverCatalog::chaves(ConvenioDriverCatalog::UNIMED_RDA);

        $this->assertSame(['login', 'password', 'base_url', 'nome_contratado'], $chaves);
        $this->assertSame(['login', 'password'], ConvenioDriverCatalog::chavesObrigatorias(ConvenioDriverCatalog::UNIMED_RDA));
        $this->assertTrue(ConvenioDriverCatalog::implementado(ConvenioDriverCatalog::UNIMED_RDA));
    }

    /** A senha é o único campo que nunca sai do servidor. */
    public function test_senha_e_o_campo_secreto_do_unimed_rda(): void
    {
        $this->assertSame(
            ['password'],
            ConvenioDriverCatalog::chavesSecretas(ConvenioDriverCatalog::UNIMED_RDA),
        );
    }

    /**
     * Sem campos de propósito: a forma de autenticação do SC Saúde ainda não
     * foi documentada, e inventar login e senha faria o operador preencher
     * achando que liga alguma coisa.
     */
    public function test_scsaude_entra_sem_campos_e_com_aviso(): void
    {
        $this->assertSame([], ConvenioDriverCatalog::campos(ConvenioDriverCatalog::SCSAUDE));
        $this->assertFalse(ConvenioDriverCatalog::implementado(ConvenioDriverCatalog::SCSAUDE));
        $this->assertNotNull(ConvenioDriverCatalog::aviso(ConvenioDriverCatalog::SCSAUDE));
        $this->assertTrue(ConvenioDriverCatalog::existe(ConvenioDriverCatalog::SCSAUDE));
    }

    /**
     * Driver fora do catálogo não estoura: devolve vazio, e a tela cai no mesmo
     * aviso do driver sem campos. É o que serve quando um convênio antigo guarda
     * um driver que saiu do catálogo.
     */
    public function test_driver_desconhecido_devolve_vazio_em_vez_de_estourar(): void
    {
        $this->assertFalse(ConvenioDriverCatalog::existe('nao_existe'));
        $this->assertFalse(ConvenioDriverCatalog::existe(null));
        $this->assertSame([], ConvenioDriverCatalog::campos('nao_existe'));
        $this->assertSame([], ConvenioDriverCatalog::chaves('nao_existe'));
        $this->assertSame([], ConvenioDriverCatalog::chavesObrigatorias('nao_existe'));
        $this->assertSame([], ConvenioDriverCatalog::chavesSecretas('nao_existe'));
        $this->assertNull(ConvenioDriverCatalog::rotulo('nao_existe'));
        $this->assertFalse(ConvenioDriverCatalog::implementado('nao_existe'));
    }

    public function test_resposta_leva_rotulo_aviso_e_campos(): void
    {
        $resposta = ConvenioDriverCatalog::paraResposta(ConvenioDriverCatalog::SCSAUDE);

        $this->assertSame(
            ['driver', 'rotulo', 'implementado', 'aviso', 'campos'],
            array_keys($resposta),
        );
        $this->assertSame('SC Saúde', $resposta['rotulo']);
        $this->assertFalse($resposta['implementado']);
    }

    /** Todo campo declara as quatro chaves que o formulário precisa para renderizar. */
    public function test_todo_campo_declara_o_contrato_que_o_formulario_espera(): void
    {
        foreach (ConvenioDriverCatalog::drivers() as $driver) {
            foreach (ConvenioDriverCatalog::campos($driver) as $campo) {
                $this->assertArrayHasKey('chave', $campo, "driver {$driver}");
                $this->assertArrayHasKey('rotulo', $campo, "driver {$driver}");
                $this->assertArrayHasKey('obrigatorio', $campo, "driver {$driver}");
                $this->assertContains($campo['tipo'], ['text', 'password', 'url'], "driver {$driver}");
            }
        }
    }
}

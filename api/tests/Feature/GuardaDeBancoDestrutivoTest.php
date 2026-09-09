<?php

namespace Tests\Feature;

use App\Support\GuardaDeBancoDestrutivo;
use Tests\TestCase;

/**
 * A trava que impede um `migrate:fresh` de apagar banco que nao seja de teste.
 *
 * Sem RefreshDatabase de proposito: estes testes so trocam configuracao e leem a
 * decisao da guarda; nao tocam em banco nenhum.
 */
class GuardaDeBancoDestrutivoTest extends TestCase
{
    private function apontarPara(string $banco, string $driver = 'mysql'): void
    {
        config([
            'database.default' => $driver,
            "database.connections.{$driver}.database" => $banco,
        ]);
    }

    public function test_recusa_o_banco_de_desenvolvimento(): void
    {
        $this->apontarPara('gestao_convenios');

        // É EXATAMENTE o cenário original: `.env.testing` ausente, o
        // `--env=testing` caindo no `.env` e o migrate:fresh mirando a cópia da
        // produção. A guarda tem de reprovar isso.
        $this->assertFalse(GuardaDeBancoDestrutivo::alvoEhDescartavel());
    }

    public function test_recusa_o_banco_de_producao(): void
    {
        $this->apontarPara('gescon_producao');

        $this->assertFalse(GuardaDeBancoDestrutivo::alvoEhDescartavel());
    }

    public function test_aceita_banco_com_sufixo_de_teste(): void
    {
        foreach (['gestao_convenios_e2e', 'gescon_test', 'qualquer_testing'] as $banco) {
            $this->apontarPara($banco);

            $this->assertTrue(
                GuardaDeBancoDestrutivo::alvoEhDescartavel(),
                "Esperava aceitar o banco descartavel {$banco}",
            );
        }
    }

    public function test_o_sufixo_nao_engana_com_nome_parecido(): void
    {
        // `_e2e` no meio não vale: o sufixo é o que declara o banco descartável.
        $this->apontarPara('gestao_convenios_e2e_producao');

        $this->assertFalse(GuardaDeBancoDestrutivo::alvoEhDescartavel());
    }

    public function test_aceita_sqlite_em_memoria(): void
    {
        $this->apontarPara(':memory:', 'sqlite');

        // É o alvo do RefreshDatabase da suíte unitária: morre com o processo.
        // Sem esta isenção, a suíte inteira pararia de rodar.
        $this->assertTrue(GuardaDeBancoDestrutivo::alvoEhDescartavel());
    }

    public function test_aceita_arquivo_sqlite_com_sufixo_de_teste(): void
    {
        $this->apontarPara('/var/app/database/gescon_test.sqlite', 'sqlite');

        $this->assertTrue(GuardaDeBancoDestrutivo::alvoEhDescartavel());
    }

    public function test_recusa_arquivo_sqlite_de_desenvolvimento(): void
    {
        $this->apontarPara('/var/app/database/database.sqlite', 'sqlite');

        $this->assertFalse(GuardaDeBancoDestrutivo::alvoEhDescartavel());
    }

    public function test_o_comando_de_conferencia_reprova_alvo_perigoso(): void
    {
        $this->apontarPara('gestao_convenios');

        $this->artisan('db:conferir-alvo-de-teste')
            ->expectsOutputToContain('gestao_convenios')
            ->assertFailed();
    }

    public function test_o_comando_de_conferencia_aprova_alvo_descartavel(): void
    {
        $this->apontarPara('gestao_convenios_e2e');

        $this->artisan('db:conferir-alvo-de-teste')->assertSuccessful();
    }
}

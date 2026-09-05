<?php

namespace Tests\Unit;

use App\Models\ConfiguracaoGlobal;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracaoGlobalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * Regressão do bug achado ao ligar/desligar automações a partir de um
     * job de fila (sem request HTTP na frente): a primeira chamada de
     * `doTenant()` para um tenant sem linha ainda cria a linha, mas o
     * `INSERT` só leva `tenant_id` — sem o `fresh()`, os demais campos
     * (defaults do banco) ficavam `null` na instância em memória, e um
     * `if (! $config->automacao_x_ativo)` lia isso como "desligada".
     */
    public function test_doTenant_devolve_defaults_do_banco_na_primeira_chamada_sem_request_previo(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertDatabaseMissing('configuracoes_globais', ['tenant_id' => $tenant->id]);

        $configuracao = ConfiguracaoGlobal::doTenant($tenant->id);

        $this->assertSame(480, $configuracao->sessao_minutos);
        $this->assertSame(24, $configuracao->unimed_recheck_horas_sucesso);
        $this->assertTrue($configuracao->automacao_reconsulta_status_ativo);
        $this->assertTrue($configuracao->automacao_captura_senha_validade_ativo);
        $this->assertTrue($configuracao->automacao_verificacao_incerta_ativo);
        $this->assertTrue($configuracao->automacao_sincronizacao_clinica_ativo);
        $this->assertSame(10, $configuracao->automacao_sincronizacao_clinica_diurno_intervalo_minutos);
        $this->assertSame(30, $configuracao->automacao_sincronizacao_clinica_noturno_intervalo_minutos);
        $this->assertSame(60, $configuracao->automacao_sincronizacao_clinica_madrugada_intervalo_minutos);
        $this->assertTrue($configuracao->automacao_expurgo_auditoria_ativo);
        $this->assertTrue($configuracao->automacao_expurgo_carteirinhas_ativo);
        $this->assertTrue($configuracao->automacao_verificacao_guias_diaria_ativo);
    }
}

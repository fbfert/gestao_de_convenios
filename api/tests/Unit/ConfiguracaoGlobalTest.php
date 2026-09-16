<?php

namespace Tests\Unit;

use App\Models\ConfiguracaoGlobal;
use App\Models\Tenant;
use App\Support\TenantContext;
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

    /**
     * `doTenant()` recebe o tenant por parâmetro, então tem de valer para ELE
     * mesmo quando há um `TenantContext` de outro tenant ativo.
     *
     * Sem o `withoutGlobalScope(TenantScope::class)`, o `firstOrCreate` roda
     * sob o escopo do contexto, não enxerga a linha que já existe, tenta criar
     * a segunda e esbarra no índice único de `configuracoes_globais.tenant_id`.
     * O engano passa despercebido porque o scope é no-op quando não há
     * contexto — que é o caso do worker onde `AvaliarAlertasJob` percorre os
     * tenants. O tiro só sai de dentro de uma requisição autenticada em outro
     * tenant.
     *
     * As duas metades importam: não estourar, e devolver a linha do tenant
     * PEDIDO. Sem a segunda asserção, um `doTenant()` que silenciosamente
     * devolvesse a configuração do contexto passaria.
     */
    public function test_doTenant_le_o_tenant_pedido_mesmo_sob_contexto_de_outro(): void
    {
        $dono = Tenant::factory()->create();
        $visitante = Tenant::factory()->create();

        // A linha já existe para o dono — é ela que o escopo do visitante
        // esconderia.
        $original = ConfiguracaoGlobal::doTenant($dono->id);
        $original->update(['sessao_minutos' => 321]);

        TenantContext::set($visitante->id);

        try {
            $lida = ConfiguracaoGlobal::doTenant($dono->id);

            $this->assertSame($original->id, $lida->id);
            $this->assertSame($dono->id, $lida->tenant_id);
            $this->assertSame(321, $lida->sessao_minutos);
            $this->assertSame(321, ConfiguracaoGlobal::doTenant($dono->id)->sessao_minutos);
        } finally {
            TenantContext::clear();
        }

        // E só uma linha por tenant no fim: a prova de que nada tentou criar
        // uma segunda.
        $this->assertSame(
            1,
            ConfiguracaoGlobal::query()->withoutGlobalScopes()->where('tenant_id', $dono->id)->count(),
        );
    }
}

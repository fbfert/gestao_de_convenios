<?php

namespace Tests\Feature;

use App\Http\Controllers\HealthController;
use App\Models\SaudeComponente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Semeado de propósito, ainda que nenhum teste daqui precise de usuário.
     *
     * O RefreshDatabase só semeia no `migrate:fresh` inicial, uma única vez por
     * processo — e quem dispara esse primeiro refresh é a primeira classe a
     * rodar. Sem `$seed` aqui, uma execução que comece por esta classe (o nome
     * ordena cedo) deixaria o banco sem carga para todas as outras.
     */
    protected bool $seed = true;

    /**
     * O sistema no estado que o monitor externo consideraria saudável.
     *
     * Não basta o carimbo do agendador: o seeder cria o componente `scheduler`
     * ativo e sem heartbeat, e "nunca deu sinal de vida" é `down` por decisão de
     * design — o que corretamente derruba o endpoint.
     */
    private function sistemaSaudavel(): void
    {
        Cache::forever(HealthController::CHAVE_SCHEDULER, now());

        SaudeComponente::query()->withoutGlobalScopes()->where('ativo', true)
            ->update(['ultimo_heartbeat_em' => now(), 'ultimo_status' => 'ok']);
    }

    public function test_devolve_200_quando_tudo_esta_saudavel(): void
    {
        $this->sistemaSaudavel();

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('db', 'ok')
            ->assertJsonPath('fila', 'ok');
    }

    public function test_devolve_503_quando_a_ultima_rodada_do_scheduler_esta_velha(): void
    {
        $this->sistemaSaudavel();
        Cache::forever(HealthController::CHAVE_SCHEDULER, now()->subMinutes(11));

        // O corpo continua dizendo que o banco esta ok: quem reprova aqui e o
        // cron parado, e o monitor externo enxerga isso pelo status HTTP.
        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJsonPath('db', 'ok');
    }

    public function test_devolve_503_quando_o_scheduler_nunca_rodou(): void
    {
        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJsonPath('scheduler_ultima_rodada', null);
    }

    public function test_responde_sem_autenticacao(): void
    {
        $this->sistemaSaudavel();

        // Sem token e sem actingAs: se algum dia a rota cair para dentro do
        // grupo auth:sanctum, isto vira 401 e o teste acusa.
        $this->getJson('/api/health')->assertOk();
    }

    public function test_nao_expoe_nada_alem_do_contrato_do_monitor(): void
    {
        $this->sistemaSaudavel();

        $corpo = $this->getJson('/api/health')->assertOk()->json();

        // Trava o formato inteiro, e nao a ausencia de um campo especifico: o
        // endpoint e publico, entao qualquer chave nova precisa passar por uma
        // decisao consciente de que ela pode ser lida por qualquer um.
        $this->assertSame(
            ['db', 'fila', 'scheduler_ultima_rodada', 'componentes'],
            array_keys($corpo)
        );
    }

    public function test_componentes_trazem_apenas_chave_e_estado(): void
    {
        $this->sistemaSaudavel();

        $corpo = $this->getJson('/api/health')->assertOk()->json();

        $this->assertNotEmpty($corpo['componentes']);

        foreach ($corpo['componentes'] as $componente) {
            $this->assertSame(['chave', 'estado'], array_keys($componente));
        }
    }
}

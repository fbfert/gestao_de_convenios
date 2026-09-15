<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * O carimbo da trilha é do aplicativo, não do banco.
 *
 * `audit_logs.created_at` tem `default current_timestamp()`, e com
 * `$timestamps = false` era o MySQL quem preenchia — com a hora DELE. O
 * servidor roda em UTC e o aplicativo em America/Sao_Paulo, então cada linha
 * nascia três horas à frente, e o Eloquent a lia de volta como se fosse
 * horário local.
 *
 * Os testes fixam o relógio às 21h de propósito: é a janela em que UTC já virou
 * o dia e o defeito aparecia. Fora dela tudo parecia certo, e foi por isso que
 * passou despercebido — o teste que o denunciou só falha entre 21h e
 * meia-noite.
 */
class AuditoriaFusoApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // 14/09 às 21h em São Paulo já é 15/09 em UTC.
        Carbon::setTestNow(Carbon::create(2026, 9, 14, 21, 30, 0, config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_carimbo_gravado_e_o_horario_do_aplicativo(): void
    {
        $usuario = $this->autenticar();

        $log = AuditLog::query()->create([
            'tenant_id' => $usuario->tenant_id,
            'user_id' => $usuario->id,
            'acao' => 'teste.fuso',
            'entidade' => 'convenios',
            'entidade_id' => 1,
        ]);

        $cru = DB::table('audit_logs')->where('id', $log->id)->value('created_at');

        $this->assertSame('2026-09-14 21:30:00', $cru);
    }

    /**
     * O filtro por período devolvia a página inteira nessa janela: comparava a
     * data local pedida contra um carimbo de servidor que já estava no dia
     * seguinte.
     */
    public function test_filtro_por_periodo_nao_vaza_o_dia_seguinte(): void
    {
        $usuario = $this->autenticar();

        AuditLog::query()->create([
            'tenant_id' => $usuario->tenant_id,
            'user_id' => $usuario->id,
            'acao' => 'teste.periodo',
            'entidade' => 'convenios',
            'entidade_id' => 1,
        ]);

        /*
         * Filtrando pela ação de propósito: o seed roda antes do relógio ser
         * fixado, então as linhas que ele cria ficam com a data real e
         * apareceriam aqui — o teste mediria a colisão com o seed em vez do
         * fuso.
         */
        $this->getJson('/api/auditoria?acao=teste.periodo&de='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/auditoria?acao=teste.periodo&de='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    private function autenticar(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }
}

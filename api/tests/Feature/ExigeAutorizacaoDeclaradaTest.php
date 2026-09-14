<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A inversão do padrão: rota autenticada que não declara autorização é
 * recusada, em vez de ficar aberta.
 *
 * Era assim que as portas nasciam: declarar o `permission:` dependia da memória
 * de quem escrevia a rota, e esquecer não produzia erro nenhum — produzia a
 * base de pacientes, as solicitações com CID e o payload das automações
 * acessíveis a qualquer papel.
 */
class ExigeAutorizacaoDeclaradaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_rota_nova_sem_autorizacao_declarada_e_recusada(): void
    {
        Route::middleware('api')->get('/api/rota-esquecida', fn () => response()->json(['ok' => true]));

        $this->autenticar();

        $this->getJson('/api/rota-esquecida')->assertForbidden();
    }

    public function test_rota_nova_com_permissao_declarada_passa(): void
    {
        Route::middleware(['api', 'permission:dashboard.pacientes'])
            ->get('/api/rota-declarada', fn () => response()->json(['ok' => true]));

        $this->autenticar();

        $this->getJson('/api/rota-declarada')->assertOk();
    }

    /** E continua sendo o `permission:` quem decide — este middleware não substitui a checagem. */
    public function test_rota_declarada_ainda_recusa_quem_nao_tem_a_permissao(): void
    {
        Route::middleware(['api', 'permission:dashboard.pacientes'])
            ->get('/api/rota-declarada-2', fn () => response()->json(['ok' => true]));

        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/rota-declarada-2')->assertForbidden();
    }

    /**
     * A lista de abertas não é cheque em branco: vale por método e caminho
     * exatos. `GET /api/cids` é leitura de cadastro de referência e está nela —
     * sem a inscrição, este middleware a recusaria, porque a rota não declara
     * permissão. Já a escrita não está na lista, e é barrada pelo `permission:`
     * que ela declara.
     */
    public function test_lista_de_abertas_vale_por_metodo_e_caminho_exatos(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/cids')->assertOk();
        $this->postJson('/api/cids', ['codigo' => 'X00', 'descricao' => 'Teste'])->assertForbidden();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }
}

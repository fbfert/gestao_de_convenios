<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Ordenação pelos cabeçalhos das tabelas.
 *
 * O foco aqui é o contrato: a coluna vem da query string e vai para o
 * `ORDER BY`, então o que não estiver na lista fechada precisa cair no padrão
 * em vez de virar SQL.
 */
class OrdenacaoListagensApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public static function listagens(): array
    {
        return [
            'médicos' => ['/api/medicos', 'nome'],
            'profissionais' => ['/api/profissionais', 'nome'],
            'especialidades' => ['/api/especialidades', 'nome'],
            'usuários' => ['/api/usuarios', 'nome'],
            'guias' => ['/api/guias', 'numero_guia'],
            'solicitações' => ['/api/solicitacoes', 'status'],
            'sessões' => ['/api/lancamentos', 'data'],
            'antecipações' => ['/api/antecipacoes', 'status'],
            'conciliações' => ['/api/conciliacoes', 'status'],
            'analíticos' => ['/api/analiticos', 'importado_em'],
        ];
    }

    /**
     * @dataProvider listagens
     */
    public function test_listagem_aceita_ordenacao_nos_dois_sentidos(string $rota, string $coluna): void
    {
        $this->autenticar();

        $this->getJson("{$rota}?ordenar_por={$coluna}&direcao=asc")->assertOk();
        $this->getJson("{$rota}?ordenar_por={$coluna}&direcao=desc")->assertOk();
    }

    /**
     * @dataProvider listagens
     */
    public function test_coluna_fora_da_lista_cai_no_padrao(string $rota): void
    {
        $this->autenticar();

        // Se o valor virasse ORDER BY cru, a consulta estouraria em vez de
        // devolver a listagem no padrao.
        $this->getJson($rota.'?ordenar_por=(select 1)&direcao=asc')->assertOk();
        $this->getJson($rota.'?ordenar_por=nome; drop table users&direcao=desc')->assertOk();
    }

    public function test_ordena_medicos_pelo_nome_nos_dois_sentidos(): void
    {
        $this->autenticar();

        $crescente = $this->getJson('/api/medicos?ordenar_por=nome&direcao=asc')->assertOk()->json('data');
        $decrescente = $this->getJson('/api/medicos?ordenar_por=nome&direcao=desc')->assertOk()->json('data');

        $this->assertSame(
            array_reverse(array_column($crescente, 'nome')),
            array_column($decrescente, 'nome'),
        );
    }

    private function autenticar(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail());
    }
}

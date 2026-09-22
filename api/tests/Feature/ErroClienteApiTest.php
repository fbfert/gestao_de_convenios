<?php

namespace Tests\Feature;

use App\Http\Controllers\ErroClienteController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * O canal por onde um erro do navegador chega ao servidor — ver a spec
 * `resiliencia-da-interface`.
 *
 * O que estes testes protegem é o que torna o canal útil: que ele funcione SEM
 * sessão (erro na tela de login é o caso que mais precisa dele), que identifique
 * quem era quando houver sessão, e que não vire porta de entrada para lixo.
 */
class ErroClienteApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        // O throttle é por IP e sobrevive entre testes dentro da mesma rodada.
        RateLimiter::clear('');
    }

    public function test_registra_o_erro_e_responde_sem_conteudo(): void
    {
        Log::spy();

        $this->postJson('/api/erros-cliente', [
            'message' => "Failed to execute 'insertBefore' on 'Node'",
            'stack' => 'at Botao (Botao.tsx:75)',
            'url' => 'https://gescon.test/lancamentos',
            'userAgent' => 'Mozilla/5.0',
            'occurredAt' => '2026-09-22T10:00:00.000Z',
        ])->assertNoContent();

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $mensagem, array $contexto) {
                return $mensagem === 'erro-cliente'
                    && $contexto['message'] === "Failed to execute 'insertBefore' on 'Node'"
                    && $contexto['url'] === 'https://gescon.test/lancamentos'
                    && $contexto['stack'] === 'at Botao (Botao.tsx:75)';
            });
    }

    /** O caso que motiva a rota ser pública: erro na tela de login. */
    public function test_funciona_sem_autenticacao_e_sem_tenant(): void
    {
        Log::spy();

        $this->postJson('/api/erros-cliente', ['message' => 'erro na tela de login'])
            ->assertNoContent();

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $m, array $c) => $c['tenant_id'] === null && $c['user_id'] === null);
    }

    public function test_identifica_tenant_e_usuario_quando_ha_sessao(): void
    {
        Log::spy();
        $usuario = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($usuario);

        $this->postJson('/api/erros-cliente', ['message' => 'erro com sessao'])->assertNoContent();

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $m, array $c) => $c['tenant_id'] === $usuario->tenant_id
                && $c['user_id'] === $usuario->id);
    }

    public function test_payload_sem_mensagem_e_recusado(): void
    {
        $this->postJson('/api/erros-cliente', ['stack' => 'sem mensagem'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_pilha_acima_do_limite_e_recusada(): void
    {
        $this->postJson('/api/erros-cliente', [
            'message' => 'erro',
            'stack' => str_repeat('x', 4001),
        ])->assertStatus(422)->assertJsonValidationErrors(['stack']);
    }

    /**
     * O teto existe para a rota pública não virar porta de entrada. O cliente
     * já deduplica e limita por conta própria; isto é a defesa que não depende
     * do cliente se comportar.
     */
    public function test_a_trigesima_primeira_chamada_no_minuto_e_recusada(): void
    {
        Log::spy();

        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/erros-cliente', ['message' => "erro {$i}"])->assertNoContent();
        }

        $this->postJson('/api/erros-cliente', ['message' => 'o excedente'])->assertStatus(429);
    }

    /**
     * O código da tela precisa achar a linha do log. Se as duas pontas
     * deixarem de concordar, o telefonema do suporte fica sem resposta —
     * ver `codigoDoErro` em web/src/lib/reportClientError.ts.
     */
    public function test_o_codigo_do_erro_e_estavel_e_curto(): void
    {
        $codigo = ErroClienteController::codigoDoErro('boom', 'at X');

        $this->assertSame($codigo, ErroClienteController::codigoDoErro('boom', 'at X'));
        $this->assertNotSame($codigo, ErroClienteController::codigoDoErro('boom', 'at Y'));
        $this->assertSame(6, strlen($codigo));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{6}$/', $codigo);
    }

    public function test_o_codigo_acompanha_o_registro(): void
    {
        Log::spy();

        $this->postJson('/api/erros-cliente', ['message' => 'boom', 'stack' => 'at X'])
            ->assertNoContent();

        $esperado = ErroClienteController::codigoDoErro('boom', 'at X');

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $m, array $c) => $c['codigo'] === $esperado);
    }
}

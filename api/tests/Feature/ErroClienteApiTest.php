<?php

namespace Tests\Feature;

use App\Http\Controllers\ErroClienteController;
use App\Http\Requests\RegistrarErroClienteRequest;
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

    /**
     * Valores de referência gerados pela implementação do NAVEGADOR.
     *
     * Este é o teste que faltava. `test_o_codigo_do_erro_e_estavel_e_curto`
     * prova que o PHP é estável consigo mesmo, e passava verde enquanto o PHP
     * fazia sha256 e o navegador fazia FNV-1a — a clínica lia `836920` na tela
     * e o log guardava `5F6052` para o mesmo erro.
     *
     * Estabilidade de cada lado não prova concordância entre os lados. Só um
     * valor de referência prova, e ele tem de vir de FORA do PHP.
     *
     * Os casos incluem acento e emoji fora do BMP de propósito: `charCodeAt`
     * devolve unidade UTF-16, e é aí que uma implementação em PHP que percorra
     * bytes divergiria — justamente nas mensagens em português.
     *
     * Para regerar, com a função de web/src/lib/reportClientError.ts:
     *   node -e '...codigoDoErro("mensagem", "pilha")...'
     */
    public function test_o_codigo_bate_com_o_do_navegador(): void
    {
        $referencia = [
            ['insertBefore', 'at Botao', 'D35CEE'],
            ['', '', 'F0C6CD'],
            ['Algo deu errado', '', '43BE78'],
            ['Não foi possível carregar a guia nº 12 — ação inválida', 'em SolicitacoesPage', 'E65C26'],
            ['a', '', '2524C6'],
            ['😀 emoji fora do BMP', 'pilha', 'FCF556'],
        ];

        foreach ($referencia as [$mensagem, $pilha, $esperado]) {
            $this->assertSame(
                $esperado,
                ErroClienteController::codigoDoErro($mensagem, $pilha),
                "O código do PHP divergiu do navegador para a mensagem \"{$mensagem}\". "
                .'Enquanto os dois não concordarem, o código que a tela mostra não acha nada no log.'
            );
        }
    }

    /**
     * Autenticação por cabeçalho DE VERDADE, não `Sanctum::actingAs`.
     *
     * `actingAs` injeta o usuário no guard sem passar por HTTP: ele provava que o
     * controller sabe ler `user('sanctum')`, e nunca que o navegador manda algo
     * para ele ler. Em produção não mandava — os três erros de 24/09/2026 vieram
     * de telas com usuário logado e gravaram `tenant_id: null`.
     */
    public function test_identifica_tenant_e_usuario_por_bearer_de_verdade(): void
    {
        Log::spy();
        $usuario = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        $token = $usuario->createToken('teste-relato')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/erros-cliente', ['message' => 'erro com bearer'])
            ->assertNoContent();

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $m, array $c) => $c['tenant_id'] === $usuario->tenant_id
                && $c['user_id'] === $usuario->id);
    }

    /** E sem o cabeçalho o mesmo relato chega anônimo — que é o comportamento pedido. */
    public function test_sem_bearer_o_relato_chega_anonimo(): void
    {
        Log::spy();

        $this->postJson('/api/erros-cliente', ['message' => 'erro sem bearer'])->assertNoContent();

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $m, array $c) => $c['tenant_id'] === null && $c['user_id'] === null);
    }

    /**
     * Recusa deixa rastro — e o rastro não carrega o conteúdo recusado.
     */
    public function test_relato_recusado_e_registrado_com_o_motivo(): void
    {
        Log::spy();

        $this->postJson('/api/erros-cliente', ['stack' => 'sem mensagem'])->assertStatus(422);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $mensagem, array $contexto) {
                return $mensagem === 'erro-cliente-recusado'
                    && array_key_exists('message', $contexto['motivos'])
                    && $contexto['tamanhos']['message'] === 'ausente'
                    && $contexto['tamanhos']['stack'] === 12;
            });
    }

    /** O log guarda o TAMANHO da pilha recusada, nunca a pilha. */
    public function test_a_recusa_nao_despeja_o_conteudo_no_log(): void
    {
        Log::spy();
        $pilhaEnorme = str_repeat('x', RegistrarErroClienteRequest::LIMITE_STACK + 500);

        $this->postJson('/api/erros-cliente', ['message' => 'boom', 'stack' => $pilhaEnorme])
            ->assertStatus(422);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $mensagem, array $contexto) use ($pilhaEnorme) {
                $serializado = json_encode($contexto);

                return $mensagem === 'erro-cliente-recusado'
                    && $contexto['tamanhos']['stack'] === mb_strlen($pilhaEnorme)
                    && ! str_contains((string) $serializado, $pilhaEnorme);
            });
    }

    /**
     * A etiqueta nova não se confunde com a antiga.
     *
     * Quem conta erros faz `grep erro-cliente`, e `erro-cliente-recusado`
     * conteria essa string — então a distinção tem de estar na etiqueta exata,
     * que é o primeiro argumento do log.
     */
    public function test_a_recusa_usa_etiqueta_distinta_do_erro(): void
    {
        Log::spy();

        $this->postJson('/api/erros-cliente', ['stack' => 'sem mensagem'])->assertStatus(422);

        Log::shouldNotHaveReceived('error', ['erro-cliente', \Mockery::any()]);
    }
}

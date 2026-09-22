<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarErroClienteRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Recebe os erros que acontecem no navegador da clínica.
 *
 * Existe porque, até esta change, uma queda da interface não deixava rastro
 * nenhum: a tela ficava branca e o servidor nunca ficava sabendo. Quando a
 * clínica ligava, não havia um único log para consultar.
 *
 * A rota é PÚBLICA, no precedente de `GET /health`, por um motivo concreto: um
 * erro na tela de login não tem sessão para se identificar, e é justamente na
 * tela de login que ninguém consegue reportar de outro jeito.
 *
 * Em troca, é a rota mais defendida do sistema:
 *
 * - `throttle:30,1` na definição da rota
 * - validação estrita do payload (RegistrarErroClienteRequest)
 * - nada do corpo é interpolado em lugar nenhum: vai inteiro para o log
 *   estruturado, como contexto, nunca concatenado na mensagem
 *
 * Sem tabela: uma tabela pediria tela de administração, expurgo e migration, e
 * hoje não sabemos sequer o volume. Se virar volume, vira tabela depois — ver
 * o design da change `tela-de-erro-em-vez-de-tela-branca`.
 */
class ErroClienteController extends Controller
{
    public function store(RegistrarErroClienteRequest $request): Response
    {
        $dados = $request->validated();

        /*
         * `user('sanctum')` e não `user()`: a rota está fora do grupo
         * autenticado, então não há guarda resolvida. Quando o token vier junto
         * — o caso comum, porque o erro costuma acontecer com alguém logado —
         * isto identifica quem era; quando não vier, segue sem identificação em
         * vez de recusar.
         */
        $usuario = $request->user('sanctum');

        Log::error('erro-cliente', [
            'message' => $dados['message'],
            'stack' => $dados['stack'] ?? null,
            'component_stack' => $dados['componentStack'] ?? null,
            'url' => $dados['url'] ?? null,
            'user_agent' => $dados['userAgent'] ?? null,
            'occurred_at' => $dados['occurredAt'] ?? null,
            'tenant_id' => $usuario?->tenant_id,
            'user_id' => $usuario?->id,
            // O mesmo código que a tela mostrou para quem viu o erro. É o que
            // permite casar o telefonema com a linha do log.
            'codigo' => self::codigoDoErro($dados['message'], $dados['stack'] ?? ''),
        ]);

        return response()->noContent();
    }

    /**
     * Código curto e ESTÁVEL do erro — derivado, não sorteado.
     *
     * O mesmo erro em dois usuários gera o mesmo código, o que transforma "três
     * pessoas ligaram com o código A3F2" numa informação útil em vez de três
     * investigações separadas. E o servidor o recomputa a partir do que
     * recebeu, sem depender de o cliente mandar um id que poderia não chegar.
     *
     * Espelha `codigoDoErro` em `web/src/lib/reportClientError.ts`: os dois
     * precisam concordar, senão o código da tela não acha a linha do log.
     */
    public static function codigoDoErro(string $message, string $stack): string
    {
        return strtoupper(substr(hash('sha256', $message."\n".$stack), 0, 6));
    }
}

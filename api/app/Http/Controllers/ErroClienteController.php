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
     * FNV-1a de 32 bits, espelhando `codigoDoErro` em
     * `web/src/lib/reportClientError.ts` — byte a byte, não "cada lado estável
     * consigo mesmo".
     *
     * ISTO JÁ ESTEVE ERRADO: até 24/09/2026 aqui era sha256 e no navegador
     * FNV-1a, então a clínica lia um código na tela (`836920`) e o log guardava
     * outro para o mesmo erro (`5F6052`). O código existe só para casar o
     * telefonema com a linha do registro, e não casava com nada. Os registros
     * daqueles três dias seguem com o código antigo.
     *
     * Por que FNV-1a e não sha256 nos dois: no navegador o hash tem de estar
     * pronto no instante em que a tela renderiza, e `crypto.subtle.digest` é
     * assíncrono. Este código não precisa ser criptográfico — precisa ser curto,
     * estável e igual dos dois lados.
     */
    public static function codigoDoErro(string $message, string $stack): string
    {
        $hash = 0x811C9DC5;

        foreach (self::unidadesUtf16($message."\n".$stack) as $unidade) {
            $hash ^= $unidade;
            // Multiplicação pelo primo do FNV, mantida em 32 bits sem sinal —
            // o equivalente do `Math.imul` que o JavaScript usa para não
            // estourar para ponto flutuante.
            $hash = ($hash * 0x01000193) & 0xFFFFFFFF;
        }

        return substr(str_pad(strtoupper(dechex($hash)), 6, '0', STR_PAD_LEFT), 0, 6);
    }

    /**
     * O texto como o JavaScript o percorre: unidades UTF-16, não bytes.
     *
     * `charCodeAt` devolve unidade UTF-16. Em ASCII dá no mesmo que byte, mas
     * em "Não foi possível" não dá — o PHP leria dois bytes onde o JS lê um
     * caractere, e o código divergiria justamente nas mensagens em português.
     * Converter para UTF-16BE resolve, e faz par de surrogates de emoji cair
     * igual nos dois lados, porque é assim que o JS também os enxerga.
     *
     * @return list<int>
     */
    private static function unidadesUtf16(string $texto): array
    {
        $utf16 = mb_convert_encoding($texto, 'UTF-16BE', 'UTF-8');

        if ($utf16 === false || $utf16 === '') {
            return [];
        }

        /** @var list<int> $unidades */
        $unidades = array_values(unpack('n*', $utf16) ?: []);

        return $unidades;
    }
}

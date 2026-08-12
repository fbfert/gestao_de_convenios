<?php

namespace App\Http\Middleware;

use App\Models\ConfiguracaoGlobal;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expira o login depois do tempo configurado em Configurações → Globais.
 *
 * Por que não `config('sanctum.expiration')`: aquele valor é único para a
 * instalação inteira, e aqui o prazo é por tenant.
 *
 * O prazo é contado da **emissão** do token, não do último uso. Sanctum grava
 * `last_used_at` dentro do próprio guard, antes de qualquer middleware da
 * rota rodar: ao chegar aqui o campo já vale "agora", então um tempo ocioso
 * medido por ele nunca venceria. Contar da emissão é o mesmo critério do
 * `expiration` do Sanctum, e o efeito é claro — passado o prazo, é preciso
 * entrar de novo.
 *
 * O token expirado é apagado, e não só recusado: deixá-lo no banco daria a um
 * vazamento de localStorage uma credencial que continua existindo.
 */
class EncerrarSessaoExpirada
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $minutos = ConfiguracaoGlobal::doTenant((int) $user->tenant_id)->sessao_minutos;

        // 0 desliga a expiração — a saída para quem não quer o comportamento.
        if ($minutos <= 0) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        // Em teste com Sanctum::actingAs o token é falso e não tem created_at.
        if (! $token instanceof PersonalAccessToken || ! $token->created_at) {
            return $next($request);
        }

        if ($token->created_at->addMinutes($minutos)->isFuture()) {
            return $next($request);
        }

        $token->delete();

        return response()->json([
            'message' => 'Sua sessão expirou. Entre novamente.',
        ], 401);
    }
}

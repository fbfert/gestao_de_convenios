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

        /*
         * Conta desativada, ou clínica desativada, derruba o token na hora.
         *
         * `ativo` só era conferido no login. Depois disso nada no pipeline
         * reconsultava o campo, e nenhum ponto do código apagava os tokens ao
         * desativar alguém — então o crachá continuava abrindo a porta: quem
         * saiu da clínica seguia lendo paciente, guia e auditoria com o token
         * que já tinha no navegador. Com `sessao_minutos = 0`, que desliga a
         * expiração, isso valia para sempre.
         *
         * Apaga em vez de só recusar, pelo mesmo motivo que a expiração apaga:
         * token que continua no banco volta a valer se alguém reativar a conta,
         * e o certo é obrigar a entrar de novo.
         */
        if (! $user->ativo || ! $user->tenant?->ativo) {
            $user->tokens()->delete();

            return response()->json([
                'message' => 'Seu acesso foi desativado. Procure o administrador da clínica.',
            ], 401);
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

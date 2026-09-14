<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rota autenticada sem autorização declarada é recusada.
 *
 * O sistema de permissões do projeto é bom — `PermissionCatalog`, `RoleCatalog`
 * e o `AppliesOwnScope` para "só o que é meu". O problema nunca foi o desenho:
 * era que declarar o `permission:` ficava por conta da memória de quem
 * escrevia a rota. Esquecer não dava erro nenhum; dava uma porta aberta, e foi
 * assim que a base de pacientes, as solicitações com CID, o payload das
 * automações e o `DELETE` de sessões ficaram acessíveis a qualquer papel.
 *
 * Aqui o padrão se inverte: quem não declara, não passa. Uma rota nova nasce
 * fechada, e abrir exige um gesto consciente — declarar a permissão, ou
 * inscrever a rota na lista de abertas abaixo, que é curta de propósito e
 * explica cada entrada.
 *
 * Roda depois do `permission:`, e não no lugar dele: o que este middleware
 * confere é se ALGUMA guarda foi declarada, não qual.
 */
class ExigeAutorizacaoDeclarada
{
    /**
     * Rotas abertas por decisão, no formato `MÉTODO uri`.
     *
     * Três famílias, e nada além delas:
     *
     * 1. Porta de entrada e identidade — não há permissão a exigir de quem
     *    ainda está entrando, nem para alguém ler o próprio cadastro.
     * 2. Telas de produto que valem para qualquer papel: o painel filtra os
     *    blocos por permissão por conta própria, a saúde do sistema não é
     *    privilégio de papel (ver o comentário na própria rota), e manual e
     *    novidades são conteúdo do produto.
     * 3. Leituras cujo escopo é aplicado no service, e não na rota: guias,
     *    sessões e conciliações passam pelo `AppliesOwnScope`, que já recusa
     *    quem não tem nem `view` nem `viewOwn`. Somar `permission:` ali seria
     *    declarar duas vezes a mesma regra, em dois lugares que podem divergir.
     *    Os cadastros de referência (convênios, especialidades, profissionais,
     *    CIDs) alimentam formulário de quase toda tela; a escrita deles, essa
     *    sim, é gateada.
     */
    private const ABERTAS = [
        // 1. Entrada e identidade
        'GET api/health',
        'POST api/login',
        'POST api/logout',
        'GET api/user',

        // 2. Produto, para qualquer papel
        'GET api/dashboard',
        'GET api/saude',
        'GET api/manual/{tipo?}',
        'GET api/novidades',
        'POST api/novidades/{slug}/lida',

        // 3a. Leitura com escopo no service
        'GET api/guias',
        'GET api/guias/{guia}',
        'GET api/lancamentos',
        'GET api/lancamentos/{lancamento}',
        'GET api/conciliacoes',

        // 3b. Cadastros de referência (só leitura)
        'GET api/convenios',
        'GET api/convenios/{convenio}',
        'GET api/especialidades',
        'GET api/profissionais',
        'GET api/cids',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $rota = $request->route();

        if (! $rota) {
            return $next($request);
        }

        if ($this->declaraGuarda($rota->gatherMiddleware())) {
            return $next($request);
        }

        if (in_array($request->method().' '.$rota->uri(), self::ABERTAS, true)) {
            return $next($request);
        }

        abort(403, 'Esta rota não declara autorização. Declare a permissão necessária ou inscreva-a como aberta por decisão.');
    }

    /**
     * @param  array<int, mixed>  $middleware
     */
    private function declaraGuarda(array $middleware): bool
    {
        foreach ($middleware as $item) {
            if (! is_string($item)) {
                continue;
            }

            /*
             * As duas formas, de propósito: `gatherMiddleware()` devolve o que
             * foi escrito na rota, ou seja o alias cru (`permission:x`), e é o
             * `route:list` que resolve para a classe. Aceitar só um dos formatos
             * faria este middleware recusar rota devidamente declarada.
             */
            if (str_starts_with($item, 'permission:') || $item === 'super-admin') {
                return true;
            }

            if (str_contains($item, 'PermissionMiddleware') || str_contains($item, 'EnsureSuperAdmin')) {
                return true;
            }
        }

        return false;
    }
}

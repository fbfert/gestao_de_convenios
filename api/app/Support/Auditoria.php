<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Escrita da trilha de auditoria.
 *
 * Duas portas de entrada:
 *
 * - o trait App\Concerns\Auditable, que registra sozinho created/updated/
 *   deleted de quem o usa. É o caminho normal, e existe porque chamar
 *   `AuditLog::create()` na mão dentro de cada controller garante que todo
 *   endpoint novo nasça sem auditoria — o defeito que este change corrige.
 * - `registrar()`, para evento que não é mudança de modelo: login, logout,
 *   recusa por permissão, importação em lote, expurgo.
 *
 * Valor de campo sensível nunca entra. O nome do campo entra em
 * `campos_ocultos`, e o par antes/depois nem chega a ser montado para ele.
 */
class Auditoria
{
    /**
     * Nome de campo que denuncia credencial. Existe além da lista declarada em
     * cada modelo porque campo sensível novo costuma aparecer sem ninguém
     * lembrar de declarar — e o custo de esquecer é a senha em texto claro na
     * tela de auditoria.
     *
     * `senha`, `chave` e `key` soltos ficaram DE FORA de propósito. Neste
     * domínio "senha" é o código de autorização que o convênio devolve, e é
     * justamente o dado que a auditoria precisa mostrar: `guias.senha`,
     * `validade_senha`, `senha_alerta_dias`, `chave_conciliacao`. Um padrão
     * genérico esconderia o miolo da trilha para proteger o que já está
     * protegido por nomes específicos.
     */
    private const PADROES_SENSIVEIS = [
        'password', 'passwd', 'secret', 'api_key', 'apikey', 'private_key',
        'access_token', 'refresh_token', 'token', 'credential', 'credencial',
    ];

    /** Campos de controle que só poluiriam o diff. */
    private const IGNORADOS = ['created_at', 'updated_at', 'remember_token'];

    private static bool $automaticoLigado = true;

    public static function ehSensivel(string $campo, array $declarados = []): bool
    {
        if (in_array($campo, $declarados, true)) {
            return true;
        }

        $normalizado = strtolower($campo);

        foreach (self::PADROES_SENSIVEIS as $padrao) {
            if (str_contains($normalizado, $padrao)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Suspende o registro automático dentro do callback.
     *
     * Usado onde já existe um evento explícito melhor que o diff cru — a pausa
     * e a reativação da automação da Unimed dizem o motivo, coisa que
     * `ativo: true -> false` não diz. Sem isso a mesma ação viraria dois
     * registros.
     */
    public static function semRegistroAutomatico(callable $callback): mixed
    {
        $anterior = self::$automaticoLigado;
        self::$automaticoLigado = false;

        try {
            return $callback();
        } finally {
            self::$automaticoLigado = $anterior;
        }
    }

    public static function automaticoLigado(): bool
    {
        return self::$automaticoLigado;
    }

    public static function registrarModelo(Model $modelo, string $acao, array $antes, array $depois, array $ocultos): void
    {
        $tenantId = $modelo->getAttribute('tenant_id') ?? TenantContext::get();

        if (! $tenantId) {
            // Sem tenant não há a quem atribuir o evento, e a coluna é
            // obrigatória. Acontece em seeder e em console fora de contexto.
            return;
        }

        $payload = [];

        if ($antes !== []) {
            $payload['antes'] = $antes;
        }

        if ($depois !== []) {
            $payload['depois'] = $depois;
        }

        if ($ocultos !== []) {
            $payload['campos_ocultos'] = array_values($ocultos);
        }

        self::gravar(
            tenantId: (int) $tenantId,
            acao: $acao,
            entidade: $modelo->getTable(),
            entidadeId: (int) $modelo->getKey(),
            payload: $payload,
        );
    }

    /**
     * Evento que não é mudança de modelo: acesso, lote, expurgo.
     *
     * `$doSistema` força a autoria do sistema mesmo dentro de uma requisição
     * autenticada. É o caso do circuit breaker: quem pausa a automação é a
     * regra, não a pessoa que por acaso disparou a chamada que falhou.
     */
    public static function registrar(
        string $acao,
        string $entidade,
        int $entidadeId,
        array $payload = [],
        ?int $tenantId = null,
        ?int $userId = null,
        bool $comOrigem = false,
        bool $doSistema = false,
    ): void {
        $tenantId ??= TenantContext::get() ?? request()?->user()?->tenant_id;

        if (! $tenantId) {
            return;
        }

        self::gravar(
            tenantId: (int) $tenantId,
            acao: $acao,
            entidade: $entidade,
            entidadeId: $entidadeId,
            payload: $payload,
            userId: $userId,
            comOrigem: $comOrigem,
            doSistema: $doSistema,
        );
    }

    private static function gravar(
        int $tenantId,
        string $acao,
        string $entidade,
        int $entidadeId,
        array $payload,
        ?int $userId = null,
        bool $comOrigem = false,
        bool $doSistema = false,
    ): void {
        $request = request();

        AuditLog::query()->create([
            'tenant_id' => $tenantId,
            // Sem usuário na requisição o evento é do sistema: job agendado,
            // worker de automação, console. `user_id` nulo é o que a tela
            // mostra como "Sistema".
            'user_id' => $doSistema ? null : ($userId ?? $request?->user()?->id),
            'acao' => $acao,
            'entidade' => $entidade,
            'entidade_id' => $entidadeId,
            'payload' => $payload === [] ? null : $payload,
            'ip' => $comOrigem ? $request?->ip() : null,
            'user_agent' => $comOrigem ? substr((string) $request?->userAgent(), 0, 255) : null,
        ]);
    }

    /**
     * Separa o que mudou em antes/depois, tirando os campos sensíveis do meio.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: string[]}
     */
    public static function diff(array $original, array $atual, array $declarados): array
    {
        $antes = [];
        $depois = [];
        $ocultos = [];

        foreach ($atual as $campo => $valor) {
            if (in_array($campo, self::IGNORADOS, true)) {
                continue;
            }

            $anterior = $original[$campo] ?? null;

            if ($anterior === $valor) {
                continue;
            }

            if (self::ehSensivel($campo, $declarados)) {
                $ocultos[] = $campo;
                continue;
            }

            $antes[$campo] = $anterior;
            $depois[$campo] = $valor;
        }

        return [$antes, $depois, $ocultos];
    }
}

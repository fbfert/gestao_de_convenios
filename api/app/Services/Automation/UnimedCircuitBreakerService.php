<?php

namespace App\Services\Automation;

use App\Models\AutomacaoExecucao;
use App\Repositories\ConvenioCredencialRepository;
use App\Support\Auditoria;

/**
 * Pausa a automação de UM convênio quando a falha é estrutural.
 *
 * Antes recebia apenas o `tenantId` e pausava a única credencial que existia.
 * Em 14/09/2026 um `WORKER_INTERNAL_FATAL` num único item derrubou a automação
 * de todos os itens do tenant por causa disso, e a credencial precisou ser
 * reativada à mão duas vezes na mesma sessão. Com um segundo convênio
 * automatizado, teria derrubado também o que não tinha problema nenhum.
 *
 * Agora a execução diz de qual convênio veio a falha, e só a credencial dele é
 * pausada. Continua pausando quando deve: o gate é o mesmo
 * `isStructuralResult()` de sempre.
 */
class UnimedCircuitBreakerService
{
    public function __construct(
        private readonly AutomationErrorCatalog $catalog,
        private readonly ConvenioCredencialRepository $credenciais,
    ) {}

    public function handleResult(AutomacaoExecucao $execucao, array $result): void
    {
        $code = $result['error_code'] ?? $result['erro_codigo'] ?? null;

        if (! $this->catalog->isStructuralResult($result)) {
            return;
        }

        $credencial = $this->credenciais->paraConvenio(
            (int) $execucao->tenant_id,
            $this->credenciais->convenioDaExecucao($execucao),
        );

        if (! $credencial) {
            return;
        }

        // O automatico e suspenso porque o evento explicito diz o motivo da
        // pausa, e `ativo: true -> false` sozinho nao diria.
        Auditoria::semRegistroAutomatico(fn () => $credencial->forceFill([
            'ativo' => false,
            'automation_paused_at' => now(),
            'automation_paused_reason' => $code,
        ])->save());

        Auditoria::registrar(
            acao: 'unimed_rda.automation_paused',
            entidade: 'convenio_credenciais',
            entidadeId: (int) $credencial->id,
            payload: [
                'reason' => $code,
                'label' => $this->catalog->label($code),
                // O convênio entra no evento porque agora a pausa é dele, e não
                // do tenant: sem isso a trilha não diria o que parou.
                'convenio_id' => $credencial->convenio_id,
            ],
            tenantId: (int) $execucao->tenant_id,
            // Pausa vem do circuit breaker, nunca de uma pessoa: fica como
            // evento do sistema mesmo dentro de uma requisicao autenticada.
            doSistema: true,
        );
    }
}

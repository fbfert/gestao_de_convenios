<?php

namespace App\Jobs;

use App\Models\ClinicaConexaoConfig;
use App\Models\ConfiguracaoGlobal;
use App\Services\ClinicaSync\ClinicaSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SincronizarClinicaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $origem = 'agendado') {}

    /**
     * O tick deste job continua fixo (a cada 5 min, routes/console.php); o
     * "intervalo" configurável por tenant em /automacoes/configuracoes só
     * decide se já passou tempo suficiente desde a última execução daquele
     * tenant, igual ao padrão já usado para a reconsulta de status Unimed.
     * Só se aplica ao tick agendado — "Sincronizar Agora" (origem=manual)
     * sempre roda, mesmo com a automação pausada.
     */
    public function handle(ClinicaSyncService $service): void
    {
        ClinicaConexaoConfig::where('ativo', true)->get()->each(function (ClinicaConexaoConfig $config) use ($service) {
            if ($this->origem === 'agendado') {
                $automacao = ConfiguracaoGlobal::doTenant($config->tenant_id);

                if (! $automacao->automacao_sincronizacao_clinica_ativo) {
                    return;
                }

                if ($config->ultima_execucao_em
                    && $config->ultima_execucao_em->addMinutes($automacao->automacao_sincronizacao_clinica_intervalo_minutos)->isFuture()) {
                    return;
                }
            }

            $service->executar($config->tenant_id, $this->origem);
        });
    }
}

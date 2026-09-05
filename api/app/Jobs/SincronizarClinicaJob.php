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
                    && $config->ultima_execucao_em->addMinutes($this->intervaloAtual($automacao))->isFuture()) {
                    return;
                }
            }

            $service->executar($config->tenant_id, $this->origem);
        });
    }

    /**
     * O ritmo varia por horário — comercial, fim de tarde e madrugada têm
     * volumes de mudança bem diferentes. As 3 janelas são configuráveis em
     * /automacoes/configuracoes; "madrugada" é o catch-all (cobre o resto do
     * dia mesmo que a reconfiguração manual deixe uma lacuna nas 24h).
     */
    private function intervaloAtual(ConfiguracaoGlobal $c): int
    {
        $agora = now()->format('H:i:s');

        if ($this->dentroDaJanela($agora, $c->automacao_sincronizacao_clinica_diurno_horario_inicio, $c->automacao_sincronizacao_clinica_diurno_horario_fim)) {
            return $c->automacao_sincronizacao_clinica_diurno_intervalo_minutos;
        }

        if ($this->dentroDaJanela($agora, $c->automacao_sincronizacao_clinica_noturno_horario_inicio, $c->automacao_sincronizacao_clinica_noturno_horario_fim)) {
            return $c->automacao_sincronizacao_clinica_noturno_intervalo_minutos;
        }

        return $c->automacao_sincronizacao_clinica_madrugada_intervalo_minutos;
    }

    /** Suporta janela que cruza a meia-noite (fim < início, ex: madrugada 22h-08h). */
    private function dentroDaJanela(string $agora, string $inicio, string $fim): bool
    {
        $inicio = substr($inicio, 0, 8);
        $fim = substr($fim, 0, 8);

        if ($inicio <= $fim) {
            return $agora >= $inicio && $agora <= $fim;
        }

        return $agora >= $inicio || $agora <= $fim;
    }
}

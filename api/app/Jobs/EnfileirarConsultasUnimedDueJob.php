<?php

namespace App\Jobs;

use App\Models\ConfiguracaoGlobal;
use App\Models\Guia;
use App\Models\SolicitacaoItem;
use App\Services\Automation\CapturarSenhaValidadeUnimedService;
use App\Services\Automation\ConfirmarGuiaIncertaUnimedService;
use App\Services\Automation\ConsultarStatusUnimedService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class EnfileirarConsultasUnimedDueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('automacoes');
    }

    /** @var array<int, ConfiguracaoGlobal> */
    private array $configPorTenant = [];

    public function handle(
        ConsultarStatusUnimedService $consultarStatus,
        CapturarSenhaValidadeUnimedService $capturarSenhaValidade,
        ConfirmarGuiaIncertaUnimedService $confirmarGuiaIncerta,
    ): void
    {
        Guia::query()
            ->with('convenio')
            ->whereHas('convenio', fn ($query) => $query->where('connector_driver', 'unimed_rda'))
            ->whereNotNull('numero_guia')
            ->whereNotIn('status', ['approved', 'denied', 'canceled', 'finalized', 'needs_verification'])
            ->where(function ($query) {
                $query->whereNull('unimed_next_check_at')
                    ->orWhere('unimed_next_check_at', '<=', now());
            })
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get()
            ->each(function (Guia $guia) use ($consultarStatus) {
                if (! $this->configPara($guia->tenant_id)->automacao_reconsulta_status_ativo) {
                    return;
                }

                $lock = Cache::lock("automacao:unimed:due:tenant:{$guia->tenant_id}:consult_status_batch", 60);

                if (! $lock->get()) {
                    return;
                }

                try {
                    $consultarStatus->enviar($guia);
                } catch (ValidationException) {
                    // Guia deixou de ser elegivel entre a busca e o enqueue.
                } finally {
                    $lock->release();
                }
            });

        Guia::query()
            ->with('convenio')
            ->whereHas('convenio', fn ($query) => $query->where('connector_driver', 'unimed_rda'))
            ->where('status', 'approved')
            ->whereNotNull('numero_guia')
            ->where(function ($query) {
                $query->whereNull('senha')
                    ->orWhereNull('validade_senha');
            })
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get()
            ->each(function (Guia $guia) use ($capturarSenhaValidade) {
                if (! $this->configPara($guia->tenant_id)->automacao_captura_senha_validade_ativo) {
                    return;
                }

                $lock = Cache::lock("automacao:unimed:due:tenant:{$guia->tenant_id}:capture_authorization_data_batch", 60);

                if (! $lock->get()) {
                    return;
                }

                try {
                    $capturarSenhaValidade->enviar($guia);
                } catch (ValidationException) {
                    // Guia deixou de ser elegivel entre a busca e o enqueue.
                } finally {
                    $lock->release();
                }
            });

        // Itens 'uncertain' pos-submit de gerar_guia (UNCERTAIN_AFTER_SUBMIT,
        // sem numero_guia conhecido, sem Guia local) — confirma por busca de
        // paciente em "Exames em aberto". Intervalo minimo e janela de
        // horario sao configuraveis por tenant em /automacoes/configuracoes;
        // o tick deste job continua a cada 30 min, entao intervalos menores
        // que isso nao tem efeito pratico.
        SolicitacaoItem::query()
            ->with('solicitacao.convenio')
            ->where('status_operacional', 'uncertain')
            ->whereDoesntHave('guia')
            ->whereHas('solicitacao.convenio', fn ($query) => $query->where('connector_driver', 'unimed_rda'))
            ->where(function ($query) {
                $query->whereNull('unimed_verificacao_next_check_at')
                    ->orWhere('unimed_verificacao_next_check_at', '<=', now());
            })
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get()
            ->each(function (SolicitacaoItem $item) use ($confirmarGuiaIncerta) {
                $config = $this->configPara($item->tenant_id);

                if (! $config->automacao_verificacao_incerta_ativo) {
                    return;
                }

                if (! $this->dentroDaJanela($config)) {
                    return;
                }

                $lock = Cache::lock("automacao:unimed:due:tenant:{$item->tenant_id}:confirmar_guia_incerta", 60);

                if (! $lock->get()) {
                    return;
                }

                try {
                    $confirmarGuiaIncerta->enviar($item);
                } catch (ValidationException) {
                    // Item deixou de ser elegivel entre a busca e o enqueue
                    // (ex.: ja tem confirmacao em andamento de outra origem).
                } finally {
                    $item->forceFill([
                        'unimed_verificacao_next_check_at' => now()->addMinutes(
                            $config->unimed_verificacao_incerta_intervalo_minutos
                        ),
                    ])->save();
                    $lock->release();
                }
            });
    }

    private function configPara(int $tenantId): ConfiguracaoGlobal
    {
        return $this->configPorTenant[$tenantId] ??= ConfiguracaoGlobal::doTenant($tenantId);
    }

    private function dentroDaJanela(ConfiguracaoGlobal $config): bool
    {
        $inicio = $config->unimed_verificacao_incerta_horario_inicio;
        $fim = $config->unimed_verificacao_incerta_horario_fim;

        if (! $inicio || ! $fim) {
            return true;
        }

        $agora = now()->format('H:i:s');

        return $agora >= substr($inicio, 0, 8) && $agora <= substr($fim, 0, 8);
    }
}

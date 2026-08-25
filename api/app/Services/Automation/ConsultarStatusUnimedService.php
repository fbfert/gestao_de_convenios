<?php

namespace App\Services\Automation;

use App\Exceptions\AutomationConcurrencyException;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\Guia;
use App\Models\UnimedRdaCredential;
use App\Services\GuiaService;
use App\Services\SolicitacaoService;
use Illuminate\Validation\ValidationException;

class ConsultarStatusUnimedService
{
    private const ACTIVE_STATUSES = ['queued', 'running'];
    private const DEFAULT_DUE_HOURS = 24;
    public const OPERATION = 'consult_status_batch';

    public function __construct(
        private readonly AutomacaoService $automacoes,
        private readonly GuiaService $guiaService,
        private readonly SolicitacaoService $solicitacoes,
    ) {
    }

    public function enviar(Guia $guia, bool $dispatch = true): AutomacaoExecucao
    {
        $avaliacao = $this->avaliar($guia);

        if (! $avaliacao['eligible']) {
            throw ValidationException::withMessages(['guia' => $avaliacao['motivos']]);
        }

        try {
            $execucao = $this->automacoes->enfileirar(
                $guia->tenant_id,
                self::OPERATION,
                guia: $guia,
                payload: $this->payloadPersistido($guia),
            );
        } catch (AutomationConcurrencyException $exception) {
            throw ValidationException::withMessages([
                'guia' => ["Já existe execução Unimed ativa para este tenant ({$exception->execucaoId})."],
            ]);
        }

        $guia->forceFill(['unimed_next_check_at' => now()->addHours(self::DEFAULT_DUE_HOURS)])->save();

        if ($dispatch) {
            ExecutarAutomacaoUnimedJob::dispatch($execucao->id);
        }

        return $execucao;
    }

    public function avaliar(Guia $guia): array
    {
        $guia->loadMissing(['convenio', 'automacaoExecucao', 'solicitacaoItem']);
        $motivos = [];
        $credential = UnimedRdaCredential::query()
            ->where('tenant_id', $guia->tenant_id)
            ->where('ativo', true)
            ->first();

        if ($guia->convenio?->connector_driver !== 'unimed_rda') {
            $motivos[] = 'A Guia não pertence a Convênio Unimed RDA.';
        }

        if (blank($guia->numero_guia)) {
            $motivos[] = 'A Guia precisa ter número da operadora para consulta de status.';
        }

        if (in_array($guia->status, ['approved', 'denied', 'canceled', 'finalized', 'needs_verification'], true)) {
            $motivos[] = 'A Guia não possui status elegível para consulta.';
        }

        if (! $credential || blank($credential->password)) {
            $motivos[] = 'A credencial Unimed ativa não está configurada.';
        }

        $active = AutomacaoExecucao::query()
            ->where('tenant_id', $guia->tenant_id)
            ->where('guia_id', $guia->id)
            ->whereIn('operacao', [self::OPERATION, 'consultar_status'])
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();

        if ($active) {
            $motivos[] = 'Já existe consulta de status em andamento para esta Guia.';
        }

        return [
            'eligible' => $motivos === [],
            'motivos' => $motivos,
        ];
    }

    public function payloadParaWorker(AutomacaoExecucao $execucao): array
    {
        $execucao->loadMissing('guia');
        $credential = UnimedRdaCredential::query()
            ->where('tenant_id', $execucao->tenant_id)
            ->where('ativo', true)
            ->firstOrFail();

        return ($execucao->payload ?? []) + [
            'credential' => [
                'login' => $credential->login,
                'password' => $credential->password,
                'base_url' => $credential->base_url,
            ],
        ];
    }

    public function aplicarResultado(AutomacaoExecucao $execucao, array $resultado): AutomacaoExecucao
    {
        $execucao = $this->automacoes->concluir($execucao, $resultado);
        $guia = $execucao->guia()->with('convenio')->firstOrFail();
        $portalStatus = $resultado['unimed_status']
            ?? $resultado['status_operadora']
            ?? $resultado['portal_status']
            ?? null;
        $guiaStatus = $resultado['guia_status'] ?? $resultado['status_guia'] ?? $resultado['portal_status'] ?? null;
        $conclusivo = (bool) ($resultado['conclusivo'] ?? ($resultado['status'] ?? null) === 'succeeded');

        if ($conclusivo && filled($guiaStatus)) {
            $guia->forceFill([
                'status' => $guiaStatus,
                'unimed_status' => $portalStatus,
                'unimed_last_checked_at' => now(),
                'unimed_next_check_at' => now()->addHours(self::DEFAULT_DUE_HOURS),
                // A operadora pode revisar a quantidade autorizada depois da geração;
                // só sobrescrevemos quando o portal realmente informou o número.
                ...$this->quantidadesInformadas($resultado),
            ])->save();

            if ($guia->solicitacao_id) {
                $this->solicitacoes->sincronizarStatusComGuias($guia->solicitacao);
            }
        } else {
            $this->automacoes->registrarEvento($execucao, 'dados_indisponiveis', $execucao->status, [
                'mensagem' => $resultado['message'] ?? 'Consulta de status sem resultado conclusivo.',
                'proxima_consulta' => $guia->unimed_next_check_at?->toISOString(),
            ]);
        }

        return $execucao->refresh();
    }

    /** @return array<string, int> */
    private function quantidadesInformadas(array $resultado): array
    {
        $quantidades = [];

        foreach (['sessoes_solicitadas', 'sessoes_autorizadas'] as $campo) {
            if (filled($resultado[$campo] ?? null)) {
                $quantidades[$campo] = (int) $resultado[$campo];
            }
        }

        return $quantidades;
    }

    private function payloadPersistido(Guia $guia): array
    {
        $guia->loadMissing(['paciente', 'convenio', 'solicitacaoItem']);

        return [
            'guia_id' => $guia->id,
            'numero_guia' => $guia->numero_guia,
            'paciente' => [
                'id' => $guia->paciente_id,
                'nome' => $guia->paciente?->nome,
                'carteirinha' => $guia->paciente?->carteirinha,
            ],
            'convenio_id' => $guia->convenio_id,
            'solicitacao_item_id' => $guia->solicitacao_item_id,
        ];
    }
}

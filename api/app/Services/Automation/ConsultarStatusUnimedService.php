<?php

namespace App\Services\Automation;

use App\Exceptions\AutomationConcurrencyException;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\Guia;
use App\Models\UnimedRdaCredential;
use App\Services\GuiaService;
use Illuminate\Validation\ValidationException;

class ConsultarStatusUnimedService
{
    private const ACTIVE_STATUSES = ['queued', 'running'];
    private const DEFAULT_DUE_HOURS = 24;

    public function __construct(
        private readonly AutomacaoService $automacoes,
        private readonly GuiaService $guiaService,
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
                'consultar_status',
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

        if (! $credential || blank($credential->password)) {
            $motivos[] = 'A credencial Unimed ativa não está configurada.';
        }

        $active = AutomacaoExecucao::query()
            ->where('tenant_id', $guia->tenant_id)
            ->where('guia_id', $guia->id)
            ->where('operacao', 'consultar_status')
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
        $portalStatus = $resultado['portal_status'] ?? $resultado['status'] ?? null;
        $senha = $resultado['senha'] ?? null;
        $validadeSenha = $resultado['validade_senha'] ?? null;

        $guia->forceFill([
            'unimed_status' => $portalStatus,
            'unimed_last_checked_at' => now(),
            'unimed_next_check_at' => now()->addHours(self::DEFAULT_DUE_HOURS),
        ])->save();

        if (filled($senha) && filled($validadeSenha) && $guia->status === 'under_review') {
            $this->guiaService->finalizar($guia, [
                'senha' => $senha,
                'validade_senha' => $validadeSenha,
                'data_finalizacao' => today()->toDateString(),
            ]);
        } elseif (! filled($senha) || ! filled($validadeSenha)) {
            $this->automacoes->registrarEvento($execucao, 'dados_indisponiveis', $execucao->status, [
                'mensagem' => 'Senha ou validade ainda indisponível no portal.',
                'proxima_consulta' => $guia->unimed_next_check_at?->toISOString(),
            ]);
        }

        return $execucao->refresh();
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

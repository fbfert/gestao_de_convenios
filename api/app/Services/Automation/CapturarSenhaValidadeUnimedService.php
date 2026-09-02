<?php

namespace App\Services\Automation;

use App\Exceptions\AutomationConcurrencyException;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\Guia;
use App\Models\UnimedRdaCredential;
use Illuminate\Validation\ValidationException;

class CapturarSenhaValidadeUnimedService
{
    private const ACTIVE_STATUSES = ['queued', 'running'];
    public const OPERATION = 'capture_authorization_data_batch';

    public function __construct(private readonly AutomacaoService $automacoes)
    {
    }

    public function enviar(Guia $guia, bool $dispatch = true): AutomacaoExecucao
    {
        $avaliacao = $this->avaliar($guia);

        if (! $avaliacao['eligible']) {
            throw ValidationException::withMessages(['guia' => $avaliacao['motivos']]);
        }

        try {
            $parent = AutomacaoExecucao::query()
                ->where('tenant_id', $guia->tenant_id)
                ->where('guia_id', $guia->id)
                ->where('operacao', self::OPERATION)
                ->whereNotIn('status', self::ACTIVE_STATUSES)
                ->latest('id')
                ->first();

            $execucao = $this->automacoes->enfileirar(
                $guia->tenant_id,
                self::OPERATION,
                guia: $guia,
                payload: $this->payloadPersistido($guia),
                parent: $parent,
            );
        } catch (AutomationConcurrencyException $exception) {
            throw ValidationException::withMessages([
                'guia' => ["Já existe execução Unimed ativa para este tenant ({$exception->execucaoId})."],
            ]);
        }

        if ($dispatch) {
            ExecutarAutomacaoUnimedJob::dispatch($execucao->id);
        }

        return $execucao;
    }

    public function avaliar(Guia $guia): array
    {
        $guia->loadMissing(['convenio']);
        $motivos = [];
        $credential = UnimedRdaCredential::query()
            ->where('tenant_id', $guia->tenant_id)
            ->where('ativo', true)
            ->first();

        if ($guia->convenio?->connector_driver !== 'unimed_rda') {
            $motivos[] = 'A Guia não pertence a Convênio Unimed RDA.';
        }

        if ($guia->temDadosADefinir()) {
            $motivos[] = 'A Guia precisa ter Especialidade e Profissional definidos para entrar na automação.';
        }

        if (! $credential || blank($credential->password)) {
            $motivos[] = 'A credencial Unimed ativa não está configurada.';
        }

        if ($guia->status !== 'approved') {
            $motivos[] = 'A Guia precisa estar aprovada para buscar senha e validade.';
        }

        if (blank($guia->numero_guia)) {
            $motivos[] = 'A Guia precisa ter número da operadora.';
        }

        if (filled($guia->senha) && filled($guia->validade_senha)) {
            $motivos[] = 'A Guia já possui senha e validade.';
        }

        $active = AutomacaoExecucao::query()
            ->where('tenant_id', $guia->tenant_id)
            ->where('guia_id', $guia->id)
            ->where('operacao', self::OPERATION)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();

        if ($active) {
            $motivos[] = 'Já existe busca de senha/validade em andamento para esta Guia.';
        }

        return [
            'eligible' => $motivos === [],
            'motivos' => $motivos,
        ];
    }

    public function payloadParaWorker(AutomacaoExecucao $execucao): array
    {
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
        $guia = $execucao->guia()->firstOrFail();

        if (($resultado['status'] ?? null) !== 'succeeded') {
            $this->automacoes->registrarEvento($execucao, 'autorizacao_indisponivel', $execucao->status, [
                'codigo' => $resultado['error_code'] ?? null,
                'mensagem' => $resultado['message'] ?? 'Senha ou validade não encontrada no portal.',
            ]);

            return $execucao->refresh();
        }

        $updates = [];
        if (filled($resultado['senha'] ?? null)) {
            $updates['senha'] = $resultado['senha'];
        }
        if (filled($resultado['validade_senha'] ?? null)) {
            $updates['validade_senha'] = $resultado['validade_senha'];
        }

        if ($updates !== []) {
            $guia->forceFill($updates)->save();
        }

        return $execucao->refresh();
    }

    private function payloadPersistido(Guia $guia): array
    {
        $guia->loadMissing(['paciente', 'convenio']);

        return [
            'guia_id' => $guia->id,
            'numero_guia' => $guia->numero_guia,
            'paciente' => [
                'id' => $guia->paciente_id,
                'nome' => $guia->paciente?->nome,
                'carteirinha' => $guia->paciente?->carteirinha,
            ],
            'convenio_id' => $guia->convenio_id,
        ];
    }
}

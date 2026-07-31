<?php

namespace App\Services\Automation;

use App\Exceptions\AutomationConcurrencyException;
use App\Models\AutomacaoEvento;
use App\Models\AutomacaoExecucao;
use App\Models\Guia;
use App\Models\SolicitacaoItem;
use Illuminate\Support\Facades\DB;

class AutomacaoService
{
    private const ACTIVE_STATUSES = ['queued', 'running'];

    public function __construct(private readonly AutomationPayloadRedactor $redactor)
    {
    }

    public function enfileirar(
        int $tenantId,
        string $operacao,
        ?SolicitacaoItem $item = null,
        ?Guia $guia = null,
        array $payload = [],
        ?AutomacaoExecucao $parent = null,
    ): AutomacaoExecucao {
        return DB::transaction(function () use ($tenantId, $operacao, $item, $guia, $payload, $parent) {
            $idempotencyKey = $this->idempotencyKey($tenantId, $operacao, $item?->id, $guia?->id, $parent?->id);
            $existing = AutomacaoExecucao::query()
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $active = AutomacaoExecucao::query()
                ->where('tenant_id', $tenantId)
                ->where('operacao', $operacao)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->first();

            if ($active) {
                throw new AutomationConcurrencyException($active->id);
            }

            $execucao = AutomacaoExecucao::query()->create([
                'tenant_id' => $tenantId,
                'solicitacao_item_id' => $item?->id,
                'guia_id' => $guia?->id,
                'operacao' => $operacao,
                'status' => 'queued',
                'idempotency_key' => $idempotencyKey,
                'payload' => $this->redactor->redact($payload),
                'queued_at' => now(),
                'parent_id' => $parent?->id,
            ]);

            $this->registrarEvento($execucao, 'queued', 'queued', $payload);

            return $execucao;
        });
    }

    public function iniciar(AutomacaoExecucao $execucao, array $payload = []): AutomacaoExecucao
    {
        $execucao->fill([
            'status' => 'running',
            'started_at' => now(),
        ])->save();

        $this->registrarEvento($execucao, 'started', 'running', $payload);

        return $execucao->refresh();
    }

    public function concluir(AutomacaoExecucao $execucao, array $resultado = []): AutomacaoExecucao
    {
        $status = $resultado['status'] ?? 'succeeded';

        $execucao->fill([
            'status' => $status,
            'resultado' => $this->redactor->redact($resultado),
            'finished_at' => now(),
        ])->save();

        $this->registrarEvento($execucao, 'finished', $status, $resultado, $resultado['evidencias'] ?? []);

        return $execucao->refresh();
    }

    public function falhar(
        AutomacaoExecucao $execucao,
        string $codigo,
        string $mensagem,
        array $payload = [],
    ): AutomacaoExecucao {
        $execucao->fill([
            'status' => 'failed',
            'erro_codigo' => $codigo,
            'erro_mensagem' => $mensagem,
            'resultado' => $this->redactor->redact($payload),
            'finished_at' => now(),
        ])->save();

        $this->registrarEvento($execucao, 'failed', 'failed', [
            'codigo' => $codigo,
            'mensagem' => $mensagem,
            'payload' => $payload,
        ], $payload['evidencias'] ?? []);

        return $execucao->refresh();
    }

    public function registrarEvento(
        AutomacaoExecucao $execucao,
        string $tipo,
        ?string $status = null,
        array $payload = [],
        array $evidencias = [],
    ): AutomacaoEvento {
        return AutomacaoEvento::query()->create([
            'tenant_id' => $execucao->tenant_id,
            'automacao_execucao_id' => $execucao->id,
            'tipo' => $tipo,
            'status' => $status,
            'payload' => $this->redactor->redact($payload),
            'evidencias' => $this->redactor->redact($evidencias),
            'registrado_em' => now(),
        ]);
    }

    private function idempotencyKey(
        int $tenantId,
        string $operacao,
        ?int $itemId,
        ?int $guiaId,
        ?int $parentId = null,
    ): string
    {
        return hash('sha256', implode('|', [
            $tenantId,
            $operacao,
            'item:'.($itemId ?? 'none'),
            'guia:'.($guiaId ?? 'none'),
            'parent:'.($parentId ?? 'none'),
        ]));
    }
}

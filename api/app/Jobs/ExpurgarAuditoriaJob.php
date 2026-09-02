<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\ConfiguracaoGlobal;
use App\Models\Tenant;
use App\Support\Auditoria;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Expurgo diário da trilha de auditoria.
 *
 * Exporta em CSV o lote vencido e só então apaga. Se a exportação falhar, nada
 * é removido: o histórico só sai de cena depois de existir em outro lugar.
 *
 * Roda por tenant porque o prazo é configurável por clínica.
 */
class ExpurgarAuditoriaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Tenant::query()->orderBy('id')->each(function (Tenant $tenant) {
            // Lê sem criar: `doTenant()` criaria a linha de configuração de
            // toda clínica que nunca abriu a tela, e cada criação viraria mais
            // um evento na trilha que este job existe para enxugar.
            $config = ConfiguracaoGlobal::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($config && ! $config->automacao_expurgo_auditoria_ativo) {
                return;
            }

            $meses = $config?->auditoria_retencao_meses ?: 12;
            $corte = now()->subMonths($meses)->startOfDay();

            // `withoutGlobalScopes` porque o job roda fora de requisição: não
            // há TenantContext, e o escopo devolveria nada.
            $vencidos = AuditLog::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('created_at', '<', $corte);

            $quantidade = (clone $vencidos)->count();

            if ($quantidade === 0) {
                return;
            }

            $arquivo = "auditoria/expurgo-{$tenant->id}-".now()->format('Y-m-d-His').'.csv';

            try {
                $this->exportar(clone $vencidos, $arquivo);
            } catch (Throwable $e) {
                // Sem exportação não há expurgo. Melhor a tabela crescer um dia
                // a mais do que perder o histórico sem cópia.
                report($e);

                return;
            }

            (clone $vencidos)->delete();

            Auditoria::registrar(
                acao: 'auditoria.expurgada',
                entidade: 'audit_logs',
                entidadeId: 0,
                payload: [
                    'ate' => $corte->toDateString(),
                    'retencao_meses' => $meses,
                    'registros' => $quantidade,
                    'arquivo' => $arquivo,
                ],
                tenantId: $tenant->id,
                doSistema: true,
            );
        });
    }

    private function exportar($consulta, string $arquivo): void
    {
        $linhas = [];
        $linhas[] = $this->linhaCsv(['id', 'created_at', 'user_id', 'acao', 'entidade', 'entidade_id', 'payload', 'ip', 'user_agent']);

        $consulta->orderBy('id')->chunk(500, function ($registros) use (&$linhas) {
            foreach ($registros as $registro) {
                $linhas[] = $this->linhaCsv([
                    $registro->id,
                    $registro->created_at?->toDateTimeString(),
                    $registro->user_id,
                    $registro->acao,
                    $registro->entidade,
                    $registro->entidade_id,
                    json_encode($registro->payload, JSON_UNESCAPED_UNICODE),
                    $registro->ip,
                    $registro->user_agent,
                ]);
            }
        });

        Storage::disk('local')->put($arquivo, implode('', $linhas));

        if (! Storage::disk('local')->exists($arquivo)) {
            throw new \RuntimeException("Falha ao gravar {$arquivo}.");
        }
    }

    private function linhaCsv(array $campos): string
    {
        $buffer = fopen('php://temp', 'r+');
        fputcsv($buffer, $campos);
        rewind($buffer);
        $linha = stream_get_contents($buffer);
        fclose($buffer);

        return $linha;
    }
}

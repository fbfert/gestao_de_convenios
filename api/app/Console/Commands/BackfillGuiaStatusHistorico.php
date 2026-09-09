<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;

/**
 * Reconstroi o historico das guias que ja existiam, a partir da trilha.
 *
 * Best-effort de proposito, e por isso as linhas saem com `origem = migracao`:
 * o ExpurgarAuditoriaJob apaga audit_logs por `auditoria_retencao_meses`, entao
 * o que ja foi expurgado nao volta. A linha fica marcada como RECONSTRUIDA, e
 * nao como observada — quem ler a serie depois precisa saber a diferenca.
 *
 * Nao falha quando a trilha e insuficiente: relata quantas guias ficaram sem
 * historico e conclui.
 */
class BackfillGuiaStatusHistorico extends Command
{
    protected $signature = 'guias:backfill-status-historico {--dry-run : Mostra o que faria sem gravar}';

    protected $description = 'Reconstroi guia_status_historico a partir de audit_logs (best-effort, origem=migracao).';

    public function handle(): int
    {
        $simulacao = (bool) $this->option('dry-run');
        $criadas = 0;
        $semHistorico = 0;
        $guiasVistas = 0;

        Guia::query()
            ->withoutGlobalScope(TenantScope::class)
            ->orderBy('id')
            ->chunkById(200, function ($guias) use (&$criadas, &$semHistorico, &$guiasVistas, $simulacao) {
                foreach ($guias as $guia) {
                    $guiasVistas++;

                    $jaTem = GuiaStatusHistorico::query()
                        ->withoutGlobalScope(TenantScope::class)
                        ->where('guia_id', $guia->id)
                        ->exists();

                    if ($jaTem) {
                        continue;
                    }

                    $linhas = $this->transicoesDaTrilha($guia);

                    if ($linhas === []) {
                        // Sem trilha utilizavel: pelo menos o estado atual vira
                        // uma linha, senao a guia fica invisivel para qualquer
                        // leitura que use o historico.
                        $linhas[] = [
                            'de' => null,
                            'para' => $guia->status,
                            'ocorrido_em' => $guia->updated_at ?? $guia->created_at ?? now(),
                        ];
                        $semHistorico++;
                    }

                    foreach ($linhas as $linha) {
                        if ($simulacao) {
                            $criadas++;

                            continue;
                        }

                        GuiaStatusHistorico::query()->create([
                            'tenant_id' => $guia->tenant_id,
                            'guia_id' => $guia->id,
                            'de' => $linha['de'],
                            'para' => $linha['para'],
                            'ocorrido_em' => $linha['ocorrido_em'],
                            'user_id' => null,
                            'origem' => GuiaStatusHistorico::ORIGEM_MIGRACAO,
                            'motivo' => null,
                        ]);
                        $criadas++;
                    }
                }
            });

        $this->line(json_encode([
            'dry_run' => $simulacao,
            'guias_lidas' => $guiasVistas,
            'linhas_criadas' => $criadas,
            'guias_sem_trilha_utilizavel' => $semHistorico,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($semHistorico > 0) {
            $this->warn(
                "{$semHistorico} guia(s) nao tinham trilha suficiente e receberam apenas o estado atual. "
                .'Isso e esperado: a trilha e expurgada por retencao.'
            );
        }

        return self::SUCCESS;
    }

    /**
     * Extrai as transicoes de status do diff que a trilha guardou.
     *
     * @return array<int, array{de: string|null, para: string, ocorrido_em: mixed}>
     */
    private function transicoesDaTrilha(Guia $guia): array
    {
        $eventos = AuditLog::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('entidade', 'guias')
            ->where('entidade_id', $guia->id)
            ->orderBy('id')
            ->get();

        $linhas = [];

        foreach ($eventos as $evento) {
            // A trilha guarda um `payload` JSON com as chaves `antes` e
            // `depois` (ver Auditoria::registrarModelo), e não colunas próprias.
            $payload = $evento->payload ?? [];
            $antes = $this->campoStatus($payload['antes'] ?? null);
            $depois = $this->campoStatus($payload['depois'] ?? null);

            if ($depois === null) {
                continue;
            }

            $linhas[] = [
                'de' => $antes,
                'para' => $depois,
                'ocorrido_em' => $evento->created_at,
            ];
        }

        return $linhas;
    }

    private function campoStatus(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $valor = $payload['status'] ?? null;

        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}

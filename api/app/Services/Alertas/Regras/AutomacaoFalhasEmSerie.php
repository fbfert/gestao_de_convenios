<?php

namespace App\Services\Alertas\Regras;

use App\Models\Alerta;
use App\Models\AlertaRegra;
use App\Models\AutomacaoExecucao;
use App\Scopes\TenantScope;
use App\Services\Alertas\AvaliadorDeAlerta;

/**
 * N falhas consecutivas da mesma operacao da automacao dentro de uma janela.
 *
 * "Consecutivas" e o ponto: uma falha isolada no meio de dez sucessos e ruido do
 * portal; tres seguidas sao um padrao. Por isso a contagem para no primeiro
 * sucesso, em vez de somar falhas soltas da janela.
 */
class AutomacaoFalhasEmSerie implements AvaliadorDeAlerta
{
    /** Status que contam como falha. */
    private const FALHAS = ['failed', 'needs_attention'];

    private const PADRAO_LIMIAR = 3;

    private const PADRAO_JANELA_HORAS = 6;

    public function avaliar(int $tenantId, AlertaRegra $regra): array
    {
        $limiar = $regra->limiar_vermelho ?? $regra->limiar_amarelo ?? self::PADRAO_LIMIAR;
        $janela = now()->subHours(self::PADRAO_JANELA_HORAS);

        $execucoes = AutomacaoExecucao::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $janela)
            ->orderByDesc('id')
            ->get(['id', 'operacao', 'status', 'erro_mensagem', 'created_at']);

        $alertas = [];

        foreach ($execucoes->groupBy('operacao') as $operacao => $doGrupo) {
            $consecutivas = 0;

            // Ja vem em ordem decrescente: contamos do mais recente para tras e
            // paramos no primeiro sucesso.
            foreach ($doGrupo as $execucao) {
                if (in_array($execucao->status, self::FALHAS, true)) {
                    $consecutivas++;

                    continue;
                }

                if (in_array($execucao->status, ['queued', 'running'], true)) {
                    continue; // ainda nao decidiu nada, nao quebra a serie
                }

                break;
            }

            if ($consecutivas < $limiar) {
                continue;
            }

            $ultima = $doGrupo->first();

            $alertas[] = [
                'nivel' => $regra->nivel_base ?: Alerta::NIVEL_VERMELHO,
                'titulo' => "Automação falhando em série — {$operacao}",
                'descricao' => "{$consecutivas} falhas consecutivas nas últimas "
                    .self::PADRAO_JANELA_HORAS.' horas'
                    .($ultima?->erro_mensagem ? ' · '.$ultima->erro_mensagem : ''),
                // A chave de deduplicação é a OPERAÇÃO, não a execução: usar o
                // id da última execução faria cada falha nova abrir um alerta
                // novo, que é exatamente o que a deduplicação existe para
                // impedir. A execução vai em `dados`, para a tela linkar.
                'entidade' => "automacao_operacao:{$operacao}",
                'entidade_id' => null,
                'dados' => [
                    'operacao' => $operacao,
                    'falhas_consecutivas' => $consecutivas,
                    'limiar' => $limiar,
                    'ultima_execucao_id' => $ultima?->id,
                ],
            ];
        }

        return $alertas;
    }
}

<?php

namespace App\Services\Alertas\Regras;

use App\Models\Alerta;
use App\Models\AlertaRegra;
use App\Models\ConfiguracaoGlobal;
use App\Models\Guia;
use App\Scopes\TenantScope;
use App\Services\Alertas\AvaliadorDeAlerta;

/**
 * Guias com validade de senha perto de vencer.
 *
 * A janela vem de `configuracoes_globais.senha_alerta_dias` — a configuracao ja
 * existia e passou anos sem consumidor. O limiar de urgencia (o que vira
 * vermelho) vem da propria regra, em `limiar_vermelho`.
 */
class SenhaVencendo implements AvaliadorDeAlerta
{
    /** Dias para o vencimento abaixo dos quais o alerta vira vermelho. */
    private const PADRAO_VERMELHO = 2;

    public function avaliar(int $tenantId, AlertaRegra $regra): array
    {
        $dias = (int) ConfiguracaoGlobal::doTenant($tenantId)->senha_alerta_dias;
        $diasVermelho = $regra->limiar_vermelho ?? self::PADRAO_VERMELHO;

        $hoje = today();
        $limite = $hoje->copy()->addDays($dias);

        $guias = Guia::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->semStatusHistorico()
            ->whereNotNull('validade_senha')
            ->whereDate('validade_senha', '>=', $hoje)
            ->whereDate('validade_senha', '<=', $limite)
            ->with('paciente')
            ->get();

        return $guias->map(function (Guia $guia) use ($hoje, $diasVermelho, $regra) {
            $faltam = $hoje->diffInDays($guia->validade_senha, false);
            $vermelho = $faltam <= $diasVermelho;

            return [
                'nivel' => $vermelho ? Alerta::NIVEL_VERMELHO : ($regra->nivel_base ?: Alerta::NIVEL_AMARELO),
                'titulo' => 'Senha vencendo — guia '.($guia->numero_guia ?: "#{$guia->id}"),
                'descricao' => ($guia->paciente?->nome ?? 'Paciente não informado')
                    .' · vence em '.$guia->validade_senha->format('d/m/Y'),
                'entidade' => 'guias',
                'entidade_id' => (int) $guia->id,
                'dados' => [
                    'validade_senha' => $guia->validade_senha->toDateString(),
                    'dias_restantes' => (int) $faltam,
                ],
            ];
        })->all();
    }
}

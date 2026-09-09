<?php

namespace App\Services\Alertas\Regras;

use App\Models\Alerta;
use App\Models\AlertaRegra;
use App\Models\SaudeComponente;
use App\Scopes\TenantScope;
use App\Services\Alertas\AvaliadorDeAlerta;
use App\Services\SaudeService;

/**
 * Componente de saude sem heartbeat alem do limite.
 *
 * Reaproveita a derivacao de estado do SaudeService em vez de repetir a conta:
 * duas implementacoes da mesma regra divergem no primeiro ajuste, e ai o card de
 * saude e a central de alertas passam a discordar sobre o que esta fora.
 */
class ComponenteFora implements AvaliadorDeAlerta
{
    public function __construct(private readonly SaudeService $saude)
    {
    }

    public function avaliar(int $tenantId, AlertaRegra $regra): array
    {
        $componentes = SaudeComponente::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->get();

        return $componentes
            ->filter(fn (SaudeComponente $c) => $this->saude->estadoDe($c) === SaudeComponente::ESTADO_FORA)
            ->map(fn (SaudeComponente $c) => [
                'nivel' => $regra->nivel_base ?: Alerta::NIVEL_VERMELHO,
                'titulo' => "Componente fora — {$c->nome}",
                'descricao' => $c->ultimo_heartbeat_em
                    ? 'Sem resposta desde '.$c->ultimo_heartbeat_em->format('d/m/Y H:i')
                    : 'Nunca respondeu',
                'entidade' => 'saude_componentes',
                'entidade_id' => (int) $c->id,
                'dados' => [
                    'chave' => $c->chave,
                    'tipo' => $c->tipo,
                    'ultimo_heartbeat_em' => $c->ultimo_heartbeat_em?->toIso8601String(),
                ],
            ])
            ->values()
            ->all();
    }
}

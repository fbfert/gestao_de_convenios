<?php

namespace App\Services;

use App\Models\ConfiguracaoGlobal;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Support\GuiaStatus;

/**
 * O card de Guias do dashboard, em DUAS consultas.
 *
 * O `GET /dashboard` ja dispara treze count() e a tela faz polling de 30s. Um
 * count() por numero somaria mais seis e faria deste painel a consulta mais cara
 * do sistema — por isso agregacao condicional, e nao uma consulta por celula.
 *
 * As fronteiras de dia e de semana sao calculadas em PHP e entram como
 * parametro: funcao de data em SQL varia por fabricante, e este codigo roda em
 * SQLite (testes) e MariaDB (producao).
 */
class DashboardGuiasCardService
{
    /** Prazo em que "vence logo" vira urgencia, dentro da janela configurada. */
    private const DIAS_URGENTE = 2;

    public function montar(int $tenantId): array
    {
        $hoje = today();
        $inicioDaSemana = $hoje->copy()->subDays(6)->startOfDay();
        $diasAlerta = (int) ConfiguracaoGlobal::doTenant($tenantId)->senha_alerta_dias;

        $totais = $this->totais($hoje, $diasAlerta);
        $porTransicao = $this->porTransicao($tenantId, $hoje, $inicioDaSemana);

        $negadas = $porTransicao[GuiaStatus::DENIED] ?? ['hoje' => 0, 'semana' => 0];
        $restricao = $porTransicao[GuiaStatus::NEEDS_VERIFICATION] ?? ['hoje' => 0, 'semana' => 0];
        $emAnalise = $porTransicao[GuiaStatus::UNDER_REVIEW] ?? ['hoje' => 0, 'semana' => 0];

        return [
            [
                'key' => 'negadas',
                'label' => 'Negadas',
                'value' => (int) $totais->negadas_pendentes,
                'unidade' => 'pendentes',
                'detail' => "{$negadas['hoje']} hoje · {$negadas['semana']} na semana",
                'href' => '/guias?status=denied&pendente=1',
            ],
            [
                // Mesmas regras das negadas: status + alerta nao ocultado +
                // fora do rastro historico, e o detalhe pela DATA DA TRANSICAO.
                'key' => 'restricao',
                'label' => 'Verificar Restrição',
                'value' => (int) $totais->restricao_pendentes,
                'unidade' => 'pendentes',
                'detail' => "{$restricao['hoje']} hoje · {$restricao['semana']} na semana",
                'href' => '/guias?status=needs_verification&pendente=1',
            ],
            [
                'key' => 'em_analise',
                'label' => 'Em análise',
                'value' => (int) $totais->em_analise,
                'unidade' => null,
                'detail' => "{$emAnalise['hoje']} hoje · {$emAnalise['semana']} na semana",
                'href' => '/guias?status=under_review',
            ],
            [
                'key' => 'senha_vencendo',
                'label' => 'Senha vencendo',
                'value' => (int) $totais->senha_vencendo,
                'unidade' => null,
                'detail' => $this->detalheSenha((int) $totais->senha_urgente, $diasAlerta),
                'href' => '/guias?senha_vencendo=1',
            ],
            [
                'key' => 'prontas_antecipacao',
                'label' => 'Prontas para Antecipação',
                'value' => Guia::countElegiveisParaAntecipacao($tenantId),
                'unidade' => null,
                'detail' => 'antecipação devida hoje ou atrasada',
                'href' => '/antecipacoes',
            ],
        ];
    }

    /**
     * Consulta 1: agregacao condicional sobre `guias`.
     *
     * `SUM(CASE WHEN ...)` em vez de varios count(): uma varredura so responde
     * as quatro perguntas. A forma e ANSI, entao vale nos dois bancos.
     */
    private function totais($hoje, int $diasAlerta): object
    {
        $limite = $hoje->copy()->addDays($diasAlerta)->toDateString();
        $urgente = $hoje->copy()->addDays(self::DIAS_URGENTE)->toDateString();

        return Guia::query()
            // Guia historica e passado resolvido: inflaria o card com trabalho
            // que nao existe. Mesmo criterio do GuiaAlertaNegacoes.
            ->semStatusHistorico()
            ->naoHistorica()
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND alerta_negacao_ocultado_em IS NULL THEN 1 ELSE 0 END) as negadas_pendentes,'
                .' SUM(CASE WHEN status = ? AND alerta_restricao_ocultado_em IS NULL THEN 1 ELSE 0 END) as restricao_pendentes,'
                .' SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as em_analise,'
                .' SUM(CASE WHEN validade_senha IS NOT NULL AND validade_senha >= ? AND validade_senha <= ? THEN 1 ELSE 0 END) as senha_vencendo,'
                .' SUM(CASE WHEN validade_senha IS NOT NULL AND validade_senha >= ? AND validade_senha <= ? THEN 1 ELSE 0 END) as senha_urgente',
                [
                    GuiaStatus::DENIED,
                    GuiaStatus::NEEDS_VERIFICATION,
                    GuiaStatus::UNDER_REVIEW,
                    $hoje->toDateString(), $limite,
                    $hoje->toDateString(), $urgente,
                ],
            )
            ->first();
    }

    /**
     * Consulta 2: "hoje" e "na semana" pela DATA DA TRANSICAO.
     *
     * `created_at` da guia nao serve: uma guia criada semana passada e negada
     * hoje conta em "hoje". `COUNT(DISTINCT guia_id)` porque uma guia negada
     * duas vezes no mesmo dia e uma guia negada hoje, nao duas.
     *
     * @return array<string, array{hoje: int, semana: int}>
     */
    private function porTransicao(int $tenantId, $hoje, $inicioDaSemana): array
    {
        $inicioDoDia = $hoje->copy()->startOfDay();

        return GuiaStatusHistorico::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('para', [GuiaStatus::DENIED, GuiaStatus::NEEDS_VERIFICATION, GuiaStatus::UNDER_REVIEW])
            ->where('ocorrido_em', '>=', $inicioDaSemana)
            ->groupBy('para')
            ->select('para')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ocorrido_em >= ? THEN guia_id END) as hoje', [$inicioDoDia])
            ->selectRaw('COUNT(DISTINCT guia_id) as semana')
            ->get()
            ->mapWithKeys(fn ($linha) => [
                $linha->para => ['hoje' => (int) $linha->hoje, 'semana' => (int) $linha->semana],
            ])
            ->all();
    }

    private function detalheSenha(int $urgentes, int $diasAlerta): string
    {
        if ($urgentes > 0) {
            $plural = $urgentes === 1 ? 'vence 1' : "vencem {$urgentes}";

            return $plural.' em 48h';
        }

        return "janela de {$diasAlerta} dias";
    }
}

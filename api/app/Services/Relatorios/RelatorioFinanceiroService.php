<?php

namespace App\Services\Relatorios;

/**
 * Aba de Financeiro.
 *
 * Esqueleto: a rota, a permissão, o recorte de clínica e o cache já valem, e o
 * contrato já é o definitivo — o miolo entra no bloco 2 do tasks.md. Vazio aqui
 * significa "ainda não calculado", e não "não houve nada no período"; a tela só
 * consome esta aba a partir do bloco 4.
 */
class RelatorioFinanceiroService extends RelatorioService
{
    public function aba(): string
    {
        return RelatorioAba::FINANCEIRO;
    }

    protected function calcular(RelatorioFiltros $filtros): array
    {
        return ['kpis' => [], 'series' => [], 'tabelas' => [], 'filtros_aplicados' => []];
    }
}

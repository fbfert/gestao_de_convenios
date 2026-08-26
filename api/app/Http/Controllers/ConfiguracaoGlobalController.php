<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConfiguracaoGlobalRequest;
use App\Models\ConfiguracaoGlobal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracaoGlobalController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->paraArray(
                ConfiguracaoGlobal::doTenant((int) $request->user()->tenant_id)
            ),
        ]);
    }

    public function update(UpdateConfiguracaoGlobalRequest $request): JsonResponse
    {
        $configuracao = ConfiguracaoGlobal::doTenant((int) $request->user()->tenant_id);
        $configuracao->fill($request->validated());
        $configuracao->save();

        return response()->json(['data' => $this->paraArray($configuracao)]);
    }

    private function paraArray(ConfiguracaoGlobal $configuracao): array
    {
        return [
            'sessao_minutos' => $configuracao->sessao_minutos,
            'senha_alerta_dias' => $configuracao->senha_alerta_dias,
            'sessoes_padrao' => $configuracao->sessoes_padrao,
            'itens_por_pagina' => $configuracao->itens_por_pagina,
            'auditoria_retencao_meses' => $configuracao->auditoria_retencao_meses,
            'carteirinha_retencao_dias' => $configuracao->carteirinha_retencao_dias,
            'unimed_recheck_horas_sucesso' => $configuracao->unimed_recheck_horas_sucesso,
            'unimed_recheck_horas_falha' => $configuracao->unimed_recheck_horas_falha,
            'unimed_verificacao_incerta_intervalo_minutos' => $configuracao->unimed_verificacao_incerta_intervalo_minutos,
            'unimed_verificacao_incerta_horario_inicio' => $this->paraHorario($configuracao->unimed_verificacao_incerta_horario_inicio),
            'unimed_verificacao_incerta_horario_fim' => $this->paraHorario($configuracao->unimed_verificacao_incerta_horario_fim),
        ];
    }

    /**
     * Coluna TIME sem cast volta do banco como "HH:MM:SS" — normaliza pra
     * "HH:MM", o mesmo formato exigido na validação (date_format:H:i).
     */
    private function paraHorario(?string $valor): ?string
    {
        return $valor === null ? null : substr($valor, 0, 5);
    }
}

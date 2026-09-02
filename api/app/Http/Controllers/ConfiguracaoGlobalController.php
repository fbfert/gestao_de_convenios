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
            'automacao_reconsulta_status_ativo' => $configuracao->automacao_reconsulta_status_ativo,
            'automacao_captura_senha_validade_ativo' => $configuracao->automacao_captura_senha_validade_ativo,
            'unimed_captura_senha_validade_intervalo_horas' => $configuracao->unimed_captura_senha_validade_intervalo_horas,
            'automacao_verificacao_incerta_ativo' => $configuracao->automacao_verificacao_incerta_ativo,
            'automacao_sincronizacao_clinica_ativo' => $configuracao->automacao_sincronizacao_clinica_ativo,
            'automacao_sincronizacao_clinica_intervalo_minutos' => $configuracao->automacao_sincronizacao_clinica_intervalo_minutos,
            'automacao_expurgo_auditoria_ativo' => $configuracao->automacao_expurgo_auditoria_ativo,
            'automacao_expurgo_carteirinhas_ativo' => $configuracao->automacao_expurgo_carteirinhas_ativo,
            'automacao_verificacao_guias_diaria_ativo' => $configuracao->automacao_verificacao_guias_diaria_ativo,
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

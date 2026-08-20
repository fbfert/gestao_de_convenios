<?php

namespace App\Services\ClinicaSync;

use App\Models\ClinicaConexaoConfig;
use App\Models\ClinicaSyncExecucao;
use Carbon\CarbonImmutable;
use Throwable;

class ClinicaSyncService
{
    public function executar(int $tenantId, string $origem): ClinicaSyncExecucao
    {
        $execucao = ClinicaSyncExecucao::create([
            'tenant_id' => $tenantId,
            'origem' => $origem,
            'iniciado_em' => CarbonImmutable::now(),
        ]);

        $config = ClinicaConexaoConfig::where('tenant_id', $tenantId)->where('ativo', true)->first();

        if ($config === null) {
            return $this->finalizar($execucao, 'error', null, 'Sem configuração de conexão ativa com o clinica (clinica_conexao_configs).');
        }

        try {
            $api = new ClinicaApiClient($config);

            $profissionais = (new ProfissionalSyncService($api, $tenantId))->executar();
            $pacientes = (new PacienteSyncService($api, $tenantId))->executar();

            $config->forceFill([
                'ultima_execucao_em' => CarbonImmutable::now(),
                'ultima_execucao_status' => 'ok',
            ])->save();

            return $this->finalizar($execucao, 'ok', [
                'profissionais' => $profissionais,
                'pacientes' => $pacientes,
            ], null);
        } catch (Throwable $e) {
            $config->forceFill([
                'ultima_execucao_em' => CarbonImmutable::now(),
                'ultima_execucao_status' => 'error',
            ])->save();

            return $this->finalizar($execucao, 'error', null, $e->getMessage());
        }
    }

    private function finalizar(ClinicaSyncExecucao $execucao, string $status, ?array $resumo, ?string $erro): ClinicaSyncExecucao
    {
        $execucao->forceFill([
            'status' => $status,
            'finalizado_em' => CarbonImmutable::now(),
            'resumo' => $resumo,
            'erro_mensagem' => $erro,
        ])->save();

        return $execucao;
    }
}

<?php

namespace App\Services\Alertas\Regras;

use App\Models\Alerta;
use App\Models\AlertaRegra;
use App\Models\Guia;
use App\Scopes\TenantScope;
use App\Services\Alertas\AvaliadorDeAlerta;
use App\Support\GuiaStatus;

/**
 * Guias negadas que ninguem tratou ainda.
 *
 * ABSORVE o componente GuiaAlertaNegacoes, que some do dashboard e da tela de
 * Guias nesta fase. As duas acoes que ele oferecia — ocultar e abrir nova
 * solicitacao — viajam no payload `dados` para a tela do alerta; sem elas, a
 * troca seria regressao para quem opera todo dia.
 *
 * Ocultar o alerta de negacao continua sendo a acao existente da guia
 * (`alerta_negacao_ocultado_em`), e nao uma nova: e ela que faz este avaliador
 * parar de devolver a guia, e o fechamento automatico resolve o alerta sozinho
 * na rodada seguinte.
 */
class GuiaNegada implements AvaliadorDeAlerta
{
    public function avaliar(int $tenantId, AlertaRegra $regra): array
    {
        $guias = Guia::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('status', GuiaStatus::DENIED)
            ->whereNull('alerta_negacao_ocultado_em')
            // Guia historica e passado resolvido, nunca uma negacao pendente.
            ->naoHistorica()
            ->with(['paciente', 'especialidade', 'convenio'])
            ->get();

        return $guias->map(fn (Guia $guia) => [
            'nivel' => $regra->nivel_base ?: Alerta::NIVEL_VERMELHO,
            'titulo' => 'Guia negada — '.($guia->numero_guia ?: "#{$guia->id}"),
            'descricao' => ($guia->paciente?->nome ?? 'Paciente não informado')
                .' · '.($guia->especialidade?->nome ?? 'Especialidade não informada')
                .' · '.($guia->convenio?->nome ?? 'Convênio não informado'),
            'entidade' => 'guias',
            'entidade_id' => (int) $guia->id,
            'dados' => [
                'negada_em' => $guia->negada_em?->toIso8601String(),
                // As duas ações herdadas do banner que este alerta substitui.
                'acoes' => ['ocultar', 'nova_solicitacao'],
                'paciente_id' => $guia->paciente_id,
                'convenio_id' => $guia->convenio_id,
                'especialidade_id' => $guia->especialidade_id,
                'profissional_id' => $guia->profissional_id,
            ],
        ])->all();
    }
}

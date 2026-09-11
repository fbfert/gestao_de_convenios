<?php

namespace App\Services\Alertas\Regras;

use App\Models\Alerta;
use App\Models\AlertaRegra;
use App\Models\Guia;
use App\Services\Alertas\AvaliadorDeAlerta;

/**
 * Guias aprovadas (ou finalizadas) que já passaram da data-alvo de
 * antecipação — hora de alguém revisar e, se fizer sentido, gerar as guias do
 * próximo ciclo. Nunca gera nada sozinho: só avisa.
 *
 * Data-alvo vem de Guia::antecipacaoDataAlvo() (override da guia > override do
 * convênio > padrão global). Guia sem data de referência ainda preenchida
 * simplesmente não entra — não dá pra calcular.
 *
 * Duas ações viajam em `dados.acoes`, mesmo padrão de GuiaNegada: "ocultar"
 * (`alerta_antecipacao_ocultado_em`, dispensa esta guia) e "gerar_antecipacao"
 * — que leva pra tela /antecipacoes já abrindo a checklist de itens desta
 * solicitação (ver App\Services\AntecipacaoService::criar(), que gera itens
 * novos por renovação na MESMA solicitação, não uma solicitação nova).
 */
class AntecipacaoDevida implements AvaliadorDeAlerta
{
    /** Dias de atraso sobre a data-alvo a partir dos quais o alerta vira vermelho. */
    private const PADRAO_VERMELHO = 5;

    public function avaliar(int $tenantId, AlertaRegra $regra): array
    {
        $diasVermelho = $regra->limiar_vermelho ?? self::PADRAO_VERMELHO;
        $hoje = today();

        $guias = Guia::elegiveisParaAntecipacao($tenantId);

        return $guias->map(function (Guia $guia) use ($hoje, $diasVermelho, $regra) {
            $dataAlvo = $guia->antecipacaoDataAlvo();
            $atraso = $dataAlvo->diffInDays($hoje);
            $vermelho = $atraso >= $diasVermelho;

            return [
                'nivel' => $vermelho ? Alerta::NIVEL_VERMELHO : ($regra->nivel_base ?: Alerta::NIVEL_AMARELO),
                'titulo' => 'Antecipação devida — guia '.($guia->numero_guia ?: "#{$guia->id}"),
                'descricao' => ($guia->paciente?->nome ?? 'Paciente não informado')
                    .' · '.($guia->convenio?->nome ?? 'Convênio não informado')
                    .' · previsto para '.$dataAlvo->format('d/m/Y'),
                'entidade' => 'guias',
                'entidade_id' => (int) $guia->id,
                'dados' => [
                    'data_alvo' => $dataAlvo->toDateString(),
                    'dias_de_atraso' => (int) $atraso,
                    'acoes' => ['ocultar', 'gerar_antecipacao'],
                    'solicitacao_id' => $guia->solicitacao_id,
                ],
            ];
        })->values()->all();
    }
}

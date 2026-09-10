<?php

namespace App\Services\Alertas\Regras;

use App\Models\Alerta;
use App\Models\AlertaRegra;
use App\Models\Guia;
use App\Scopes\TenantScope;
use App\Services\Alertas\AvaliadorDeAlerta;
use App\Support\GuiaStatus;

/**
 * Guias aprovadas (ou finalizadas) que já passaram da data-alvo de
 * antecipação — hora de alguém revisar e, se fizer sentido, gerar a
 * solicitação do próximo ciclo. Nunca gera nada sozinho: só avisa.
 *
 * Data-alvo vem de Guia::antecipacaoDataAlvo() (override da guia > override do
 * convênio > padrão global). Guia sem data de referência ainda preenchida
 * (validade da senha ou data de finalização, conforme a regra escolhida)
 * simplesmente não entra — não dá pra calcular.
 *
 * Duas ações viajam em `dados.acoes`, mesmo padrão de GuiaNegada: "ocultar"
 * (`alerta_antecipacao_ocultado_em`, dispensa esta guia) e "nova_solicitacao"
 * — que aqui carrega médico, CIDs e todos os itens (especialidade+profissional)
 * da solicitação de origem, pra tela pré-preencher a solicitação inteira do
 * próximo ciclo, e não só um item.
 */
class AntecipacaoDevida implements AvaliadorDeAlerta
{
    /** Dias de atraso sobre a data-alvo a partir dos quais o alerta vira vermelho. */
    private const PADRAO_VERMELHO = 5;

    public function avaliar(int $tenantId, AlertaRegra $regra): array
    {
        $diasVermelho = $regra->limiar_vermelho ?? self::PADRAO_VERMELHO;
        $hoje = today();

        $guias = Guia::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [GuiaStatus::APPROVED, GuiaStatus::FINALIZED])
            ->whereNull('alerta_antecipacao_ocultado_em')
            ->naoHistorica()
            ->with([
                'paciente',
                'convenio',
                'solicitacao.medico',
                'solicitacao.cidCadastros',
                'solicitacao.itens.especialidade',
                'solicitacao.itens.profissional',
            ])
            ->get()
            ->filter(function (Guia $guia) use ($hoje) {
                $dataAlvo = $guia->antecipacaoDataAlvo();

                return $dataAlvo !== null && ! $hoje->lt($dataAlvo);
            });

        return $guias->map(function (Guia $guia) use ($hoje, $diasVermelho, $regra) {
            $dataAlvo = $guia->antecipacaoDataAlvo();
            $atraso = $dataAlvo->diffInDays($hoje);
            $vermelho = $atraso >= $diasVermelho;
            $solicitacao = $guia->solicitacao;

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
                    'acoes' => ['ocultar', 'nova_solicitacao'],
                    'paciente_id' => $guia->paciente_id,
                    'convenio_id' => $guia->convenio_id,
                    'medico' => $solicitacao?->medico ? [
                        'id' => $solicitacao->medico->id,
                        'nome' => $solicitacao->medico->nome,
                        'crm' => $solicitacao->medico->crm,
                        'crm_uf' => $solicitacao->medico->crm_uf,
                    ] : null,
                    'cid_ids' => $solicitacao?->cidCadastros->pluck('id')->all() ?? [],
                    // Par especialidade+profissional de cada item da solicitação
                    // de origem — repete o ciclo inteiro, não só um item.
                    'itens' => $solicitacao?->itens
                        ->filter(fn ($item) => $item->especialidade && $item->profissional)
                        ->unique(fn ($item) => "{$item->especialidade_id}-{$item->profissional_id}")
                        ->values()
                        ->map(fn ($item) => [
                            'especialidade_id' => $item->especialidade->id,
                            'especialidade_nome' => $item->especialidade->nome,
                            'profissional_id' => $item->profissional->id,
                            'profissional_nome' => $item->profissional->nome,
                        ])
                        ->all() ?? [],
                ],
            ];
        })->values()->all();
    }
}

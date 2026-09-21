import { useState } from 'react'
import { AvisoErro } from '../../components/ui/AvisoErro'
import { Tooltip } from '../../components/ui/Tooltip'
import { AutomacaoProgressoModal } from '../automacoes/AutomacaoProgressoModal'
import { AutomacaoUnimedDesativadaModal } from '../configuracoes/AutomacaoUnimedDesativadaModal'
import { useAutomacaoUnimedGate } from '../configuracoes/useAutomacaoUnimedGate'
import { useEnviarItemUnimed, useVerificarAndamentoItem } from './useSolicitacoes'
import { STATUS_QUE_BLOQUEIAM_ENVIO, type SolicitacaoStatus } from './types'

type Props = {
  itemId: number
  /** Só a presença importa aqui — com guia, nada deste componente é mostrado. */
  guia: object | null | undefined
  automacaoExecucaoAtiva: { id: number; operacao: string; status: string } | null | undefined
  convenio: { nome: string; connector_driver: string | null } | null | undefined
  solicitacaoStatus: string
  /** Prefixo dos data-testid — cada tela que usa isto tem os seus próprios (ex.: `solicitacao-item`, `antecipacao-item`). */
  testIdPrefix: string
  queryKeysInvalidar: string[][]
}

/**
 * Envia o item pra automação da operadora (hoje só Unimed RDA; outros
 * conectores futuros entram pelo mesmo botão, só trocando o rótulo pelo nome
 * do convênio) e mostra o andamento. Usado tanto na lista de itens de
 * Solicitações quanto no histórico de Antecipações — os itens gerados por
 * renovação lá também dependem deste envio manual pra ganhar guia.
 */
export function EnviarItemAutomacaoAcao({
  itemId,
  guia,
  automacaoExecucaoAtiva,
  convenio,
  solicitacaoStatus,
  testIdPrefix,
  queryKeysInvalidar,
}: Props) {
  const [progressoExecucaoId, setProgressoExecucaoId] = useState<number | null>(null)
  const enviarItemUnimed = useEnviarItemUnimed()
  const verificarAndamentoItem = useVerificarAndamentoItem()
  const { tratarErroUnimed, modalProps, avisoProps } = useAutomacaoUnimedGate()

  const isAutomatizado = convenio?.connector_driver === 'unimed_rda'

  if (!isAutomatizado || guia) {
    return null
  }

  // Gate por ITEM, e não pela solicitação inteira: exigir `ready_for_automation`
  // fazia o item novo de uma solicitação já aprovada nunca poder ser enviado —
  // que é justamente o caso de "Adicionar sessões" e da Antecipação. A lista
  // de status barrados espelha App\Support\SolicitacaoStatus::BLOQUEIAM_ENVIO,
  // e a API a aplica de novo do lado de lá.
  const canSend =
    !automacaoExecucaoAtiva &&
    !STATUS_QUE_BLOQUEIAM_ENVIO.includes(solicitacaoStatus as SolicitacaoStatus)

  // Guia incerta pos-submit (Finalizar rodou no portal mas o worker nao leu a
  // confirmacao de volta): sem numero de guia conhecido, so da pra confirmar
  // buscando por paciente, nao reenviando.
  const precisaVerificarAndamento =
    automacaoExecucaoAtiva?.operacao === 'gerar_guia' && automacaoExecucaoAtiva?.status === 'uncertain'

  const handleEnviar = async () => {
    try {
      const execucao = await enviarItemUnimed.mutateAsync(itemId)
      setProgressoExecucaoId(execucao.id)
    } catch (error) {
      tratarErroUnimed(error, 'Não foi possível enviar o item para a operadora.', () => void handleEnviar())
    }
  }

  const handleVerificarAndamento = async () => {
    try {
      const execucao = await verificarAndamentoItem.mutateAsync(itemId)
      setProgressoExecucaoId(execucao.id)
    } catch (error) {
      tratarErroUnimed(
        error,
        'Não foi possível verificar o andamento no portal da operadora.',
        () => void handleVerificarAndamento(),
      )
    }
  }

  return (
    <>
      {automacaoExecucaoAtiva ? (
        <button
          type="button"
          onClick={() => setProgressoExecucaoId(automacaoExecucaoAtiva.id)}
          className="rounded-full border border-amber-400/20 bg-amber-400/10 px-2.5 py-1 text-meta font-semibold text-amber-100 transition hover:bg-amber-400/20"
          data-testid={`${testIdPrefix}-execucao-ativa-${itemId}`}
        >
          {automacaoExecucaoAtiva.status} · ver andamento
        </button>
      ) : precisaVerificarAndamento ? (
        <>
          <button
            type="button"
            onClick={() => void handleVerificarAndamento()}
            disabled={verificarAndamentoItem.isPending}
            className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
            data-testid={`${testIdPrefix}-verificar-andamento-${itemId}`}
          >
            Verificar Andamento
          </button>
          <Tooltip rotulo="O que este botão faz">
            O robô tentou gerar a guia mas não conseguiu confirmar o resultado com o portal.
            Clique para checar se a guia foi criada de fato, buscando pelo paciente em Exames em
            aberto — sem reenviar a solicitação.
          </Tooltip>
        </>
      ) : (
        <>
          <button
            type="button"
            onClick={() => void handleEnviar()}
            disabled={!canSend || enviarItemUnimed.isPending}
            className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
            data-testid={`${testIdPrefix}-enviar-unimed-${itemId}`}
          >
            Enviar para {convenio?.nome ?? 'a operadora'}
          </button>
          <Tooltip rotulo="Quando este botão funciona">
            Dispara o robô da operadora para gerar a guia sozinho (ver Automações). Só fica ativo
            com a solicitação pronta para automatização, sem guia gerada ainda e sem outra
            execução em andamento para este item.
          </Tooltip>
        </>
      )}

      <AutomacaoProgressoModal
        execucaoId={progressoExecucaoId}
        onClose={() => setProgressoExecucaoId(null)}
        queryKeysInvalidar={queryKeysInvalidar}
      />
      {avisoProps.mensagem ? (
        <div className="w-full">
          <AvisoErro {...avisoProps} testId={`${testIdPrefix}-automacao-unimed-erro-${itemId}`} />
        </div>
      ) : null}
      <AutomacaoUnimedDesativadaModal {...modalProps} />
    </>
  )
}

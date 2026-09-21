import { useState } from 'react'
import { Botao } from '../../components/ui/Botao'
import { useConfirm } from '../../components/ui/ConfirmDialog'
import { AutomacaoProgressoModal } from '../automacoes/AutomacaoProgressoModal'
import {
  getHttpErrorMessage,
  useConferirGuiaFinalizadaUnimed,
  useConferirGuiasFinalizadasEmLote,
} from './useGuias'

/**
 * "Conferir na Unimed" — a pergunta ao portal sobre guias já finalizadas lá.
 *
 * Existe por causa do passivo: a clínica encerrou guias no portal à mão durante
 * meses antes de a automação existir, e este sistema não sabe disso. A ação em
 * lote liquida esse passivo de uma vez; o botão por guia serve para o caso
 * avulso depois.
 *
 * Nada é alterado no portal — é consulta.
 */

export function ConferirFinalizadaUnimedButton({
  guiaId,
  jaConferida,
}: {
  guiaId: number
  jaConferida: boolean
}) {
  const conferir = useConferirGuiaFinalizadaUnimed()
  const [execucaoId, setExecucaoId] = useState<number | null>(null)
  const [erro, setErro] = useState<string | null>(null)

  const acionar = async () => {
    setErro(null)

    try {
      const execucao = await conferir.mutateAsync(guiaId)
      setExecucaoId(execucao.id)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível conferir a guia na Unimed.'))
    }
  }

  return (
    <>
      <button
        type="button"
        onClick={(event) => {
          event.stopPropagation()
          void acionar()
        }}
        disabled={conferir.isPending}
        className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
        data-testid={`guia-conferir-finalizada-${guiaId}`}
      >
        {conferir.isPending ? 'Conferindo...' : jaConferida ? 'Conferir de novo' : 'Conferir na Unimed'}
      </button>

      {erro ? (
        <p className="text-corpo text-rose-300" data-testid={`guia-conferir-erro-${guiaId}`}>
          {erro}
        </p>
      ) : null}

      <AutomacaoProgressoModal
        execucaoId={execucaoId}
        onClose={() => setExecucaoId(null)}
        titulo="Conferindo a guia na Unimed"
        descricao="O robô está procurando esta guia entre os exames finalizados do portal."
        mensagemExecutando="Procurando a guia entre os exames finalizados..."
        queryKeysInvalidar={[['guias']]}
      />
    </>
  )
}

export function ConferirFinalizadasEmLoteButton() {
  const conferir = useConferirGuiasFinalizadasEmLote()
  const confirmar = useConfirm()
  const [execucaoId, setExecucaoId] = useState<number | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [resumo, setResumo] = useState<{
    total: number
    convenio: string | null
    restantes: number
  } | null>(null)

  const acionar = async (incluirJaConferidas: boolean) => {
    setErro(null)

    try {
      const execucao = await conferir.mutateAsync({ incluirJaConferidas })
      setResumo({
        total: execucao.total_guias,
        convenio: execucao.convenio,
        restantes: execucao.restantes_de_outros_convenios,
      })
      setExecucaoId(execucao.id)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível conferir as guias na Unimed.'))
    }
  }

  /*
   * Reconferir pede confirmação porque refaz TODAS — inclusive as que já têm
   * resposta. É a saída para um lote que correu errado, não a ação de todo
   * dia, e uma ida ao portal por guia não é de graça.
   */
  const reconferir = async () => {
    const ok = await confirmar({
      titulo: 'Reconferir todas as guias',
      descricao:
        'Refaz a conferência de todas as guias Unimed no portal, inclusive as que já foram conferidas. Use quando desconfiar de um lote anterior — por exemplo, se ele devolveu zero finalizadas. Confirma?',
      confirmarTexto: 'Reconferir todas',
      variante: 'primario',
    })

    if (ok) {
      await acionar(true)
    }
  }

  return (
    <div className="space-y-2" data-testid="guias-conferir-lote">
      <div className="flex flex-wrap items-center gap-2">
        <Botao
          variante="secundario"
          onClick={() => void acionar(false)}
          disabled={conferir.isPending}
          data-testid="guias-conferir-lote-botao"
        >
          {conferir.isPending ? 'Conferindo...' : 'Conferir finalizadas na Unimed'}
        </Botao>
        <button
          type="button"
          onClick={() => void reconferir()}
          disabled={conferir.isPending}
          className="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1.5 text-meta font-semibold text-amber-100 transition hover:bg-amber-400/20 disabled:opacity-50"
          data-testid="guias-reconferir-lote-botao"
        >
          Reconferir todas
        </button>
      </div>

      {resumo ? (
        <div className="text-corpo text-slate-300" data-testid="guias-conferir-lote-total">
          <p>
            {resumo.total} guia(s) na fila de conferência
            {resumo.convenio ? ` · ${resumo.convenio}` : ''}.
          </p>
          {/* A credencial do portal é por convênio, então o lote cobre um por
              vez. Sem este aviso, metade do passivo ficaria para trás sem
              ninguém perceber. */}
          {resumo.restantes > 0 ? (
            <p className="text-amber-200" data-testid="guias-conferir-lote-restantes">
              Faltam {resumo.restantes} guia(s) de outro convênio — rode de novo quando este lote
              terminar.
            </p>
          ) : null}
        </div>
      ) : null}

      {erro ? (
        <p className="text-corpo text-rose-300" data-testid="guias-conferir-lote-erro">
          {erro}
        </p>
      ) : null}

      <AutomacaoProgressoModal
        execucaoId={execucaoId}
        onClose={() => setExecucaoId(null)}
        titulo="Conferindo guias na Unimed"
        descricao="O robô está procurando cada guia entre os exames finalizados do portal. Nada é alterado lá."
        mensagemExecutando="Procurando as guias entre os exames finalizados..."
        queryKeysInvalidar={[['guias'], ['solicitacoes']]}
      />
    </div>
  )
}

import { useState } from 'react'
import { Botao } from '../../components/ui/Botao'
import { Tooltip } from '../../components/ui/Tooltip'
import { AutomacaoProgressoModal } from '../automacoes/AutomacaoProgressoModal'
import {
  getHttpErrorMessage,
  useFinalizarGuiaUnimed,
  useFinalizarUnimedPreVoo,
} from './useGuias'
import type { FinalizarUnimedDecisao } from './types'

/**
 * Finalizar a guia NA UNIMED.
 *
 * Substitui o Finalizar manual para guia de convênio com automação: desde a
 * change `automacao-unimed-finalizar-guia`, guia Unimed só é dada por
 * finalizada no Gescon depois que a operadora aceitou.
 *
 * O botão abre um painel em vez de agir direto porque quase toda finalização
 * tem uma pergunta antes: menos sessões do que o autorizado, mais do que o
 * autorizado, nenhuma folha anexada. Cada uma dessas decisões volta como
 * confirmação explícita no disparo — a API recusa sem elas, então um clique
 * apressado não passa.
 */

type GuiaParaFinalizar = {
  id: number
  status: string
  numero_guia?: string | null
}

function decisaoRotulo(decisao: FinalizarUnimedDecisao): string {
  if (decisao.chave === 'limitar_ao_autorizado') {
    return `Enviar apenas as ${decisao.autorizadas} sessões mais antigas`
  }

  if (decisao.chave === 'confirmar_menos_sessoes') {
    return `Finalizar com ${decisao.registradas} de ${decisao.autorizadas} sessões`
  }

  return 'Finalizar sem folha de registro anexada'
}

export function FinalizarNaUnimedButton({ guia }: { guia: GuiaParaFinalizar }) {
  const [aberto, setAberto] = useState(false)
  const [confirmadas, setConfirmadas] = useState<Record<string, boolean>>({})
  const [erro, setErro] = useState<string | null>(null)
  const [execucaoId, setExecucaoId] = useState<number | null>(null)

  const preVoo = useFinalizarUnimedPreVoo(guia.id, aberto)
  const finalizar = useFinalizarGuiaUnimed()

  const dados = preVoo.data
  const decisoes = dados?.decisoes ?? []
  const faltaConfirmar = decisoes.some((decisao) => !confirmadas[decisao.chave])
  const podeDisparar = Boolean(dados?.pode_finalizar) && !faltaConfirmar && !finalizar.isPending

  const abrir = () => {
    setAberto(true)
    setErro(null)
    setConfirmadas({})
  }

  const disparar = async () => {
    setErro(null)

    try {
      const execucao = await finalizar.mutateAsync({ id: guia.id, confirmacoes: confirmadas })
      setExecucaoId(execucao.id)
      setAberto(false)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível finalizar a guia na Unimed.'))
    }
  }

  return (
    <div className="space-y-3" data-testid={`guia-finalizar-unimed-acao-${guia.id}`}>
      <button
        type="button"
        className="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-meta font-semibold text-emerald-100 transition hover:bg-emerald-400/20 disabled:opacity-50"
        onClick={(event) => {
          event.stopPropagation()
          abrir()
        }}
        disabled={finalizar.isPending}
        data-testid={`guia-finalizar-unimed-${guia.id}`}
      >
        Finalizar na Unimed
      </button>

      {aberto ? (
        <div
          className="grid gap-3 rounded-2xl border border-white/10 bg-slate-950/50 p-4"
          onClick={(event) => event.stopPropagation()}
          data-testid={`guia-finalizar-unimed-painel-${guia.id}`}
        >
          {preVoo.isLoading ? (
            <p className="text-corpo text-slate-300">Conferindo a guia...</p>
          ) : null}

          {dados?.simular ? (
            <p
              className="rounded-2xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-corpo text-amber-100"
              data-testid={`guia-finalizar-unimed-simulacao-${guia.id}`}
            >
              <span className="font-semibold">Modo simulação ligado.</span> O robô vai preencher a
              guia e anexar as folhas no portal, mas <strong>não</strong> vai gravar nem finalizar.
              Desligue em Configurações quando quiser finalizar de verdade.
            </p>
          ) : null}

          {dados?.impedimentos.length ? (
            <div
              className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100"
              data-testid={`guia-finalizar-unimed-impedimentos-${guia.id}`}
            >
              <p className="font-semibold">Não dá para finalizar esta guia:</p>
              <ul className="mt-2 list-disc space-y-1 pl-5">
                {dados.impedimentos.map((motivo) => (
                  <li key={motivo}>{motivo}</li>
                ))}
              </ul>
            </div>
          ) : null}

          {dados?.conflitos.length ? (
            <div
              className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100"
              data-testid={`guia-finalizar-unimed-conflitos-${guia.id}`}
            >
              <p className="font-semibold">
                Há sessões em conflito na agenda do paciente. Corrija-as antes de finalizar — não há
                como enviar assim.
              </p>
              <ul className="mt-2 list-disc space-y-1 pl-5">
                {dados.conflitos.map((conflito, indice) => (
                  <li key={indice}>{conflito.mensagem}</li>
                ))}
              </ul>
            </div>
          ) : null}

          {dados && dados.impedimentos.length === 0 && dados.conflitos.length === 0 ? (
            <p className="text-corpo text-slate-300">
              {dados.sessoes.length} sessão(ões) serão enviadas ao portal, e {dados.folhas} folha(s)
              de registro anexada(s).
            </p>
          ) : null}

          {decisoes.map((decisao) => (
            <label
              key={decisao.chave}
              className="flex items-start gap-3 rounded-2xl border border-amber-400/20 bg-amber-500/5 px-4 py-3 text-corpo text-amber-50"
              data-testid={`guia-finalizar-unimed-decisao-${decisao.chave}`}
            >
              <input
                type="checkbox"
                className="mt-1"
                checked={Boolean(confirmadas[decisao.chave])}
                onChange={(event) =>
                  setConfirmadas((atual) => ({ ...atual, [decisao.chave]: event.target.checked }))
                }
                data-testid={`guia-finalizar-unimed-decisao-check-${decisao.chave}`}
              />
              <span>
                <span className="block">{decisao.mensagem}</span>
                <span className="mt-1 block font-semibold">{decisaoRotulo(decisao)}</span>
              </span>
            </label>
          ))}

          <div className="flex flex-wrap items-center gap-2">
            <Botao
              variante="primario"
              onClick={() => void disparar()}
              disabled={!podeDisparar}
              data-testid={`guia-finalizar-unimed-confirmar-${guia.id}`}
            >
              {finalizar.isPending
                ? 'Enviando...'
                : dados?.simular
                  ? 'Simular no portal'
                  : 'Finalizar na Unimed'}
            </Botao>
            <Botao variante="secundario" onClick={() => setAberto(false)}>
              Cancelar
            </Botao>
            <Tooltip rotulo="O que acontece aqui">
              <p className="font-semibold text-white">O robô finaliza a guia no portal</p>
              <p className="mt-1">
                Ele busca a guia, preenche as datas das sessões registradas, anexa as folhas e
                clica em Gravar e Finalizar. A guia só fica finalizada no Gescon quando a Unimed
                aceitar.
              </p>
            </Tooltip>
          </div>
        </div>
      ) : null}

      {erro ? (
        <p
          className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100"
          data-testid={`guia-finalizar-unimed-erro-${guia.id}`}
        >
          {erro}
        </p>
      ) : null}

      <AutomacaoProgressoModal
        execucaoId={execucaoId}
        onClose={() => setExecucaoId(null)}
        titulo="Finalizando a guia na Unimed"
        descricao="Acompanhe o preenchimento da guia, o envio das folhas e a finalização no portal da Unimed."
        mensagemExecutando="O robô está finalizando esta guia no portal da Unimed..."
        queryKeysInvalidar={[['guias'], ['lancamentos']]}
      />
    </div>
  )
}

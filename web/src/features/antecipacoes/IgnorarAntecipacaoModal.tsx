import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useEffect, useState } from 'react'
import { X } from 'lucide-react'
import { Botao } from '../../components/ui/Botao'
import { useFechamentoExplicito } from '../../lib/useFechamentoExplicito'
import { formatarData } from '../solicitacoes/datas'
import { getHttpErrorMessage, useIgnorarAntecipacao } from './useAntecipacoes'
import type { AntecipacaoElegivel } from './types'

type IgnorarAntecipacaoModalProps = {
  elegivel: AntecipacaoElegivel | null
  onClose: () => void
}

/**
 * Confirmação de "Ignorar".
 *
 * Existe porque ignorar era um clique único e sem volta visível: o registro
 * `ignorada` faz a solicitação sumir da fila de elegíveis (ver
 * `AntecipacaoService::listarElegiveis`, que exclui pela EXISTÊNCIA de
 * registro), e o botão ficava colado no "Gerar". Errar de alvo custava o
 * ciclo inteiro do paciente.
 *
 * O motivo é opcional de propósito: exigir texto faria alguém digitar "x"
 * para passar da tela. Quando preenchido, vira `observacoes` e aparece no
 * tooltip do histórico, respondendo depois "por que isso foi ignorado?".
 */
export function IgnorarAntecipacaoModal({ elegivel, onClose }: IgnorarAntecipacaoModalProps) {
  const ignorar = useIgnorarAntecipacao()
  const [motivo, setMotivo] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const fechamento = useFechamentoExplicito(elegivel !== null, onClose)

  useEffect(() => {
    if (!elegivel) return

    setMotivo('')
    setErro(null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [elegivel?.solicitacao_id])

  if (!elegivel) {
    return null
  }

  const confirmar = async () => {
    setErro(null)

    try {
      await ignorar.mutateAsync({
        solicitacao_origem_id: elegivel.solicitacao_id,
        // A data prevista da fila é calculada ao vivo; gravá-la aqui é o que
        // deixa o histórico dizer "era pra ter sido em tal dia" depois que a
        // guia mudar de estado e o cálculo não valer mais.
        data_alvo: elegivel.data_alvo,
        observacoes: motivo.trim() || null,
      })

      onClose()
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível ignorar esta antecipação.'))
    }
  }

  return (
    <Dialog {...fechamento} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-md rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="ignorar-antecipacao-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <DialogTitle className="text-titulo font-semibold">Ignorar antecipação</DialogTitle>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full border border-white/10 bg-white/5 p-2 text-white transition hover:bg-white/10"
                aria-label="Fechar"
              >
                <X className="size-4" aria-hidden="true" />
              </button>
            </div>

            <div className="mt-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
              <p className="text-corpo font-medium text-white">
                {elegivel.paciente?.nome ?? 'Paciente não informado'} ·{' '}
                {elegivel.convenio?.nome ?? 'Convênio não informado'}
              </p>
              <p className="text-meta text-slate-400">
                Previsto para {formatarData(elegivel.data_alvo)} · {elegivel.guias.length} item(ns)
              </p>
            </div>

            <p className="mt-4 text-corpo text-slate-300">
              Nada é gerado. Esta solicitação sai da lista de elegíveis e vai para o histórico como{' '}
              <strong className="font-semibold text-white">Ignorada</strong>. Dá para desfazer
              depois, pelo histórico.
            </p>

            <label className="mt-4 block space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">
                Motivo (opcional)
              </span>
              <textarea
                value={motivo}
                onChange={(evento) => setMotivo(evento.target.value)}
                rows={3}
                placeholder="Ex.: paciente em alta, tratamento encerrado..."
                className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-corpo text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20"
                data-testid="ignorar-antecipacao-motivo"
              />
            </label>

            {erro ? (
              <p className="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                {erro}
              </p>
            ) : null}

            <div className="mt-6 flex justify-end gap-3">
              <Botao type="button" variante="secundario" onClick={onClose}>
                Cancelar
              </Botao>
              <Botao
                type="button"
                variante="perigo"
                disabled={ignorar.isPending}
                onClick={() => void confirmar()}
                data-testid="ignorar-antecipacao-confirmar"
              >
                {ignorar.isPending ? 'Ignorando...' : 'Ignorar'}
              </Botao>
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}

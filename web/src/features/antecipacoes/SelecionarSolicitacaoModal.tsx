import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { Search, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useFechamentoExplicito } from '../../lib/useFechamentoExplicito'
import { useDebouncedValue } from '../../lib/useDebouncedValue'
import { useSolicitacoes } from '../solicitacoes/useSolicitacoes'
import type { Solicitacao } from '../solicitacoes/types'

type SelecionarSolicitacaoModalProps = {
  open: boolean
  onClose: () => void
  onSelecionar: (solicitacao: Solicitacao) => void
}

/**
 * Busca simples por paciente, pra criação manual de antecipação a partir da
 * própria tela /antecipacoes (sem passar por uma Solicitação específica
 * primeiro). Modelada em SelecionarPacienteModal — mesmo padrão de busca.
 */
export function SelecionarSolicitacaoModal({ open, onClose, onSelecionar }: SelecionarSolicitacaoModalProps) {
  const [termo, setTermo] = useState('')
  const debounced = useDebouncedValue(termo, 500)

  useEffect(() => {
    if (!open) {
      setTermo('')
    }
  }, [open])

  const query = useSolicitacoes(
    {
      status: '',
      convenio_id: '',
      paciente: debounced,
      profissional: '',
      medico: '',
      mostrar_historico: '',
    },
    1,
  )

  const itens = debounced.trim().length >= 2 ? query.data?.data ?? [] : []

  return (
    <Dialog {...useFechamentoExplicito(open, onClose)} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-xl rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="selecionar-solicitacao-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <DialogTitle className="text-titulo font-semibold">Selecionar solicitação</DialogTitle>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full border border-white/10 bg-white/5 p-2 text-white transition hover:bg-white/10"
                aria-label="Fechar"
              >
                <X className="size-4" aria-hidden="true" />
              </button>
            </div>

            <div className="relative mt-4">
              <Search
                className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
              />
              <input
                value={termo}
                onChange={(event) => setTermo(event.target.value)}
                placeholder="Buscar por nome do paciente"
                className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 pl-11 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20"
                data-testid="selecionar-solicitacao-busca"
              />
            </div>

            <div className="mt-4 max-h-96 space-y-1 overflow-y-auto">
              {debounced.trim().length < 2 ? (
                <p className="rounded-2xl px-4 py-3 text-corpo text-slate-400">
                  Digite ao menos 2 caracteres para buscar.
                </p>
              ) : null}

              {query.isLoading ? (
                <p className="rounded-2xl px-4 py-3 text-corpo text-slate-300">Carregando...</p>
              ) : null}

              {!query.isLoading && debounced.trim().length >= 2 && itens.length === 0 ? (
                <p className="rounded-2xl px-4 py-3 text-corpo text-slate-400">Nenhuma solicitação encontrada.</p>
              ) : null}

              {itens.map((solicitacao) => (
                <button
                  key={solicitacao.id}
                  type="button"
                  onClick={() => {
                    onSelecionar(solicitacao)
                    onClose()
                  }}
                  className="w-full rounded-2xl px-4 py-3 text-left transition hover:bg-cyan-400/10"
                  data-testid="selecionar-solicitacao-item"
                >
                  <p className="text-corpo font-medium text-white">
                    {solicitacao.paciente?.nome ?? 'Paciente não informado'}
                  </p>
                  <p className="text-meta text-slate-400">
                    {solicitacao.convenio?.nome ?? 'Convênio não informado'} · #{solicitacao.id}
                  </p>
                </button>
              ))}
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}

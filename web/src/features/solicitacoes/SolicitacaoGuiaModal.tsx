import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { GuiaDetalheResumo } from '../guias/GuiaDetalheResumo'
import { getHttpErrorMessage, useGuia } from '../guias/useGuias'
import type { Solicitacao } from './types'

type SolicitacaoGuiaModalProps = {
  solicitacao: Solicitacao | null
  onClose: () => void
}

export function SolicitacaoGuiaModal({ solicitacao, onClose }: SolicitacaoGuiaModalProps) {
  const guiaId = solicitacao?.guia?.id ?? null
  const guiaQuery = useGuia(guiaId)
  const open = solicitacao !== null

  return (
    <Dialog open={open} onClose={onClose} className="relative z-50">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-6xl rounded-[2rem] border border-white/10 bg-slate-950 p-6 text-white shadow-2xl shadow-black/60"
            data-testid="solicitacao-guia-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <DialogTitle className="text-xl font-semibold">Detalhes da guia</DialogTitle>
                <p className="mt-1 text-sm text-slate-300">
                  Clique no nome do paciente para consultar a guia sem sair da lista de solicitações.
                </p>
              </div>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
              >
                Fechar
              </button>
            </div>

            <div className="mt-6">
              {!solicitacao?.guia ? (
                <div
                  className="rounded-3xl border border-amber-400/20 bg-amber-500/10 p-5 text-sm text-amber-50"
                  data-testid="solicitacao-guia-empty"
                >
                  Esta solicitação ainda não possui uma guia vinculada.
                </div>
              ) : guiaQuery.isLoading ? (
                <div
                  className="rounded-3xl border border-white/10 bg-white/5 p-5 text-sm text-slate-300"
                  data-testid="solicitacao-guia-loading"
                >
                  Carregando detalhes da guia...
                </div>
              ) : guiaQuery.isError || !guiaQuery.data ? (
                <div
                  className="space-y-2 rounded-3xl border border-rose-400/20 bg-rose-500/10 p-5 text-sm text-rose-100"
                  data-testid="solicitacao-guia-error"
                >
                  <p>Não foi possível carregar a guia vinculada.</p>
                  <p className="text-xs text-rose-100/80">
                    {getHttpErrorMessage(guiaQuery.error, 'Confira o vínculo da solicitação e tente novamente.')}
                  </p>
                </div>
              ) : (
                <div data-testid="solicitacao-guia-content">
                  <GuiaDetalheResumo guia={guiaQuery.data} />
                </div>
              )}
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}

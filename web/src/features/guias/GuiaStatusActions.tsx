import { useState } from 'react'
import { getHttpErrorMessage, useFinalizarGuia, useNegarGuia } from './useGuias'
import type { Guia, GuiaFinalizarForm } from './types'
import { Tooltip } from '../../components/ui/Tooltip'

const emptyFinalizeForm: GuiaFinalizarForm = {
  senha: '',
  validade_senha: '',
}

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function GuiaStatusActions({ guia }: { guia: Guia }) {
  const [isFinalizeOpen, setIsFinalizeOpen] = useState(false)
  const [finalizeDraft, setFinalizeDraft] = useState<GuiaFinalizarForm>(emptyFinalizeForm)
  const [actionError, setActionError] = useState<string | null>(null)
  const finalizarGuia = useFinalizarGuia()
  const negarGuia = useNegarGuia()

  const handleFinalize = async () => {
    setActionError(null)

    try {
      await finalizarGuia.mutateAsync({
        id: guia.id,
        payload: {
          senha: finalizeDraft.senha,
          ...(finalizeDraft.validade_senha ? { validade_senha: finalizeDraft.validade_senha } : {}),
        },
      })
      setIsFinalizeOpen(false)
      setFinalizeDraft(emptyFinalizeForm)
    } catch (error) {
      setActionError(getHttpErrorMessage(error, 'Não foi possível finalizar a guia.'))
    }
  }

  const handleNegar = async () => {
    setActionError(null)

    try {
      await negarGuia.mutateAsync(guia.id)
    } catch (error) {
      setActionError(getHttpErrorMessage(error, 'Não foi possível negar a guia.'))
    }
  }

  if (guia.status !== 'under_review') {
    return null
  }

  return (
    <div className="space-y-3" data-testid={`guia-status-actions-${guia.id}`}>
      <div className="flex flex-wrap gap-2">
        <button
          type="button"
          className="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-xs font-semibold text-emerald-100 transition hover:bg-emerald-400/20 disabled:opacity-50"
          onClick={() => {
            setIsFinalizeOpen(true)
            setActionError(null)
          }}
          disabled={finalizarGuia.isPending || negarGuia.isPending}
          data-testid={`guia-finalizar-${guia.id}`}
        >
          Finalizar
        </button>
        <button
          type="button"
          className="rounded-full border border-rose-400/30 bg-rose-400/10 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/20 disabled:opacity-50"
          onClick={handleNegar}
          disabled={finalizarGuia.isPending || negarGuia.isPending}
          data-testid={`guia-negar-${guia.id}`}
        >
          Negar
        </button>
      </div>

      {isFinalizeOpen ? (
        <div className="grid gap-3 rounded-2xl border border-white/10 bg-slate-950/50 p-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
          <label className="space-y-2">
            <span className="flex items-center gap-1 text-xs uppercase tracking-[0.25em] text-slate-400">
              Senha
              <Tooltip rotulo="O que é a senha aqui">
                É o código de autorização que o convênio devolve para a guia — não é senha de login.
                Preencher aqui muda o status para Aprovada.
              </Tooltip>
            </span>
            <input
              value={finalizeDraft.senha}
              onChange={(event) =>
                setFinalizeDraft((current) => ({ ...current, senha: event.target.value }))
              }
              className={inputClasses()}
              placeholder="ABC123"
              data-testid={`guia-senha-${guia.id}`}
            />
          </label>
          <label className="space-y-2">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">
              Validade senha
            </span>
            <input
              type="date"
              value={finalizeDraft.validade_senha ?? ''}
              onChange={(event) =>
                setFinalizeDraft((current) => ({ ...current, validade_senha: event.target.value }))
              }
              className={inputClasses()}
              data-testid={`guia-validade-${guia.id}`}
            />
          </label>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={handleFinalize}
              disabled={finalizarGuia.isPending}
              className="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
              data-testid={`guia-finalizar-confirmar-${guia.id}`}
            >
              {finalizarGuia.isPending ? 'Finalizando...' : 'Finalizar'}
            </button>
            <button
              type="button"
              onClick={() => setIsFinalizeOpen(false)}
              className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
            >
              Cancelar
            </button>
          </div>
        </div>
      ) : null}

      {actionError ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
          {actionError}
        </p>
      ) : null}
    </div>
  )
}

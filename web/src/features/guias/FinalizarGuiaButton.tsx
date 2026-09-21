import { useState } from 'react'
import { getHttpErrorMessage, useFinalizarGuia } from './useGuias'
import type { GuiaFinalizarForm } from './types'
import { Botao } from '../../components/ui/Botao'
import { Tooltip } from '../../components/ui/Tooltip'
import { useConfirm } from '../../components/ui/ConfirmDialog'

const emptyFinalizeForm: GuiaFinalizarForm = {
  senha: '',
  validade_senha: '',
}

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

type GuiaParaFinalizar = {
  id: number
  status: string
  numero_guia?: string | null
  senha?: string | null
  validade_senha?: string | null
}

/**
 * Finalizar exige ao menos 1 sessão registrada (trava real no backend,
 * GuiaService::finalizar) — por isso este botão só existe no grupo por guia
 * de LancamentosPage, e não mais nas telas de Guias: todo grupo que aparece
 * lá já tem sessão, por construção de listarAgrupadoPorGuia.
 */
export function FinalizarGuiaButton({ guia }: { guia: GuiaParaFinalizar | null | undefined }) {
  const [isOpen, setIsOpen] = useState(false)
  const [draft, setDraft] = useState<GuiaFinalizarForm>(emptyFinalizeForm)
  const [error, setError] = useState<string | null>(null)
  const finalizarGuia = useFinalizarGuia()
  const confirmar = useConfirm()

  if (!guia) {
    return null
  }

  const podeFinalizar = guia.status === 'under_review' || guia.status === 'approved'

  if (!podeFinalizar) {
    return null
  }

  const handleFinalize = async () => {
    setError(null)

    const ok = await confirmar({
      titulo: 'Finalizar guia',
      descricao:
        guia.status === 'approved'
          ? 'Abre o ciclo de faturamento (Antecipação) desta guia, usando a senha e validade já capturadas pela automação. Confirma?'
          : 'A guia é finalizada com a senha e validade informadas. Confirma?',
      confirmarTexto: 'Finalizar',
      variante: 'primario',
    })

    if (!ok) {
      return
    }

    try {
      await finalizarGuia.mutateAsync({
        id: guia.id,
        payload: {
          senha: draft.senha,
          ...(draft.validade_senha ? { validade_senha: draft.validade_senha } : {}),
        },
      })
      setIsOpen(false)
      setDraft(emptyFinalizeForm)
    } catch (err) {
      setError(getHttpErrorMessage(err, 'Não foi possível finalizar a guia.'))
    }
  }

  return (
    <div className="space-y-3" data-testid={`guia-finalizar-acao-${guia.id}`}>
      <button
        type="button"
        className="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-meta font-semibold text-emerald-100 transition hover:bg-emerald-400/20 disabled:opacity-50"
        onClick={(event) => {
          event.stopPropagation()
          setIsOpen(true)
          setError(null)
          setDraft({
            senha: guia.senha ?? '',
            validade_senha: guia.validade_senha ?? '',
          })
        }}
        disabled={finalizarGuia.isPending}
        data-testid={`guia-finalizar-${guia.id}`}
      >
        Finalizar
      </button>

      {isOpen ? (
        <div
          className="grid gap-3 rounded-2xl border border-white/10 bg-slate-950/50 p-4"
          onClick={(event) => event.stopPropagation()}
        >
          <label className="space-y-2">
            <span className="flex items-center gap-1 text-meta uppercase tracking-[0.25em] text-slate-400">
              Senha
              <Tooltip rotulo="O que é a senha aqui">
                É o código de autorização que o convênio devolve para a guia — não é senha de login.
                Preencher aqui muda o status para Finalizado.
              </Tooltip>
            </span>
            <input
              value={draft.senha}
              onChange={(event) => setDraft((current) => ({ ...current, senha: event.target.value }))}
              className={inputClasses()}
              placeholder="ABC123"
              data-testid={`guia-senha-${guia.id}`}
            />
          </label>
          <label className="space-y-2">
            <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Validade senha</span>
            <input
              type="date"
              value={draft.validade_senha ?? ''}
              onChange={(event) =>
                setDraft((current) => ({ ...current, validade_senha: event.target.value }))
              }
              className={inputClasses()}
              data-testid={`guia-validade-${guia.id}`}
            />
          </label>
          <div className="flex gap-2">
            <Botao
              variante="primario"
              onClick={handleFinalize}
              disabled={finalizarGuia.isPending}
              data-testid={`guia-finalizar-confirmar-${guia.id}`}
            >
              {finalizarGuia.isPending ? 'Finalizando...' : 'Finalizar'}
            </Botao>
            <Botao variante="secundario" onClick={() => setIsOpen(false)}>
              Cancelar
            </Botao>
          </div>
        </div>
      ) : null}

      {error ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
          {error}
        </p>
      ) : null}
    </div>
  )
}

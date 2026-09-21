import { useState } from 'react'
import { getHttpErrorMessage, useAprovarGuia, useNegarGuia } from './useGuias'
import type { Guia } from './types'
import { useConfirm } from '../../components/ui/ConfirmDialog'

export function GuiaStatusActions({ guia }: { guia: Guia }) {
  const [actionError, setActionError] = useState<string | null>(null)
  const aprovarGuia = useAprovarGuia()
  const negarGuia = useNegarGuia()
  const confirmar = useConfirm()

  const podeAprovar = guia.status === 'under_review'
  const podeNegar = guia.status === 'under_review'

  const handleAprovar = async () => {
    setActionError(null)

    const ok = await confirmar({
      titulo: 'Aprovar guia',
      descricao: `Guia #${guia.id}${guia.numero_guia ? ` (${guia.numero_guia})` : ''} será marcada como aprovada, liberando o lançamento de sessões. Use quando a operadora já autorizou fora da automação. Confirma?`,
      confirmarTexto: 'Aprovar',
      variante: 'primario',
    })

    if (!ok) {
      return
    }

    try {
      await aprovarGuia.mutateAsync(guia.id)
    } catch (error) {
      setActionError(getHttpErrorMessage(error, 'Não foi possível aprovar a guia.'))
    }
  }

  const handleNegar = async () => {
    setActionError(null)

    const ok = await confirmar({
      titulo: 'Negar guia',
      descricao: `Guia #${guia.id}${guia.numero_guia ? ` (${guia.numero_guia})` : ''} será marcada como negada. Essa ação não fica pendente de revisão. Confirma?`,
      confirmarTexto: 'Negar guia',
    })

    if (!ok) {
      return
    }

    try {
      await negarGuia.mutateAsync(guia.id)
    } catch (error) {
      setActionError(getHttpErrorMessage(error, 'Não foi possível negar a guia.'))
    }
  }

  if (!podeAprovar && !podeNegar) {
    return null
  }

  return (
    <div className="space-y-3" data-testid={`guia-status-actions-${guia.id}`}>
      <div className="flex flex-nowrap items-center gap-2">
        {podeAprovar ? (
          <button
            type="button"
            className="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-meta font-semibold text-emerald-100 transition hover:bg-emerald-400/20 disabled:opacity-50"
            onClick={handleAprovar}
            disabled={aprovarGuia.isPending || negarGuia.isPending}
            data-testid={`guia-aprovar-${guia.id}`}
          >
            Aprovar
          </button>
        ) : null}
        {podeNegar ? (
          <button
            type="button"
            className="rounded-full border border-rose-400/30 bg-rose-400/10 px-3 py-1.5 text-meta font-semibold text-rose-100 transition hover:bg-rose-400/20 disabled:opacity-50"
            onClick={handleNegar}
            disabled={aprovarGuia.isPending || negarGuia.isPending}
            data-testid={`guia-negar-${guia.id}`}
          >
            Negar
          </button>
        ) : null}
      </div>

      {actionError ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
          {actionError}
        </p>
      ) : null}
    </div>
  )
}

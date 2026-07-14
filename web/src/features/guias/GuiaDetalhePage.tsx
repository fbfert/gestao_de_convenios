import { Link, useParams } from 'react-router-dom'
import { GuiaDetalheResumo } from './GuiaDetalheResumo'
import { GuiaStatusActions } from './GuiaStatusActions'
import { getHttpErrorMessage, useGuia } from './useGuias'

export function GuiaDetalhePage() {
  const { id } = useParams()
  const guiaId = id && /^\d+$/.test(id) ? Number(id) : null
  const guiaQuery = useGuia(guiaId)

  if (guiaId === null) {
    return (
      <div className="rounded-3xl border border-rose-400/20 bg-rose-500/10 p-6 text-rose-100">
        Identificador de guia inválido.
      </div>
    )
  }

  if (guiaQuery.isLoading) {
    return <div className="rounded-3xl border border-white/10 bg-white/5 p-6 text-slate-300">Carregando guia...</div>
  }

  if (guiaQuery.isError || !guiaQuery.data) {
    return (
      <div className="space-y-4 rounded-3xl border border-rose-400/20 bg-rose-500/10 p-6 text-rose-100">
        <p>Não foi possível encontrar esta guia.</p>
        <p className="text-sm text-rose-100/80">
          {getHttpErrorMessage(guiaQuery.error, 'Confira o número informado e tente novamente.')}
        </p>
        <Link to="/guias" className="inline-flex rounded-2xl border border-rose-200/30 px-4 py-2 text-sm font-semibold">
          Voltar para guias
        </Link>
      </div>
    )
  }

  const guia = guiaQuery.data

  return (
    <div className="space-y-8" data-testid="guia-detalhe-page">
      <section className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <Link to="/guias" className="text-sm font-semibold text-cyan-200 transition hover:text-cyan-100">
            ← Voltar para guias
          </Link>
        </div>
      </section>

      <GuiaDetalheResumo guia={guia} />

      <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <h3 className="text-lg font-semibold text-white">Ações da guia</h3>
        <p className="mt-1 text-sm text-slate-300">Finalize com senha e validade ou negue enquanto estiver em análise.</p>
        <div className="mt-4"><GuiaStatusActions guia={guia} /></div>
      </section>
    </div>
  )
}

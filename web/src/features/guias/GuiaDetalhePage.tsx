import { Link, useParams } from 'react-router-dom'
import { GuiaDetalheResumo } from './GuiaDetalheResumo'
import { GuiaStatusActions } from './GuiaStatusActions'
import { getHttpErrorMessage, useBuscarSenhaValidadeGuiaUnimed, useConsultarGuiaUnimed, useGuia } from './useGuias'

export function GuiaDetalhePage() {
  const { id } = useParams()
  const guiaId = id && /^\d+$/.test(id) ? Number(id) : null
  const guiaQuery = useGuia(guiaId)
  const consultarGuiaUnimed = useConsultarGuiaUnimed()
  const buscarSenhaValidadeUnimed = useBuscarSenhaValidadeGuiaUnimed()

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
  const isUnimed = guia.convenio?.connector_driver === 'unimed_rda'
  const canConsultarUnimed = isUnimed && Boolean(guia.numero_guia) && !['approved', 'denied', 'canceled', 'finalized', 'needs_verification'].includes(guia.status)
  const canBuscarSenhaValidade = isUnimed && guia.status === 'approved' && Boolean(guia.numero_guia) && (!guia.senha || !guia.validade_senha)

  const handleConsultarUnimed = async () => {
    try {
      await consultarGuiaUnimed.mutateAsync(guia.id)
    } catch (error) {
      window.alert(getHttpErrorMessage(error, 'Não foi possível consultar a Unimed.'))
    }
  }

  const handleBuscarSenhaValidade = async () => {
    try {
      await buscarSenhaValidadeUnimed.mutateAsync(guia.id)
    } catch (error) {
      window.alert(getHttpErrorMessage(error, 'Não foi possível buscar senha e validade na Unimed.'))
    }
  }

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
        <div className="mt-4 flex flex-wrap gap-2">
          <GuiaStatusActions guia={guia} />
          <button
            type="button"
            onClick={() => void handleConsultarUnimed()}
            disabled={!canConsultarUnimed || consultarGuiaUnimed.isPending}
            className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
            data-testid="guia-consultar-unimed"
          >
            {consultarGuiaUnimed.isPending ? 'Consultando...' : 'Consultar Unimed'}
          </button>
          <button
            type="button"
            onClick={() => void handleBuscarSenhaValidade()}
            disabled={!canBuscarSenhaValidade || buscarSenhaValidadeUnimed.isPending}
            className="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-xs font-semibold text-emerald-100 transition hover:bg-emerald-400/20 disabled:cursor-not-allowed disabled:opacity-50"
            data-testid="guia-buscar-senha-validade-unimed"
          >
            {buscarSenhaValidadeUnimed.isPending ? 'Buscando...' : 'Buscar senha/validade'}
          </button>
        </div>
      </section>
    </div>
  )
}

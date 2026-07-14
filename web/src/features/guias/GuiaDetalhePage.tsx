import { Link, useParams } from 'react-router-dom'
import { translateStatus } from '../../lib/statusLabels'
import { GuiaStatusActions } from './GuiaStatusActions'
import { isSenhaVencendo } from './senhaValidade'
import { getHttpErrorMessage, useGuia } from './useGuias'

function DetailItem({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
      <p className="text-xs uppercase tracking-[0.25em] text-slate-400">{label}</p>
      <div className="mt-2 text-sm font-medium text-white">{children}</div>
    </div>
  )
}

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
  const validadeVencendo = isSenhaVencendo(guia.validade_senha)

  return (
    <div className="space-y-8" data-testid="guia-detalhe-page">
      <section className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <Link to="/guias" className="text-sm font-semibold text-cyan-200 transition hover:text-cyan-100">
            ← Voltar para guias
          </Link>
          <p className="mt-5 text-xs uppercase tracking-[0.3em] text-cyan-300/80">Guia</p>
          <h2 className="mt-2 text-3xl font-semibold text-white">{guia.numero_guia}</h2>
          <p className="mt-2 text-sm text-slate-300">{guia.tipo_terapia}</p>
        </div>
        <span className="inline-flex w-fit rounded-full border border-cyan-400/20 bg-cyan-400/15 px-3 py-1 text-xs font-semibold text-cyan-100">
          {translateStatus('guias', guia.status)}
        </span>
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <DetailItem label="Paciente">
          <p>{guia.paciente?.nome ?? guia.paciente_id}</p>
          <p className="mt-1 text-xs font-normal text-slate-300">Carteirinha: {guia.paciente?.carteirinha ?? '-'}</p>
        </DetailItem>
        <DetailItem label="Convênio">{guia.convenio?.nome ?? guia.convenio_id}</DetailItem>
        <DetailItem label="Profissional">{guia.profissional?.nome ?? guia.profissional_id}</DetailItem>
        <DetailItem label="Especialidade">{guia.especialidade?.nome ?? guia.especialidade_id}</DetailItem>
        <DetailItem label="Solicitação">{guia.data_solicitacao}</DetailItem>
        <DetailItem label="Finalização">{guia.data_finalizacao ?? '-'}</DetailItem>
        <DetailItem label="Senha">{guia.senha ?? '-'}</DetailItem>
        <DetailItem label="Validade da senha">
          <span className={validadeVencendo ? 'text-amber-200' : undefined}>{guia.validade_senha ?? '-'}</span>
          {validadeVencendo ? <p className="mt-1 text-xs font-normal text-amber-200">Vencendo em até 7 dias</p> : null}
        </DetailItem>
      </section>

      <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <h3 className="text-lg font-semibold text-white">Ações da guia</h3>
        <p className="mt-1 text-sm text-slate-300">Finalize com senha e validade ou negue enquanto estiver em análise.</p>
        <div className="mt-4"><GuiaStatusActions guia={guia} /></div>
      </section>

      <section className="grid gap-4 xl:grid-cols-2">
        <article className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <div className="flex items-center justify-between gap-4">
            <h3 className="text-lg font-semibold text-white">Antecipações</h3>
            <Link to="/antecipacoes" className="text-sm font-semibold text-cyan-200 hover:text-cyan-100">Ver antecipações</Link>
          </div>
          {guia.antecipacoes?.length ? (
            <div className="mt-4 space-y-3">
              {guia.antecipacoes.map((antecipacao) => (
                <div key={antecipacao.id} className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-200">
                  <p className="font-semibold text-white">Antecipação #{antecipacao.id}</p>
                  <p className="mt-1">Cota: {antecipacao.qtd_utilizada}/{antecipacao.qtd_autorizada}</p>
                </div>
              ))}
            </div>
          ) : <p className="mt-4 text-sm text-slate-300">Nenhuma antecipação vinculada.</p>}
        </article>

        <article className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <div className="flex items-center justify-between gap-4">
            <h3 className="text-lg font-semibold text-white">Conciliação</h3>
            <Link to="/conciliacao" className="text-sm font-semibold text-cyan-200 hover:text-cyan-100">Ver conciliação</Link>
          </div>
          {guia.conciliacoes?.length ? (
            <div className="mt-4 space-y-3">
              {guia.conciliacoes.map((conciliacao) => (
                <div key={conciliacao.id} className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-200">
                  <p className="font-semibold text-white">Conciliação #{conciliacao.id}</p>
                  <p className="mt-1">Status: {translateStatus('conciliacoes', conciliacao.status)}</p>
                </div>
              ))}
            </div>
          ) : <p className="mt-4 text-sm text-slate-300">Nenhuma conciliação gerada.</p>}
        </article>
      </section>
    </div>
  )
}

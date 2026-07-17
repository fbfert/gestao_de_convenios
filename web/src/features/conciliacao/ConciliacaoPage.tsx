import { useMemo, useState, type FormEvent } from 'react'
import { translateStatus } from '../../lib/statusLabels'
import { Select } from '../../components/ui/Select'
import { useConvenios, useEspecialidades, useProfissionais } from '../../lib/queries/useReferenceData'
import {
  getHttpErrorMessage,
  useConciliacoes,
  useMarcarConferidoConciliacao,
  useMarcarPagoConciliacao,
} from './useConciliacoes'
import type { ConciliacaoFilters } from './types'

const defaultFilters: ConciliacaoFilters = {
  convenio_id: '',
  especialidade_id: '',
  profissional_id: '',
  status: '',
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function statusTone(status: string) {
  switch (status) {
    case 'reviewed':
      return 'bg-amber-400/15 text-amber-100 border-amber-400/20'
    case 'paid':
      return 'bg-emerald-400/15 text-emerald-100 border-emerald-400/20'
    default:
      return 'bg-cyan-400/15 text-cyan-100 border-cyan-400/20'
  }
}

function formatCurrency(value: string) {
  const numeric = Number(value)
  if (Number.isNaN(numeric)) {
    return value
  }

  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(numeric)
}

function formatPercent(value: string) {
  const numeric = Number(value)
  if (Number.isNaN(numeric)) {
    return value
  }

  return `${new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numeric)}%`
}

function joinUnique(values: Array<string | null | undefined>) {
  return Array.from(new Set(values.filter((value): value is string => Boolean(value?.trim())))).join(
    ', ',
  )
}

export function ConciliacaoPage() {
  const [filters, setFilters] = useState(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(defaultFilters)
  const [page, setPage] = useState(1)
  const [actionError, setActionError] = useState<string | null>(null)

  const conveniosQuery = useConvenios()
  const especialidadesQuery = useEspecialidades()
  const profissionaisQuery = useProfissionais()
  const conciliacoesQuery = useConciliacoes(filters, page)
  const marcarConferido = useMarcarConferidoConciliacao()
  const marcarPago = useMarcarPagoConciliacao()

  const convenios = useMemo(() => conveniosQuery.data ?? [], [conveniosQuery.data])
  const especialidades = useMemo(() => especialidadesQuery.data ?? [], [especialidadesQuery.data])
  const profissionais = useMemo(() => profissionaisQuery.data ?? [], [profissionaisQuery.data])
  const conciliacoes = conciliacoesQuery.data?.data ?? []
  const totalPages = conciliacoesQuery.data?.meta?.last_page ?? 1

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setPage(1)
    setFilters(draftFilters)
  }

  const handleConferir = async (id: number) => {
    setActionError(null)

    try {
      await marcarConferido.mutateAsync(id)
    } catch (error) {
      setActionError(getHttpErrorMessage(error, 'Não foi possível marcar a conciliação como conferida.'))
    }
  }

  const handlePagar = async (id: number) => {
    setActionError(null)

    try {
      await marcarPago.mutateAsync(id)
    } catch (error) {
      setActionError(getHttpErrorMessage(error, 'Não foi possível marcar a conciliação como paga.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="conciliacao-page">
      <section className="space-y-4">
        <div>
          <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">
            Conciliação financeira
          </p>
          <h2 className="mt-2 text-3xl font-semibold text-white">Fechamento de guias</h2>
          <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
            A lista mantém os filtros reais de convênio, especialidade, profissional e status.
            A trava visual de pagamento impede pular direto de pendente para paga.
          </p>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Total na página</p>
            <p className="mt-2 text-2xl font-semibold text-white">{conciliacoes.length}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Status ativo</p>
            <p className="mt-2 text-2xl font-semibold text-white">
              {filters.status ? translateStatus('conciliacoes', filters.status) : 'Todos'}
            </p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Convênio</p>
            <p className="mt-2 text-2xl font-semibold text-white">
              {convenios.find((item) => String(item.id) === filters.convenio_id)?.nome ?? 'Todos'}
            </p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Especialidade</p>
            <p className="mt-2 text-2xl font-semibold text-white">
              {especialidades.find((item) => String(item.id) === filters.especialidade_id)?.nome ??
                'Todas'}
            </p>
          </article>
        </div>
      </section>

      <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <form className="grid gap-3 md:grid-cols-2 xl:grid-cols-4" onSubmit={handleFilterSubmit}>
          <label className="space-y-2">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Convênio</span>
            <Select
              value={draftFilters.convenio_id}
              onChange={(event) =>
                setDraftFilters((current) => ({ ...current, convenio_id: event.target.value }))
              }
              className={selectClasses()}
              data-testid="conciliacao-filtro-convenio"
            >
              <option value="">Todos</option>
              {convenios.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.nome}
                </option>
              ))}
            </Select>
          </label>

          <label className="space-y-2">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">
              Especialidade (via guia)
            </span>
            <Select
              value={draftFilters.especialidade_id}
              onChange={(event) =>
                setDraftFilters((current) => ({ ...current, especialidade_id: event.target.value }))
              }
              className={selectClasses()}
              data-testid="conciliacao-filtro-especialidade"
            >
              <option value="">Todas</option>
              {especialidades.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.nome}
                </option>
              ))}
            </Select>
          </label>

          <label className="space-y-2">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Profissional</span>
            <Select
              value={draftFilters.profissional_id}
              onChange={(event) =>
                setDraftFilters((current) => ({ ...current, profissional_id: event.target.value }))
              }
              className={selectClasses()}
              data-testid="conciliacao-filtro-profissional"
            >
              <option value="">Todos</option>
              {profissionais.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.nome}
                </option>
              ))}
            </Select>
          </label>

          <label className="space-y-2">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Status</span>
            <Select
              value={draftFilters.status}
              onChange={(event) =>
                setDraftFilters((current) => ({ ...current, status: event.target.value }))
              }
              className={selectClasses()}
              data-testid="conciliacao-filtro-status"
            >
              <option value="">Todos</option>
              <option value="pending">Pendente</option>
              <option value="reviewed">Conferida</option>
              <option value="paid">Paga</option>
            </Select>
          </label>

          <button
            type="submit"
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10 md:col-span-2 xl:col-span-4"
          >
            Aplicar filtros
          </button>
        </form>

        {actionError ? (
          <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
            {actionError}
          </p>
        ) : null}

        {conciliacoesQuery.isLoading ? (
          <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            Carregando conciliações...
          </div>
        ) : conciliacoesQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
            Não foi possível carregar a lista.
          </div>
        ) : (
          <div className="overflow-hidden rounded-3xl border border-white/10">
            <table className="w-full border-collapse text-left text-sm">
              <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                <tr>
                  <th className="px-4 py-3">ID</th>
                  <th className="px-4 py-3">Guia</th>
                  <th className="px-4 py-3">Convênio</th>
                  <th className="px-4 py-3">Especialidade</th>
                  <th className="px-4 py-3">Profissional</th>
                  <th className="px-4 py-3">Qtd.</th>
                  <th className="px-4 py-3">Valor unit.</th>
                  <th className="px-4 py-3">Valor total</th>
                  <th className="px-4 py-3">Repasse %</th>
                  <th className="px-4 py-3">Repasse total</th>
                  <th className="px-4 py-3">Entrada</th>
                  <th className="px-4 py-3">Saída</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {conciliacoes.map((conciliacao) => (
                  <tr key={conciliacao.id} data-testid={`conciliacao-row-${conciliacao.id}`}>
                    <td className="px-4 py-4 font-medium text-white">#{conciliacao.id}</td>
                    <td className="px-4 py-4 text-slate-200">#{conciliacao.guia_id}</td>
                    <td className="px-4 py-4 text-slate-200">
                      {convenios.find((item) => item.id === conciliacao.convenio_id)?.nome ??
                        conciliacao.convenio_id}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {especialidades.find((item) => item.id === conciliacao.especialidade_id)?.nome ??
                        conciliacao.especialidade_id}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      <div className="space-y-1">
                        <p>
                          Plano:{' '}
                          {conciliacao.profissional_informado?.nome ??
                            profissionais.find((item) => item.id === conciliacao.profissional_id)
                              ?.nome ??
                            conciliacao.profissional_id}
                        </p>
                        <p className="text-xs text-slate-400">
                          Execução:{' '}
                          {joinUnique(
                            conciliacao.movimentos_financeiros
                              ?.filter((movimento) => movimento.tipo === 'saida')
                              .map((movimento) => movimento.profissional_executor?.nome ?? null) ?? [],
                          ) || 'Pendente'}
                        </p>
                      </div>
                    </td>
                    <td className="px-4 py-4 text-slate-200">{conciliacao.quantidade}</td>
                    <td className="px-4 py-4 text-slate-200">
                      {formatCurrency(conciliacao.valor_unitario)}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {formatCurrency(conciliacao.valor_total)}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {formatPercent(conciliacao.percentual_repasse_profissional)}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {formatCurrency(conciliacao.valor_repasse_total)}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {formatCurrency(conciliacao.entrada_total)}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {formatCurrency(conciliacao.saida_total)}
                    </td>
                    <td className="px-4 py-4">
                      <span
                        className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${statusTone(
                          conciliacao.status,
                        )}`}
                        data-testid={`conciliacao-status-${conciliacao.id}`}
                      >
                        {translateStatus('conciliacoes', conciliacao.status)}
                      </span>
                      {conciliacao.conferido_em ? (
                        <p className="mt-2 text-xs text-slate-400">
                          Conferida em {new Date(conciliacao.conferido_em).toLocaleDateString('pt-BR')}
                        </p>
                      ) : null}
                    </td>
                    <td className="px-4 py-4">
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          onClick={() => handleConferir(conciliacao.id)}
                          disabled={conciliacao.status !== 'pending' || marcarConferido.isPending}
                          className="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1.5 text-xs font-semibold text-amber-100 transition hover:bg-amber-400/20 disabled:opacity-50"
                          data-testid={`conciliacao-conferir-${conciliacao.id}`}
                        >
                          Marcar conferido
                        </button>
                        <button
                          type="button"
                          onClick={() => handlePagar(conciliacao.id)}
                          disabled={conciliacao.status !== 'reviewed' || marcarPago.isPending}
                          className="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-xs font-semibold text-emerald-100 transition hover:bg-emerald-400/20 disabled:opacity-50"
                          data-testid={`conciliacao-pagar-${conciliacao.id}`}
                        >
                          Marcar pago
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {conciliacoes.length === 0 ? (
                  <tr>
                    <td colSpan={14} className="px-4 py-8 text-center text-slate-300">
                      Nenhuma conciliação encontrada.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        )}

        <div className="flex items-center justify-between gap-3">
          <button
            type="button"
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10 disabled:opacity-50"
            onClick={() => setPage((current) => Math.max(1, current - 1))}
            disabled={page <= 1 || conciliacoesQuery.isFetching}
          >
            Anterior
          </button>

          <p className="text-sm text-slate-300">
            Página {page} de {totalPages}
          </p>

          <button
            type="button"
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10 disabled:opacity-50"
            onClick={() => setPage((current) => Math.min(totalPages, current + 1))}
            disabled={page >= totalPages || conciliacoesQuery.isFetching}
          >
            Próxima
          </button>
        </div>
      </section>
    </div>
  )
}

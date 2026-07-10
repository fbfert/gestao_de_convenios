import { useMemo, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { translateStatus } from '../../lib/statusLabels'
import { useConvenios, usePacientes } from '../../lib/queries/useReferenceData'
import { useAntecipacoes } from './useAntecipacoes'
import type { AntecipacaoFilters } from './types'

const defaultFilters: AntecipacaoFilters = {
  status: '',
  paciente_id: '',
  convenio_id: '',
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function statusTone(status: string) {
  return status === 'closed'
    ? 'bg-emerald-400/15 text-emerald-100 border-emerald-400/20'
    : 'bg-cyan-400/15 text-cyan-100 border-cyan-400/20'
}

export function AntecipacoesPage() {
  const [filters, setFilters] = useState(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(defaultFilters)
  const [page, setPage] = useState(1)

  const conveniosQuery = useConvenios()
  const pacientesQuery = usePacientes()
  const antecipacoesQuery = useAntecipacoes(filters, page)

  const convenios = useMemo(() => conveniosQuery.data ?? [], [conveniosQuery.data])
  const pacientes = useMemo(() => pacientesQuery.data ?? [], [pacientesQuery.data])
  const antecipacoes = antecipacoesQuery.data?.data ?? []
  const totalPages = antecipacoesQuery.data?.meta?.last_page ?? 1

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setPage(1)
    setFilters(draftFilters)
  }

  return (
    <div className="space-y-8" data-testid="antecipacoes-page">
      <section>
        <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Antecipações</p>
        <h2 className="mt-2 text-3xl font-semibold text-white">Painel de agendamento</h2>
        <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
          Aqui a equipe acompanha quanto já foi utilizado versus o limite autorizado.
        </p>
      </section>

      <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <form className="grid gap-3 md:grid-cols-3 xl:grid-cols-4" onSubmit={handleFilterSubmit}>
          <label className="space-y-2">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Status</span>
            <select
              value={draftFilters.status}
              onChange={(event) =>
                setDraftFilters((current) => ({ ...current, status: event.target.value }))
              }
              className={selectClasses()}
              data-testid="antecipacao-filtro-status"
            >
              <option value="">Todos</option>
              <option value="open">Aberta</option>
              <option value="closed">Fechada</option>
            </select>
          </label>

          <label className="space-y-2">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Paciente</span>
            <select
              value={draftFilters.paciente_id}
              onChange={(event) =>
                setDraftFilters((current) => ({ ...current, paciente_id: event.target.value }))
              }
              className={selectClasses()}
              data-testid="antecipacao-filtro-paciente"
            >
              <option value="">Todos</option>
              {pacientes.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.nome}
                </option>
              ))}
            </select>
          </label>

          <label className="space-y-2">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Convênio</span>
            <select
              value={draftFilters.convenio_id}
              onChange={(event) =>
                setDraftFilters((current) => ({ ...current, convenio_id: event.target.value }))
              }
              className={selectClasses()}
              data-testid="antecipacao-filtro-convenio"
            >
              <option value="">Todos</option>
              {convenios.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.nome}
                </option>
              ))}
            </select>
          </label>

          <button
            type="submit"
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
          >
            Aplicar
          </button>
        </form>

        {antecipacoesQuery.isLoading ? (
          <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            Carregando antecipações...
          </div>
        ) : antecipacoesQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
            Não foi possível carregar a lista.
          </div>
        ) : (
          <div className="overflow-hidden rounded-3xl border border-white/10">
            <table className="w-full border-collapse text-left text-sm">
              <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                <tr>
                  <th className="px-4 py-3">ID</th>
                  <th className="px-4 py-3">Paciente</th>
                  <th className="px-4 py-3">Convênio</th>
                  <th className="px-4 py-3">Cota</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {antecipacoes.map((antecipacao) => (
                  <tr key={antecipacao.id} data-testid={`antecipacao-row-${antecipacao.id}`}>
                    <td className="px-4 py-4 font-medium text-white">#{antecipacao.id}</td>
                    <td className="px-4 py-4 text-slate-200">
                      {pacientes.find((item) => item.id === antecipacao.paciente_id)?.nome ??
                        antecipacao.paciente_id}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      {convenios.find((item) => item.id === antecipacao.convenio_id)?.nome ??
                        antecipacao.convenio_id}
                    </td>
                    <td className="px-4 py-4 text-slate-200">
                      <div className="space-y-2">
                        <div className="flex items-center justify-between text-xs uppercase tracking-[0.25em] text-slate-400">
                          <span>{antecipacao.qtd_utilizada}</span>
                          <span>{antecipacao.qtd_autorizada}</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-white/10">
                          <div
                            className="h-full rounded-full bg-cyan-300"
                            style={{
                              width: `${Math.min(
                                100,
                                (antecipacao.qtd_utilizada / Math.max(1, antecipacao.qtd_autorizada)) *
                                  100,
                              )}%`,
                            }}
                          />
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-4">
                      <span
                        className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${statusTone(
                          antecipacao.status,
                        )}`}
                        data-testid={`antecipacao-status-${antecipacao.id}`}
                      >
                          {translateStatus('antecipacoes', antecipacao.status)}
                      </span>
                    </td>
                    <td className="px-4 py-4">
                      <Link
                        to={`/lancamentos?antecipacao_id=${antecipacao.id}`}
                        className="inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/10"
                        data-testid={`antecipacao-lancar-${antecipacao.id}`}
                      >
                        Registrar
                      </Link>
                    </td>
                  </tr>
                ))}
                {antecipacoes.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-slate-300">
                      Nenhuma antecipação encontrada.
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
            disabled={page <= 1 || antecipacoesQuery.isFetching}
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
            disabled={page >= totalPages || antecipacoesQuery.isFetching}
          >
            Próxima
          </button>
        </div>
      </section>
    </div>
  )
}

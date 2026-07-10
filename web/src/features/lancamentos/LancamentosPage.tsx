import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { translateStatus } from '../../lib/statusLabels'
import { useProfissionais } from '../../lib/queries/useReferenceData'
import { useAntecipacoes } from '../antecipacoes/useAntecipacoes'
import { useCriarLancamento, useLancamentos } from './useLancamentos'
import type { LancamentoFilters, LancamentoForm } from './types'
import { getHttpErrorMessage } from './useLancamentos'

const defaultFilters: LancamentoFilters = {
  profissional_id: '',
  data_sessao: '',
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function LancamentosPage() {
  const [searchParams] = useSearchParams()
  const initialAntecipacaoId = searchParams.get('antecipacao_id') ?? ''

  const [filters, setFilters] = useState(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(defaultFilters)
  const [page, setPage] = useState(1)
  const [form, setForm] = useState<LancamentoForm>({
    antecipacao_id: initialAntecipacaoId,
    profissional_id: '',
    data_sessao: new Date().toISOString().slice(0, 10),
  })
  const [formError, setFormError] = useState<string | null>(null)

  const profissionaisQuery = useProfissionais()
  const antecipacoesQuery = useAntecipacoes({ status: '', paciente_id: '', convenio_id: '' }, 1)
  const lancamentosQuery = useLancamentos(filters, page)
  const criarLancamento = useCriarLancamento()

  const profissionais = useMemo(() => profissionaisQuery.data ?? [], [profissionaisQuery.data])
  const antecipacoes = useMemo(() => antecipacoesQuery.data?.data ?? [], [antecipacoesQuery.data])
  const lancamentos = lancamentosQuery.data?.data ?? []
  const totalPages = lancamentosQuery.data?.meta?.last_page ?? 1

  useEffect(() => {
    if (antecipacoes.length === 0) {
      return
    }

    setForm((current) => ({
      ...current,
      antecipacao_id:
        current.antecipacao_id ||
        initialAntecipacaoId ||
        String(antecipacoes[0].id),
    }))
  }, [antecipacoes, initialAntecipacaoId])

  useEffect(() => {
    if (profissionais.length === 0) {
      return
    }

    setForm((current) => ({
      ...current,
      profissional_id:
        current.profissional_id ||
        String(profissionais[0].id),
    }))
  }, [profissionais])

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setPage(1)
    setFilters(draftFilters)
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      await criarLancamento.mutateAsync(form)
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível registrar o lançamento.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="lancamentos-page">
      <section>
        <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Lançamentos</p>
        <h2 className="mt-2 text-3xl font-semibold text-white">Registro de sessão</h2>
        <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
          Cada registro consome cota da antecipação escolhida e reflete de volta no
          painel de antecipações.
        </p>
      </section>

      <section className="grid gap-6 xl:grid-cols-[1.05fr_1.4fr]">
        <form
          onSubmit={handleSubmit}
          className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6"
        >
          <div>
            <h3 className="text-lg font-semibold text-white">Novo lançamento</h3>
            <p className="text-sm text-slate-300">
              Selecione uma antecipação, o profissional e a data da sessão.
            </p>
          </div>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Antecipação</span>
            <select
              value={form.antecipacao_id}
              onChange={(event) =>
                setForm((current) => ({ ...current, antecipacao_id: event.target.value }))
              }
              className={selectClasses()}
              data-testid="lancamento-antecipacao"
            >
              {antecipacoes.map((antecipacao) => (
                <option key={antecipacao.id} value={antecipacao.id}>
                  #{antecipacao.id} · Paciente {antecipacao.paciente_id} · {antecipacao.status}
                </option>
              ))}
            </select>
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Profissional</span>
            <select
              value={form.profissional_id}
              onChange={(event) =>
                setForm((current) => ({ ...current, profissional_id: event.target.value }))
              }
              className={selectClasses()}
              data-testid="lancamento-profissional"
            >
              {profissionais.map((profissional) => (
                <option key={profissional.id} value={profissional.id}>
                  {profissional.nome}
                </option>
              ))}
            </select>
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Data da sessão</span>
            <input
              type="date"
              value={form.data_sessao}
              onChange={(event) =>
                setForm((current) => ({ ...current, data_sessao: event.target.value }))
              }
              className={selectClasses()}
              data-testid="lancamento-data-sessao"
            />
          </label>

          {formError ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
              {formError}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={criarLancamento.isPending}
            className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
            data-testid="lancamento-submit"
          >
            {criarLancamento.isPending ? 'Salvando...' : 'Registrar lançamento'}
          </button>
        </form>

        <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <h3 className="text-lg font-semibold text-white">Filtros e lista</h3>
              <p className="text-sm text-slate-300">
                A lista acompanha profissional e data da sessão.
              </p>
            </div>

            <form className="grid gap-3 md:grid-cols-3 xl:grid-cols-3" onSubmit={handleFilterSubmit}>
              <label className="space-y-2">
                <span className="text-xs uppercase tracking-[0.25em] text-slate-400">
                  Profissional
                </span>
                <select
                  value={draftFilters.profissional_id}
                  onChange={(event) =>
                    setDraftFilters((current) => ({ ...current, profissional_id: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-filtro-profissional"
                >
                  <option value="">Todos</option>
                  {profissionais.map((profissional) => (
                    <option key={profissional.id} value={profissional.id}>
                      {profissional.nome}
                    </option>
                  ))}
                </select>
              </label>

              <label className="space-y-2">
                <span className="text-xs uppercase tracking-[0.25em] text-slate-400">
                  Data sessão
                </span>
                <input
                  type="date"
                  value={draftFilters.data_sessao}
                  onChange={(event) =>
                    setDraftFilters((current) => ({ ...current, data_sessao: event.target.value }))
                  }
                  className={selectClasses()}
                  data-testid="lancamento-filtro-data-sessao"
                />
              </label>

              <button
                type="submit"
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
              >
                Aplicar
              </button>
            </form>
          </div>

          {lancamentosQuery.isLoading ? (
            <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
              Carregando lançamentos...
            </div>
          ) : lancamentosQuery.isError ? (
            <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
              Não foi possível carregar a lista.
            </div>
          ) : (
            <div className="overflow-hidden rounded-3xl border border-white/10">
              <table className="w-full border-collapse text-left text-sm">
                <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                  <tr>
                    <th className="px-4 py-3">ID</th>
                    <th className="px-4 py-3">Antecipação</th>
                    <th className="px-4 py-3">Profissional</th>
                    <th className="px-4 py-3">Data</th>
                    <th className="px-4 py-3">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/10 bg-slate-950/30">
                  {lancamentos.map((lancamento) => (
                    <tr key={lancamento.id} data-testid={`lancamento-row-${lancamento.id}`}>
                      <td className="px-4 py-4 font-medium text-white">#{lancamento.id}</td>
                      <td className="px-4 py-4 text-slate-200">#{lancamento.antecipacao_id}</td>
                      <td className="px-4 py-4 text-slate-200">
                        {profissionais.find((item) => item.id === lancamento.profissional_id)?.nome ??
                          lancamento.profissional_id}
                      </td>
                      <td className="px-4 py-4 text-slate-200">{lancamento.data_sessao}</td>
                      <td className="px-4 py-4 text-slate-200">
                        {translateStatus('lancamentos', lancamento.status)}
                      </td>
                    </tr>
                  ))}
                  {lancamentos.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="px-4 py-8 text-center text-slate-300">
                        Nenhum lançamento encontrado.
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
              disabled={page <= 1 || lancamentosQuery.isFetching}
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
              disabled={page >= totalPages || lancamentosQuery.isFetching}
            >
              Próxima
            </button>
          </div>
        </section>
      </section>
    </div>
  )
}

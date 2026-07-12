import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { translateStatus } from '../../lib/statusLabels'
import {
  getHttpErrorMessage,
  useAprovarSolicitacao,
  useCriarSolicitacao,
  useNegarSolicitacao,
  useSolicitacoes,
} from './useSolicitacoes'
import type { SolicitacaoFilters, SolicitacaoForm } from './types'
import {
  useConvenios,
  useEspecialidades,
  useMedicos,
  usePacientes,
  useProfissionais,
} from '../../lib/queries/useReferenceData'

const emptyArray: never[] = []

const defaultFilters: SolicitacaoFilters = {
  status: '',
  convenio_id: '',
}

const emptyForm: SolicitacaoForm = {
  paciente_id: '',
  profissional_id: '',
  especialidade_id: '',
  convenio_id: '',
  medico_id: '',
  solicitado_em: new Date().toISOString().slice(0, 10),
  observacoes: '',
}

function statusTone(status: string) {
  switch (status) {
    case 'approved':
      return 'bg-emerald-400/15 text-emerald-100 border-emerald-400/20'
    case 'denied':
      return 'bg-rose-400/15 text-rose-100 border-rose-400/20'
    default:
      return 'bg-cyan-400/15 text-cyan-100 border-cyan-400/20'
  }
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function SolicitacoesPage() {
  const [filters, setFilters] = useState(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(defaultFilters)
  const [page, setPage] = useState(1)
  const [form, setForm] = useState<SolicitacaoForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)

  const conveniosQuery = useConvenios()
  const especialidadesQuery = useEspecialidades()
  const medicosQuery = useMedicos()
  const pacientesQuery = usePacientes({ convenio_id: form.convenio_id })
  const profissionaisQuery = useProfissionais({ especialidade_id: form.especialidade_id })
  const solicitacoesQuery = useSolicitacoes(filters, page)
  const criarSolicitacao = useCriarSolicitacao()
  const aprovarSolicitacao = useAprovarSolicitacao()
  const negarSolicitacao = useNegarSolicitacao()

  const convenios = useMemo(() => conveniosQuery.data ?? emptyArray, [conveniosQuery.data])
  const especialidades = useMemo(
    () => especialidadesQuery.data ?? emptyArray,
    [especialidadesQuery.data],
  )
  const pacientes = useMemo(() => pacientesQuery.data ?? emptyArray, [pacientesQuery.data])
  const profissionais = useMemo(
    () => profissionaisQuery.data ?? emptyArray,
    [profissionaisQuery.data],
  )
  const medicos = useMemo(() => medicosQuery.data ?? emptyArray, [medicosQuery.data])

  const formReady = convenios.length > 0 && especialidades.length > 0 && medicos.length > 0
  const formIsComplete =
    formReady &&
    pacientes.length > 0 &&
    profissionais.length > 0 &&
    form.convenio_id !== '' &&
    form.especialidade_id !== '' &&
    form.paciente_id !== '' &&
    form.profissional_id !== '' &&
    form.medico_id !== ''

  useEffect(() => {
    if (!formReady) {
      return
    }

    setForm((current) => {
      if (current.convenio_id && current.especialidade_id) {
        return current
      }

      return {
        ...current,
        convenio_id: current.convenio_id || String(convenios[0].id),
        especialidade_id: current.especialidade_id || String(especialidades[0].id),
      }
    })
  }, [convenios, especialidades, formReady])

  useEffect(() => {
    if (pacientes.length === 0) {
      return
    }

    setForm((current) => {
      const hasSelected = pacientes.some((paciente) => String(paciente.id) === current.paciente_id)
      if (hasSelected) {
        return current
      }

      return {
        ...current,
        paciente_id: String(pacientes[0].id),
      }
    })
  }, [pacientes])

  useEffect(() => {
    if (profissionais.length === 0) {
      return
    }

    setForm((current) => {
      const hasSelected = profissionais.some(
        (profissional) => String(profissional.id) === current.profissional_id,
      )
      if (hasSelected) {
        return current
      }

      return {
        ...current,
        profissional_id: String(profissionais[0].id),
      }
    })
  }, [profissionais])

  useEffect(() => {
    if (medicos.length === 0) {
      return
    }

    setForm((current) => {
      const hasSelected = medicos.some((medico) => String(medico.id) === current.medico_id)
      if (hasSelected) {
        return current
      }

      return {
        ...current,
        medico_id: String(medicos[0].id),
      }
    })
  }, [medicos])

  useEffect(() => {
    if (convenios.length === 0 || especialidades.length === 0) {
      return
    }

    setForm((current) => ({
      ...current,
      convenio_id: current.convenio_id || String(convenios[0].id),
      especialidade_id: current.especialidade_id || String(especialidades[0].id),
    }))
  }, [convenios, especialidades])

  const totalPages = solicitacoesQuery.data?.meta?.last_page ?? 1

  const currentConvenio = useMemo(
    () => convenios.find((item) => String(item.id) === form.convenio_id),
    [convenios, form.convenio_id],
  )

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setPage(1)
    setFilters(draftFilters)
  }

  const handleFormSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      await criarSolicitacao.mutateAsync(form)
      setForm((current) => ({
        ...emptyForm,
        convenio_id: current.convenio_id,
        especialidade_id: current.especialidade_id,
        paciente_id: current.paciente_id,
        profissional_id: current.profissional_id,
        medico_id: current.medico_id,
      }))
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível criar a solicitação.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="solicitacoes-page">
      <section className="space-y-4">
        <div>
          <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">
            Solicitações
          </p>
          <h2 className="mt-2 text-3xl font-semibold text-white">
            Primeiro contato do fluxo de convênios
          </h2>
          <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
            Crie, filtre e aprove solicitações usando os endpoints reais da API.
            O status sempre passa por translateStatus().
          </p>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">
              Total na página
            </p>
            <p className="mt-2 text-2xl font-semibold text-white">
              {solicitacoesQuery.data?.meta?.total ?? 0}
            </p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">
              Página atual
            </p>
            <p className="mt-2 text-2xl font-semibold text-white">{page}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">
              Status ativo
            </p>
            <p className="mt-2 text-2xl font-semibold text-white">
              {filters.status || 'Todos'}
            </p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">
              Convênio
            </p>
            <p className="mt-2 text-2xl font-semibold text-white">
              {currentConvenio?.nome ?? 'Todos'}
            </p>
          </article>
        </div>
      </section>

      <section className="grid gap-6 xl:grid-cols-[1.05fr_1.4fr]">
        <form
          onSubmit={handleFormSubmit}
          className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6"
          data-testid="solicitacao-form"
        >
          <div>
            <h3 className="text-lg font-semibold text-white">Nova solicitação</h3>
            <p className="text-sm text-slate-300">
              Todos os campos vêm da API real de referência.
            </p>
          </div>

          {!formReady ? (
            <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
              Carregando dados de referência...
            </div>
          ) : null}

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Convênio</span>
            <select
              value={form.convenio_id}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  convenio_id: event.target.value,
                  paciente_id: '',
                }))
              }
              className={selectClasses()}
              data-testid="solicitacao-convenio"
              disabled={conveniosQuery.isLoading}
            >
              <option value="" disabled>
                Selecione
              </option>
              {convenios.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.nome}
                </option>
              ))}
            </select>
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Paciente</span>
            <select
              value={form.paciente_id}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  paciente_id: event.target.value,
                }))
              }
              className={selectClasses()}
              data-testid="solicitacao-paciente"
              disabled={pacientesQuery.isLoading || pacientes.length === 0}
            >
              <option value="" disabled>
                Selecione
              </option>
              {pacientes.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.nome} · {item.carteirinha}
                  {item.convenio?.nome ? ` · ${item.convenio.nome}` : ''}
                </option>
              ))}
            </select>
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Profissional</span>
            <select
              value={form.profissional_id}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  profissional_id: event.target.value,
                }))
              }
              className={selectClasses()}
              data-testid="solicitacao-profissional"
              disabled={profissionaisQuery.isLoading || profissionais.length === 0}
            >
              <option value="" disabled>
                Selecione
              </option>
              {profissionais.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.nome}
                  {item.especialidade?.nome ? ` · ${item.especialidade.nome}` : ''}
                </option>
              ))}
            </select>
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Especialidade</span>
            <select
              value={form.especialidade_id}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  especialidade_id: event.target.value,
                  profissional_id: '',
                }))
              }
              className={selectClasses()}
              data-testid="solicitacao-especialidade"
              disabled={especialidadesQuery.isLoading}
            >
              <option value="" disabled>
                Selecione
              </option>
              {especialidades.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.nome}
                </option>
              ))}
            </select>
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Médico solicitante</span>
            <select
              value={form.medico_id}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  medico_id: event.target.value,
                }))
              }
              className={selectClasses()}
              data-testid="solicitacao-medico"
              disabled={medicosQuery.isLoading || medicos.length === 0}
            >
              <option value="" disabled>
                Selecione
              </option>
              {medicos.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.nome}
                </option>
              ))}
            </select>
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Data</span>
            <input
              type="date"
              value={form.solicitado_em}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  solicitado_em: event.target.value,
                }))
              }
              className={selectClasses()}
              data-testid="solicitacao-data"
            />
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Observações</span>
            <textarea
              value={form.observacoes}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  observacoes: event.target.value,
                }))
              }
              className={`${selectClasses()} min-h-28`}
              placeholder="Opcional"
              data-testid="solicitacao-observacoes"
            />
          </label>

          {formError ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
              {formError}
            </p>
          ) : null}

          <button
            type="submit"
            className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
            disabled={criarSolicitacao.isPending || !formIsComplete}
            data-testid="solicitacao-submit"
          >
            {criarSolicitacao.isPending ? 'Salvando...' : 'Criar solicitação'}
          </button>
        </form>

        <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <h3 className="text-lg font-semibold text-white">Filtros e lista</h3>
              <p className="text-sm text-slate-300">
                A busca por status e convênio vem direto da API.
              </p>
            </div>

            <form className="flex flex-wrap gap-3" onSubmit={handleFilterSubmit}>
              <label className="min-w-40 flex-1 space-y-2">
                <span className="text-xs uppercase tracking-[0.25em] text-slate-400">
                  Status
                </span>
                <select
                  value={draftFilters.status}
                  onChange={(event) =>
                    setDraftFilters((current) => ({
                      ...current,
                      status: event.target.value,
                    }))
                  }
                  className={selectClasses()}
                  data-testid="solicitacao-filtro-status"
                >
                  <option value="">Todos</option>
                  <option value="under_review">Em análise</option>
                  <option value="approved">Aprovada</option>
                  <option value="denied">Negada</option>
                </select>
              </label>

              <label className="min-w-40 flex-1 space-y-2">
                <span className="text-xs uppercase tracking-[0.25em] text-slate-400">
                  Convênio
                </span>
                <select
                  value={draftFilters.convenio_id}
                  onChange={(event) =>
                    setDraftFilters((current) => ({
                      ...current,
                      convenio_id: event.target.value,
                    }))
                  }
                  className={selectClasses()}
                  data-testid="solicitacao-filtro-convenio"
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
          </div>

          {solicitacoesQuery.isLoading ? (
            <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
              Carregando solicitações...
            </div>
          ) : solicitacoesQuery.isError ? (
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
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Médico</th>
                    <th className="px-4 py-3">Ações</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/10 bg-slate-950/30">
                  {(solicitacoesQuery.data?.data ?? []).map((solicitacao) => (
                    <tr key={solicitacao.id} data-testid={`solicitacao-row-${solicitacao.id}`}>
                      <td className="px-4 py-4 font-medium text-white">#{solicitacao.id}</td>
                      <td className="px-4 py-4 text-slate-200">
                        {pacientes.find((item) => item.id === solicitacao.paciente_id)?.nome ??
                          solicitacao.paciente_id}
                      </td>
                      <td className="px-4 py-4 text-slate-200">
                        {convenios.find((item) => item.id === solicitacao.convenio_id)?.nome ??
                          solicitacao.convenio_id}
                      </td>
                      <td className="px-4 py-4">
                        <span
                          className={`inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${statusTone(
                            solicitacao.status,
                          )}`}
                          data-testid={`solicitacao-status-${solicitacao.id}`}
                        >
                          {translateStatus('solicitacoes', solicitacao.status)}
                        </span>
                      </td>
                      <td className="px-4 py-4 text-slate-200">
                        {solicitacao.medico?.nome ?? solicitacao.medico_id}
                      </td>
                      <td className="px-4 py-4">
                        <div className="flex flex-wrap gap-2">
                          <button
                            type="button"
                            className="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1.5 text-xs font-semibold text-emerald-100 transition hover:bg-emerald-400/20 disabled:opacity-50"
                            onClick={() => aprovarSolicitacao.mutate(solicitacao.id)}
                            disabled={aprovarSolicitacao.isPending}
                            data-testid={`solicitacao-aprovar-${solicitacao.id}`}
                          >
                            Aprovar
                          </button>
                          <button
                            type="button"
                            className="rounded-full border border-rose-400/30 bg-rose-400/10 px-3 py-1.5 text-xs font-semibold text-rose-100 transition hover:bg-rose-400/20 disabled:opacity-50"
                            onClick={() => negarSolicitacao.mutate(solicitacao.id)}
                            disabled={negarSolicitacao.isPending}
                            data-testid={`solicitacao-negar-${solicitacao.id}`}
                          >
                            Negar
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {solicitacoesQuery.data?.data?.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-4 py-8 text-center text-slate-300">
                        Nenhuma solicitação encontrada.
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
              disabled={page <= 1 || solicitacoesQuery.isFetching}
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
              disabled={page >= totalPages || solicitacoesQuery.isFetching}
            >
              Próxima
            </button>
          </div>
        </section>
      </section>
    </div>
  )
}

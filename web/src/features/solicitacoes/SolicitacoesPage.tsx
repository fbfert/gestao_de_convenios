import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useMatch, useNavigate } from 'react-router-dom'
import { translateStatus } from '../../lib/statusLabels'
import { Select } from '../../components/ui/Select'
import {
  getHttpErrorMessage,
  useAtualizarStatusSolicitacao,
  useCriarSolicitacao,
  useSolicitacoes,
} from './useSolicitacoes'
import type { Solicitacao, SolicitacaoFilters, SolicitacaoForm, SolicitacaoStatus } from './types'
import {
  useConvenios,
  useEspecialidades,
  useMedicos,
  usePacientes,
  useProfissionais,
} from '../../lib/queries/useReferenceData'
import { SolicitacaoGuiaModal } from './SolicitacaoGuiaModal'

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

const statusActions: Array<{ status: SolicitacaoStatus; label: string; className: string }> = [
  {
    status: 'under_review',
    label: 'Em análise',
    className:
      'border-cyan-400/30 bg-cyan-400/10 text-cyan-100 hover:bg-cyan-400/20 disabled:hover:bg-cyan-400/10',
  },
  {
    status: 'approved',
    label: 'Aprovado',
    className:
      'border-emerald-400/30 bg-emerald-400/10 text-emerald-100 hover:bg-emerald-400/20 disabled:hover:bg-emerald-400/10',
  },
  {
    status: 'denied',
    label: 'Negado',
    className:
      'border-rose-400/30 bg-rose-400/10 text-rose-100 hover:bg-rose-400/20 disabled:hover:bg-rose-400/10',
  },
]

function statusTone(status: string) {
  switch (status) {
    case 'registered':
      return 'bg-cyan-400/15 text-cyan-100 border-cyan-400/20'
    case 'approved':
      return 'bg-emerald-400/15 text-emerald-100 border-emerald-400/20'
    case 'canceled':
    case 'denied':
      return 'bg-rose-400/15 text-rose-100 border-rose-400/20'
    case 'expired':
      return 'bg-amber-400/15 text-amber-100 border-amber-400/20'
    default:
      return 'bg-cyan-400/15 text-cyan-100 border-cyan-400/20'
  }
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function SolicitacoesPage() {
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/solicitacoes/nova') !== null
  const [filters, setFilters] = useState(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(defaultFilters)
  const [page, setPage] = useState(1)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedSolicitacao, setSelectedSolicitacao] = useState<Solicitacao | null>(null)
  const [form, setForm] = useState<SolicitacaoForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)

  const conveniosQuery = useConvenios()
  const especialidadesQuery = useEspecialidades()
  const medicosQuery = useMedicos()
  const pacientesQuery = usePacientes({ convenio_id: form.convenio_id })
  const profissionaisQuery = useProfissionais({ especialidade_id: form.especialidade_id })
  const solicitacoesQuery = useSolicitacoes(filters, page)
  const criarSolicitacao = useCriarSolicitacao()
  const atualizarStatusSolicitacao = useAtualizarStatusSolicitacao()

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

  const handleNew = () => {
    navigate('/solicitacoes/nova')
    setForm((current) => ({
      ...emptyForm,
      convenio_id: current.convenio_id,
      especialidade_id: current.especialidade_id,
      paciente_id: current.paciente_id,
      profissional_id: current.profissional_id,
      medico_id: current.medico_id,
    }))
    setFormError(null)
  }

  const handleCancel = () => {
    setFormError(null)
    if (isCreateRoute) {
      navigate('/solicitacoes')
      return
    }

    setIsFormOpen(false)
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
      if (isCreateRoute) {
        navigate('/solicitacoes')
      } else {
        setIsFormOpen(false)
      }
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível criar a solicitação.'))
    }
  }

  const handleStatusChange = async (solicitacao: Solicitacao, status: SolicitacaoStatus) => {
    if (solicitacao.status === status) {
      return
    }

    const statusLabel = translateStatus('solicitacoes', status)
    const confirmed = window.confirm(
      `Confirmar alteração da solicitação #${solicitacao.id} para ${statusLabel}?`,
    )

    if (!confirmed) {
      return
    }

    try {
      await atualizarStatusSolicitacao.mutateAsync({ id: solicitacao.id, status })
    } catch (error) {
      window.alert(getHttpErrorMessage(error, 'Não foi possível alterar o status da solicitação.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="solicitacoes-page">
      {!isCreateRoute ? (
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Solicitações</p>
            <h2 className="mt-2 text-3xl font-semibold text-white">
              Primeiro contato do fluxo de convênios
            </h2>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
              Crie, filtre e aprove solicitações usando os endpoints reais da API. O status sempre
              passa por translateStatus().
            </p>
          </div>

          <div className="flex flex-wrap gap-3">
            <button
              type="button"
              onClick={() => navigate('/solicitacoes/ler-pedido-medico')}
              className="rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-3 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
              data-testid="solicitacao-ler-pedido-medico"
            >
              Ler pedido médico
            </button>
            <button
              type="button"
              onClick={handleNew}
              className="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
              data-testid="solicitacao-novo"
            >
              Novo
            </button>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Total na página</p>
            <p className="mt-2 text-2xl font-semibold text-white">{solicitacoesQuery.data?.meta?.total ?? 0}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Página atual</p>
            <p className="mt-2 text-2xl font-semibold text-white">{page}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Status ativo</p>
            <p className="mt-2 text-2xl font-semibold text-white">{filters.status || 'Todos'}</p>
          </article>
          <article className="rounded-3xl border border-white/10 bg-slate-950/40 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Convênio</p>
            <p className="mt-2 text-2xl font-semibold text-white">{currentConvenio?.nome ?? 'Todos'}</p>
          </article>
        </div>
      </section>
      ) : null}

      {isFormOpen || isCreateRoute ? (
        <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <form onSubmit={handleFormSubmit} className="space-y-4" data-testid="solicitacao-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-white">Nova solicitação</h3>
                <p className="text-sm text-slate-300">Todos os campos vêm da API real de referência.</p>
              </div>
              <button
                type="button"
                onClick={handleCancel}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                data-testid="solicitacao-fechar"
              >
                Fechar
              </button>
            </div>

            {!formReady ? (
              <div className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                Carregando dados de referência...
              </div>
            ) : null}

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Convênio</span>
              <Select
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
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Paciente</span>
              <Select
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
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Profissional executante</span>
              <Select
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
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Especialidade</span>
              <Select
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
              </Select>
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Médico solicitante</span>
              <Select
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
              </Select>
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
        </section>
      ) : null}

      {!isCreateRoute ? (
      <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h3 className="text-lg font-semibold text-white">Filtros e lista</h3>
            <p className="text-sm text-slate-300">A busca por status e convênio vem direto da API.</p>
          </div>

          <form className="flex flex-wrap gap-3" onSubmit={handleFilterSubmit}>
            <label className="min-w-40 flex-1 space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Status</span>
              <Select
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
                <option value="registered">Cadastrado</option>
                <option value="under_review">Em análise</option>
                <option value="approved">Aprovado</option>
                <option value="canceled">Cancelado</option>
                <option value="denied">Negado</option>
                <option value="expired">Vencido</option>
              </Select>
            </label>

            <label className="min-w-40 flex-1 space-y-2">
              <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Convênio</span>
              <Select
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
              </Select>
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
                  <th className="px-4 py-3">Médico solicitante</th>
                  <th className="px-4 py-3">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {(solicitacoesQuery.data?.data ?? []).map((solicitacao) => (
                  <tr key={solicitacao.id} data-testid={`solicitacao-row-${solicitacao.id}`}>
                    <td className="px-4 py-4 font-medium text-white">#{solicitacao.id}</td>
                    <td className="px-4 py-4 text-slate-200">
                      <button
                        type="button"
                        className="text-left font-medium text-cyan-100 underline decoration-cyan-400/40 underline-offset-4 transition hover:text-cyan-50"
                        onClick={() => setSelectedSolicitacao(solicitacao)}
                        data-testid={`solicitacao-paciente-${solicitacao.id}`}
                      >
                        {solicitacao.paciente?.nome ??
                          pacientes.find((item) => item.id === solicitacao.paciente_id)?.nome ??
                          solicitacao.paciente_id}
                      </button>
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
                        {statusActions.map((action) => (
                          <button
                            key={action.status}
                            type="button"
                            className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 ${action.className}`}
                            onClick={() => void handleStatusChange(solicitacao, action.status)}
                            disabled={
                              atualizarStatusSolicitacao.isPending ||
                              solicitacao.status === action.status
                            }
                            data-testid={`solicitacao-status-action-${action.status}-${solicitacao.id}`}
                          >
                            {action.label}
                          </button>
                        ))}
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
      ) : null}

      <SolicitacaoGuiaModal
        solicitacao={selectedSolicitacao}
        onClose={() => setSelectedSolicitacao(null)}
      />
    </div>
  )
}

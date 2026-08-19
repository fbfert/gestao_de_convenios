import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { ColunaOrdenavel } from '../../components/ui/ColunaOrdenavel'
import { useOrdenacao } from '../../lib/useOrdenacao'
import { Link, useMatch, useNavigate } from 'react-router-dom'
import { translateStatus } from '../../lib/statusLabels'
import { Select } from '../../components/ui/Select'
import {
  getHttpErrorMessage,
  useAtualizarStatusSolicitacao,
  useCriarSolicitacao,
  useEnviarItemUnimed,
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
import { formatCarteirinha } from '../../lib/carteirinha'
import { SolicitacaoGuiaModal } from './SolicitacaoGuiaModal'
import { SolicitacaoItensFields } from './SolicitacaoItensFields'
import { emptyItem, itensEstaoCompletos } from './solicitacaoItens'
import { Indicadores } from '../../components/ui/Indicadores'
import { Tooltip } from '../../components/ui/Tooltip'
import { usePode } from '../../lib/permissoes'

const emptyArray: never[] = []

const defaultFilters: SolicitacaoFilters = {
  status: '',
  convenio_id: '',
}

const emptyForm: SolicitacaoForm = {
  paciente_id: '',
  convenio_id: '',
  medico_id: '',
  cid: '',
  solicitado_em: new Date().toISOString().slice(0, 10),
  observacoes: '',
  itens: [{ ...emptyItem }],
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
  const pode = usePode()
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/solicitacoes/nova') !== null
  const [filters, setFilters] = useState(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(defaultFilters)
  const [page, setPage] = useState(1)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedSolicitacaoId, setSelectedSolicitacaoId] = useState<number | null>(null)
  const [form, setForm] = useState<SolicitacaoForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)

  const { ordenacao, ordenarPor } = useOrdenacao({
    ordenar_por: 'id',
    direcao: 'desc',
  })

  const conveniosQuery = useConvenios()
  // O código do procedimento é por convênio, então a listagem acompanha o convênio do form.
  const especialidadesQuery = useEspecialidades({ convenio_id: form.convenio_id })
  const medicosQuery = useMedicos()
  const pacientesQuery = usePacientes({ convenio_id: form.convenio_id })
  // Todos os profissionais: cada linha de item filtra pela sua própria especialidade.
  const profissionaisQuery = useProfissionais()
  const solicitacoesQuery = useSolicitacoes({ ...filters, ...ordenacao }, page)
  const criarSolicitacao = useCriarSolicitacao()
  const atualizarStatusSolicitacao = useAtualizarStatusSolicitacao()
  const enviarItemUnimed = useEnviarItemUnimed()

  const convenios = useMemo(() => conveniosQuery.data ?? emptyArray, [conveniosQuery.data])
  const especialidades = useMemo(
    () => especialidadesQuery.data ?? emptyArray,
    [especialidadesQuery.data],
  )
  const pacientes = useMemo(() => pacientesQuery.data ?? emptyArray, [pacientesQuery.data])
  const pacienteSelecionado = useMemo(
    () => pacientes.find((item) => String(item.id) === form.paciente_id),
    [pacientes, form.paciente_id],
  )
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
    form.paciente_id !== '' &&
    form.medico_id !== '' &&
    itensEstaoCompletos(form.itens)

  useEffect(() => {
    if (!formReady) {
      return
    }

    setForm((current) =>
      current.convenio_id ? current : { ...current, convenio_id: String(convenios[0].id) },
    )
  }, [convenios, formReady])

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

  const totalPages = solicitacoesQuery.data?.meta?.last_page ?? 1

  const solicitacoes = useMemo(
    () => solicitacoesQuery.data?.data ?? emptyArray,
    [solicitacoesQuery.data],
  )
  // Mantemos só o id: assim o modal enxerga o resultado de um anexo recém-enviado,
  // que chega pelo refetch da lista, em vez de um objeto congelado no clique.
  const selectedSolicitacao = useMemo(
    () => solicitacoes.find((item) => item.id === selectedSolicitacaoId) ?? null,
    [solicitacoes, selectedSolicitacaoId],
  )

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
      paciente_id: current.paciente_id,
      medico_id: current.medico_id,
      cid: current.cid,
      itens: [{ ...emptyItem }],
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
        paciente_id: current.paciente_id,
        medico_id: current.medico_id,
        cid: current.cid,
        itens: [{ ...emptyItem }],
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

  const handleEnviarItemUnimed = async (itemId: number) => {
    try {
      await enviarItemUnimed.mutateAsync(itemId)
    } catch (error) {
      window.alert(getHttpErrorMessage(error, 'Não foi possível enviar o item para a Unimed.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="solicitacoes-page">
      {!isCreateRoute ? (
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Solicitações</p>
            <h2 className="mt-2 flex items-center gap-2 text-3xl font-semibold text-white">
              Primeiro contato do fluxo de convênios
              <Tooltip rotulo="O que é uma solicitação">
                <p className="font-semibold text-white">O pedido de autorização</p>
                <p className="mt-1">
                  É o primeiro passo do atendimento: registra o pedido médico enviado ao convênio,
                  com uma ou mais especialidades. Depois de aprovada, cada especialidade vira uma
                  Guia. Cadastre o paciente antes, em Pacientes.
                </p>
              </Tooltip>
            </h2>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <button
              type="button"
              onClick={() => navigate('/solicitacoes/ler-pedido-medico')}
              className="rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-3 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
              data-testid="solicitacao-ler-pedido-medico"
            >
              Ler pedido médico
            </button>
            <Tooltip rotulo="Diferença entre os botões">
              <strong>Ler pedido médico</strong> envia o pedido escaneado para a IA preencher o
              formulário sozinha, com sugestões de paciente/médico/especialidades para conferir.
              <strong className="mt-1 block">Novo</strong> abre o mesmo formulário em branco, para
              digitar tudo manualmente.
            </Tooltip>
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

        <Indicadores
          itens={[
            { rotulo: 'Total na página', valor: solicitacoesQuery.data?.meta?.total ?? 0 },
            { rotulo: 'Página atual', valor: page },
            { rotulo: 'Status ativo', valor: filters.status || 'Todos' },
            { rotulo: 'Convênio', valor: currentConvenio?.nome ?? 'Todos' },
          ]}
        />
      </section>
      ) : null}

      {isFormOpen || isCreateRoute ? (
        <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <form onSubmit={handleFormSubmit} className="space-y-4" data-testid="solicitacao-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-white">Nova solicitação</h3>
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

              {pacienteSelecionado?.carteirinha_vencida ? (
                <span className="block rounded-2xl border border-amber-300/20 bg-amber-400/10 px-3 py-2 text-xs text-amber-100">
                  A carteirinha deste paciente está vencida
                  {pacienteSelecionado.validade_carteirinha
                    ? ` desde ${new Date(`${pacienteSelecionado.validade_carteirinha}T12:00:00`).toLocaleDateString('pt-BR')}`
                    : ''}
                  . Confirme o cartão atual antes de solicitar — a operadora costuma recusar guia
                  com carteirinha vencida.
                </span>
              ) : null}
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
                    {item.nome} · {formatCarteirinha(item.carteirinha, item.convenio?.carteirinha_blocos ?? undefined)}
                    {item.convenio?.nome ? ` · ${item.convenio.nome}` : ''}
                  </option>
                ))}
              </Select>
            </label>

            <SolicitacaoItensFields
              itens={form.itens}
              onChange={(itens) => setForm((current) => ({ ...current, itens }))}
              especialidades={especialidades}
              profissionais={profissionais}
              disabled={especialidadesQuery.isLoading || profissionaisQuery.isLoading}
            />

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
              <span className="text-sm font-medium text-slate-200">CID</span>
              <input
                value={form.cid}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    cid: event.target.value,
                  }))
                }
                className={selectClasses()}
                placeholder="Opcional"
                data-testid="solicitacao-cid"
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
                  <ColunaOrdenavel
                    titulo="ID"
                    coluna="id"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel
                    titulo="Paciente"
                    coluna="paciente"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel
                    titulo="Convênio"
                    coluna="convenio"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel titulo="Itens" />
                  <ColunaOrdenavel
                    titulo="Status"
                    coluna="status"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel
                    titulo="Médico solicitante"
                    coluna="medico"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                  />
                  <ColunaOrdenavel titulo="Ações" />
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {solicitacoes.map((solicitacao) => (
                  <tr key={solicitacao.id} data-testid={`solicitacao-row-${solicitacao.id}`}>
                    <td className="px-4 py-4 font-medium text-white">#{solicitacao.id}</td>
                    <td className="px-4 py-4 text-slate-200">
                      <button
                        type="button"
                        className="text-left font-medium text-cyan-100 underline decoration-cyan-400/40 underline-offset-4 transition hover:text-cyan-50"
                        onClick={() => setSelectedSolicitacaoId(solicitacao.id)}
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
                    <td className="px-4 py-4 text-slate-200">
                      {solicitacao.itens?.length ? (
                        <div className="space-y-1">
                          {solicitacao.itens.map((item) => {
                            const isUnimedRda =
                              solicitacao.convenio?.connector_driver === 'unimed_rda'
                            const hasActiveExecution = item.automacao_execucao_ativa !== null
                            const canSend =
                              isUnimedRda &&
                              solicitacao.status === 'approved' &&
                              !item.guia_id &&
                              !hasActiveExecution

                            return (
                              <div
                                key={item.id}
                                className="flex flex-col gap-2 rounded-2xl border border-white/10 bg-white/5 p-3"
                              >
                                <p>
                                  {item.especialidade?.nome ?? item.especialidade_id}
                                  {item.especialidade?.mapeamento_convenio?.codigo_procedimento
                                    ? ` · ${item.especialidade.mapeamento_convenio.codigo_procedimento}`
                                    : ''}{' '}
                                  ·{' '}
                                  {item.profissional?.nome ?? item.profissional_id} ·{' '}
                                  {item.quantidade}
                                </p>
                                <div className="flex flex-wrap items-center gap-2">
                                  {item.guia_id ? (
                                    <span className="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-xs font-semibold text-emerald-100">
                                      Guia #{item.guia_id}
                                    </span>
                                  ) : null}
                                  {item.automacao_execucao_ativa ? (
                                    <span className="rounded-full border border-amber-400/20 bg-amber-400/10 px-2.5 py-1 text-xs font-semibold text-amber-100">
                                      {item.automacao_execucao_ativa.status}
                                    </span>
                                  ) : null}
                                  {isUnimedRda ? (
                                    <>
                                      <button
                                        type="button"
                                        onClick={() => void handleEnviarItemUnimed(item.id)}
                                        disabled={!canSend || enviarItemUnimed.isPending}
                                        className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
                                        data-testid={`solicitacao-item-enviar-unimed-${item.id}`}
                                      >
                                        Enviar para Unimed
                                      </button>
                                      <Tooltip rotulo="Quando este botão funciona">
                                        Dispara o robô da Unimed para gerar a guia sozinho (ver
                                        Automações). Só fica ativo com a solicitação aprovada, sem
                                        guia gerada ainda e sem outra execução em andamento para
                                        este item.
                                      </Tooltip>
                                    </>
                                  ) : null}
                                </div>
                              </div>
                            )
                          })}
                        </div>
                      ) : (
                        <span className="text-slate-400">Item legado</span>
                      )}
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
                        <button
                          type="button"
                          onClick={() => setSelectedSolicitacaoId(solicitacao.id)}
                          className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/10"
                          data-testid={`solicitacao-anexos-${solicitacao.id}`}
                        >
                          Anexos ({solicitacao.documentos?.length ?? 0})
                        </button>
                        {pode('solicitacoes.manage') ? (
                          <Link
                            to={`/solicitacoes/${solicitacao.id}/editar`}
                            className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/10"
                            data-testid={`solicitacao-editar-${solicitacao.id}`}
                          >
                            Editar
                          </Link>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))}
                {solicitacoes.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-slate-300">
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
        onClose={() => setSelectedSolicitacaoId(null)}
      />
    </div>
  )
}

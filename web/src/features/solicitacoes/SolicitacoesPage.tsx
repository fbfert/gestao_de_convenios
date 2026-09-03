import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { MoreVertical } from 'lucide-react'
import { DropdownMenu } from 'radix-ui'
import { ColunaOrdenavel } from '../../components/ui/ColunaOrdenavel'
import { useOrdenacao } from '../../lib/useOrdenacao'
import { Link, useMatch, useNavigate, useSearchParams } from 'react-router-dom'
import { translateStatus } from '../../lib/statusLabels'
import { Select } from '../../components/ui/Select'
import {
  getHttpErrorMessage,
  useAtualizarStatusSolicitacao,
  useCriarSolicitacao,
  useEnviarItemUnimed,
  useSolicitacoes,
  useVerificarAndamentoItem,
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
import { AutomacaoProgressoModal } from '../automacoes/AutomacaoProgressoModal'
import { AutomacaoUnimedDesativadaModal } from '../configuracoes/AutomacaoUnimedDesativadaModal'
import { useAutomacaoUnimedGate } from '../configuracoes/useAutomacaoUnimedGate'
import { CidsCampo } from '../cids/CidsCampo'
import { SolicitacaoItensFields } from './SolicitacaoItensFields'
import { emptyItem, itensEstaoCompletos } from './solicitacaoItens'
import { Indicadores } from '../../components/ui/Indicadores'
import { Tooltip } from '../../components/ui/Tooltip'
import { usePode } from '../../lib/permissoes'
import { Botao } from '../../components/ui/Botao'
import { Badge, type BadgeProps } from '../../components/ui/Badge'

const emptyArray: never[] = []

const defaultFilters: SolicitacaoFilters = {
  status: '',
  convenio_id: '',
  paciente: '',
  profissional: '',
  medico: '',
  mostrar_historico: '',
}

const emptyForm: SolicitacaoForm = {
  paciente_id: '',
  convenio_id: '',
  medico_id: '',
  cid_ids: [],
  solicitado_em: new Date().toISOString().slice(0, 10),
  observacoes: '',
  itens: [{ ...emptyItem }],
}

// 'approved' fica de fora de propósito: agora é aprovação real da operadora,
// sincronizada automaticamente a partir da guia — não é mais uma ação manual.
const statusActions: Array<{
  status: SolicitacaoStatus
  label: string
  dotClassName: string
  textClassName: string
}> = [
  {
    status: 'under_review',
    label: 'Análise Interna',
    dotClassName: 'bg-cyan-300',
    textClassName: 'text-cyan-100',
  },
  {
    status: 'ready_for_automation',
    label: 'Pronto para Automatização',
    dotClassName: 'bg-amber-300',
    textClassName: 'text-amber-100',
  },
  {
    status: 'denied',
    label: 'Negado',
    dotClassName: 'bg-rose-300',
    textClassName: 'text-rose-100',
  },
]

function statusTone(status: string): NonNullable<BadgeProps['tone']> {
  switch (status) {
    case 'approved':
      return 'sucesso'
    case 'ready_for_automation':
      return 'alerta'
    case 'canceled':
    case 'denied':
      return 'perigo'
    case 'expired':
      return 'alerta'
    case 'historico':
      return 'neutro'
    case 'guia_gerada':
    case 'registered':
    default:
      return 'info'
  }
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function SolicitacoesPage() {
  const pode = usePode()
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/solicitacoes/nova') !== null
  const [searchParams] = useSearchParams()
  const [filters, setFilters] = useState(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(defaultFilters)
  const [page, setPage] = useState(1)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedSolicitacaoId, setSelectedSolicitacaoId] = useState<number | null>(null)
  const [progressoExecucaoId, setProgressoExecucaoId] = useState<number | null>(null)
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
  const verificarAndamentoItem = useVerificarAndamentoItem()
  const { tratarErroUnimed, modalProps: automacaoUnimedModalProps } = useAutomacaoUnimedGate()

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
    form.cid_ids.length > 0 &&
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

  // Pré-preenche a partir do "Nova Solicitação" do alerta de guia negada
  // (GuiaAlertaNegacoes), que navega pra cá com esses query params. So roda
  // uma vez ao entrar na rota — os outros useEffects de default (convenio,
  // paciente, medico) so preenchem campo vazio, entao nao competem com isso.
  useEffect(() => {
    if (!isCreateRoute) {
      return
    }

    const pacienteId = searchParams.get('paciente_id')
    const convenioId = searchParams.get('convenio_id')
    const especialidadeId = searchParams.get('especialidade_id')
    const profissionalId = searchParams.get('profissional_id')

    if (!pacienteId && !convenioId && !especialidadeId) {
      return
    }

    setForm((current) => ({
      ...current,
      paciente_id: pacienteId ?? current.paciente_id,
      convenio_id: convenioId ?? current.convenio_id,
      itens: especialidadeId
        ? [{ ...emptyItem, especialidade_id: especialidadeId, profissional_id: profissionalId ?? '' }]
        : current.itens,
    }))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isCreateRoute])

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

  // Filtro exclusivo: liga mostra só as solicitações "Histórico" (rastro de
  // guias antigas migradas sem automação por trás — ver GuiaService) e some
  // com o filtro de Status normal, pra não brigarem pelo mesmo campo.
  const toggleHistoricoBadge = () => {
    const ligado = draftFilters.mostrar_historico === '1'
    const proximo = ligado ? '' : '1'
    setPage(1)
    setFilters((current) => ({ ...current, status: '', mostrar_historico: proximo }))
    setDraftFilters((current) => ({ ...current, status: '', mostrar_historico: proximo }))
  }

  const handleNew = () => {
    navigate('/solicitacoes/nova')
    setForm((current) => ({
      ...emptyForm,
      convenio_id: current.convenio_id,
      paciente_id: current.paciente_id,
      medico_id: current.medico_id,
      cid_ids: current.cid_ids,
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
        cid_ids: current.cid_ids,
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
      const execucao = await enviarItemUnimed.mutateAsync(itemId)
      setProgressoExecucaoId(execucao.id)
    } catch (error) {
      tratarErroUnimed(error, 'Não foi possível enviar o item para a Unimed.', () => handleEnviarItemUnimed(itemId))
    }
  }

  const handleVerificarAndamentoItem = async (itemId: number) => {
    try {
      const execucao = await verificarAndamentoItem.mutateAsync(itemId)
      setProgressoExecucaoId(execucao.id)
    } catch (error) {
      tratarErroUnimed(
        error,
        'Não foi possível verificar o andamento no portal da Unimed.',
        () => handleVerificarAndamentoItem(itemId),
      )
    }
  }

  return (
    <div className="space-y-8" data-testid="solicitacoes-page">
      {!isCreateRoute ? (
      <section className="space-y-4">
        <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Solicitações</p>
            <h2 className="mt-2 flex items-center gap-2 text-display font-semibold text-white">
              Primeiro contato do fluxo de convênios
              <Tooltip rotulo="O que é uma solicitação">
                <p className="font-semibold text-white">O pedido de autorização</p>
                <p className="mt-1">
                  É o primeiro passo do atendimento: registra o pedido médico enviado ao convênio,
                  com uma ou mais especialidades. Depois de ficar pronta para automatização, cada
                  especialidade vira uma Guia. Cadastre o paciente antes, em Pacientes.
                </p>
              </Tooltip>
            </h2>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <button
              type="button"
              onClick={() => navigate('/solicitacoes/ler-pedido-medico')}
              className="inline-flex items-center justify-center rounded-2xl border border-cyan-300/30 bg-cyan-400/10 h-10 px-4 text-corpo font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
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
            {pode('solicitacoes.manage') ? (
              <Botao
                variante="secundario"
                onClick={() => navigate('/solicitacoes/importar')}
                data-testid="solicitacao-importar"
              >
                Importar planilha
              </Botao>
            ) : null}
            <Botao variante="primario" onClick={handleNew} data-testid="solicitacao-novo">
              Novo
            </Botao>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Indicadores
            itens={[
              { rotulo: 'Total na página', valor: solicitacoesQuery.data?.meta?.total ?? 0 },
              { rotulo: 'Página atual', valor: page },
              { rotulo: 'Status ativo', valor: filters.status || 'Todos' },
              { rotulo: 'Convênio', valor: currentConvenio?.nome ?? 'Todos' },
            ]}
          />

          <button
            type="button"
            onClick={toggleHistoricoBadge}
            className={[
              'inline-flex rounded-full border px-3 py-1.5 text-corpo font-semibold transition',
              filters.mostrar_historico === '1'
                ? 'border-cyan-200/50 bg-cyan-300/20 text-white'
                : 'border-cyan-200/20 bg-white/5 text-cyan-50 hover:bg-white/10',
            ].join(' ')}
            data-testid="solicitacao-filtro-historico"
          >
            {filters.mostrar_historico === '1' ? 'Histórico' : 'Mostrar Histórico'}
          </button>
        </div>
      </section>
      ) : null}

      {isFormOpen || isCreateRoute ? (
        <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <form onSubmit={handleFormSubmit} className="space-y-4" data-testid="solicitacao-form">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="text-subtitulo font-semibold text-white">Nova solicitação</h3>
              </div>
              <Botao type="button" variante="secundario" onClick={handleCancel} data-testid="solicitacao-fechar">
                Fechar
              </Botao>
            </div>

            {!formReady ? (
              <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1 text-corpo text-slate-300">
                Carregando dados de referência...
              </div>
            ) : null}

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Convênio</span>
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
                <span className="block rounded-2xl border border-amber-300/20 bg-amber-400/10 px-3 py-2 text-meta text-amber-100">
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
              <span className="text-corpo font-medium text-slate-200">Paciente</span>
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
              <span className="text-corpo font-medium text-slate-200">Médico solicitante</span>
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
              <span className="text-corpo font-medium text-slate-200">Data</span>
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
              <span className="text-corpo font-medium text-slate-200">CID</span>
              <CidsCampo
                value={form.cid_ids}
                onChange={(cidIds) => setForm((current) => ({ ...current, cid_ids: cidIds }))}
                testIdPrefix="solicitacao-cid"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Observações</span>
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
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                {formError}
              </p>
            ) : null}

            <Botao
              type="submit"
              variante="primario"
              className="w-full"
              disabled={criarSolicitacao.isPending || !formIsComplete}
              data-testid="solicitacao-submit"
            >
              {criarSolicitacao.isPending ? 'Salvando...' : 'Criar solicitação'}
            </Botao>
          </form>
        </section>
      ) : null}

      {!isCreateRoute ? (
      <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">

          <form className="flex flex-wrap gap-3" onSubmit={handleFilterSubmit}>
            <label className="min-w-40 flex-1 space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Paciente</span>
              <input
                type="text"
                value={draftFilters.paciente}
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    paciente: event.target.value,
                  }))
                }
                placeholder="Buscar por nome"
                className={selectClasses()}
                data-testid="solicitacao-filtro-paciente"
              />
            </label>

            <label className="min-w-40 flex-1 space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Profissional</span>
              <input
                type="text"
                value={draftFilters.profissional}
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    profissional: event.target.value,
                  }))
                }
                placeholder="Buscar por nome"
                className={selectClasses()}
                data-testid="solicitacao-filtro-profissional"
              />
            </label>

            <label className="min-w-40 flex-1 space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">
                Médico solicitante
              </span>
              <input
                type="text"
                value={draftFilters.medico}
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    medico: event.target.value,
                  }))
                }
                placeholder="Buscar por nome"
                className={selectClasses()}
                data-testid="solicitacao-filtro-medico"
              />
            </label>

            <label className="min-w-40 flex-1 space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Status</span>
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
                <option value="under_review">Análise Interna</option>
                <option value="ready_for_automation">Pronto para Automatização</option>
                <option value="guia_gerada">Guia Gerada</option>
                <option value="approved">Aprovado</option>
                <option value="canceled">Cancelado</option>
                <option value="denied">Negado</option>
                <option value="expired">Vencido</option>
              </Select>
            </label>

            <label className="min-w-40 flex-1 space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Convênio</span>
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

            <Botao type="submit" variante="secundario">
              Aplicar
            </Botao>
          </form>
        </div>

        {solicitacoesQuery.isLoading ? (
          <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1 text-corpo text-slate-300">
            Carregando solicitações...
          </div>
        ) : solicitacoesQuery.isError ? (
          <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-corpo text-rose-100">
            Não foi possível carregar a lista.
          </div>
        ) : (
          <div className="overflow-x-auto rounded-superficie border border-linha">
            <table className="w-full table-fixed border-collapse text-left text-corpo" data-cartoes="lg">
              <thead className="bg-fundo text-meta uppercase tracking-[0.25em] text-texto-suave">
                <tr>
                  <ColunaOrdenavel
                    titulo="ID"
                    coluna="id"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                    className="w-[5%] px-4 py-3"
                  />
                  <ColunaOrdenavel
                    titulo="Paciente"
                    coluna="paciente"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                    className="w-[16%] px-4 py-3"
                  />
                  <ColunaOrdenavel
                    titulo="Convênio"
                    coluna="convenio"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                    className="w-[10%] px-4 py-3"
                  />
                  <ColunaOrdenavel titulo="Itens" className="w-[35%] px-4 py-3" />
                  <ColunaOrdenavel
                    titulo="Status"
                    coluna="status"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                    className="w-[11%] px-4 py-3"
                  />
                  <ColunaOrdenavel
                    titulo="Médico solicitante"
                    coluna="medico"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                    className="w-[15%] px-4 py-3"
                  />
                  <ColunaOrdenavel titulo="Ações" className="w-[8%] px-4 py-3 text-center" />
                </tr>
              </thead>
              <tbody className="divide-y divide-linha bg-superficie">
                {solicitacoes.map((solicitacao) => (
                  <tr key={solicitacao.id} data-testid={`solicitacao-row-${solicitacao.id}`}>
                    <td data-rotulo="ID" className="px-4 py-4 font-medium text-white">#{solicitacao.id}</td>
                    <td data-rotulo="Paciente" className="break-words px-4 py-4 text-slate-200">
                      <button
                        type="button"
                        className="inline-flex min-h-6 items-center text-left font-semibold text-texto decoration-acento/60 underline-offset-4 transition hover:underline hover:text-acento-intenso"
                        onClick={() => setSelectedSolicitacaoId(solicitacao.id)}
                        data-testid={`solicitacao-paciente-${solicitacao.id}`}
                      >
                        {solicitacao.paciente?.nome ??
                          pacientes.find((item) => item.id === solicitacao.paciente_id)?.nome ??
                          solicitacao.paciente_id}
                      </button>
                    </td>
                    <td data-rotulo="Convênio" className="px-4 py-4 text-slate-200">
                      {convenios.find((item) => item.id === solicitacao.convenio_id)?.nome ??
                        solicitacao.convenio_id}
                    </td>
                    <td data-rotulo="Itens" data-rotulo-bloco className="px-4 py-4 text-slate-200">
                      {solicitacao.itens?.length ? (
                        <div className="space-y-1">
                          {solicitacao.itens.map((item) => {
                            const isUnimedRda =
                              solicitacao.convenio?.connector_driver === 'unimed_rda'
                            const hasActiveExecution = item.automacao_execucao_ativa !== null
                            const canSend =
                              isUnimedRda &&
                              solicitacao.status === 'ready_for_automation' &&
                              !item.guia_id &&
                              !hasActiveExecution
                            // Guia incerta pos-submit (Finalizar rodou no portal mas o worker
                            // nao leu a confirmacao de volta): sem numero de guia conhecido,
                            // so da pra confirmar buscando por paciente, nao reenviando.
                            const precisaVerificarAndamento =
                              isUnimedRda &&
                              !item.guia_id &&
                              item.automacao_execucao_ativa?.operacao === 'gerar_guia' &&
                              item.automacao_execucao_ativa?.status === 'uncertain'

                            return (
                              <div
                                key={item.id}
                                className="flex flex-col gap-2 rounded-superficie border border-linha bg-fundo p-3 shadow-e1"
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
                                    <span className="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-meta font-semibold text-emerald-100">
                                      Guia #{item.guia_id}
                                    </span>
                                  ) : null}
                                  {isUnimedRda && item.guia_id ? (
                                    <span
                                      className="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-meta font-semibold text-emerald-100"
                                      data-testid={`solicitacao-item-guia-gerada-${item.id}`}
                                    >
                                      Guia gerada
                                    </span>
                                  ) : null}
                                  {item.automacao_execucao_ativa ? (
                                    <button
                                      type="button"
                                      onClick={() =>
                                        setProgressoExecucaoId(item.automacao_execucao_ativa!.id)
                                      }
                                      className="rounded-full border border-amber-400/20 bg-amber-400/10 px-2.5 py-1 text-meta font-semibold text-amber-100 transition hover:bg-amber-400/20"
                                      data-testid={`solicitacao-item-execucao-ativa-${item.id}`}
                                    >
                                      {item.automacao_execucao_ativa.status} · ver andamento
                                    </button>
                                  ) : null}
                                  {precisaVerificarAndamento ? (
                                    <>
                                      <button
                                        type="button"
                                        onClick={() => void handleVerificarAndamentoItem(item.id)}
                                        disabled={verificarAndamentoItem.isPending}
                                        className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
                                        data-testid={`solicitacao-item-verificar-andamento-${item.id}`}
                                      >
                                        Verificar Andamento
                                      </button>
                                      <Tooltip rotulo="O que este botão faz">
                                        O robô tentou gerar a guia mas não conseguiu confirmar o
                                        resultado com o portal. Clique para checar se a guia foi
                                        criada de fato, buscando pelo paciente em Exames em
                                        aberto — sem reenviar a solicitação.
                                      </Tooltip>
                                    </>
                                  ) : isUnimedRda && !item.guia_id ? (
                                    <>
                                      <button
                                        type="button"
                                        onClick={() => void handleEnviarItemUnimed(item.id)}
                                        disabled={!canSend || enviarItemUnimed.isPending}
                                        className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
                                        data-testid={`solicitacao-item-enviar-unimed-${item.id}`}
                                      >
                                        Enviar para Unimed
                                      </button>
                                      <Tooltip rotulo="Quando este botão funciona">
                                        Dispara o robô da Unimed para gerar a guia sozinho (ver
                                        Automações). Só fica ativo com a solicitação pronta para
                                        automatização, sem guia gerada ainda e sem outra execução em
                                        andamento para este item.
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
                    <td data-rotulo="Status" className="px-4 py-4">
                      <Badge tone={statusTone(solicitacao.status)} data-testid={`solicitacao-status-${solicitacao.id}`}>
                        {translateStatus('solicitacoes', solicitacao.status)}
                      </Badge>
                    </td>
                    <td data-rotulo="Médico solicitante" className="px-4 py-4 text-slate-200">
                      {solicitacao.medico?.nome ?? solicitacao.medico_id}
                    </td>
                    <td data-rotulo="Ações" data-rotulo-bloco className="px-4 py-4 text-center">
                      <DropdownMenu.Root>
                        <DropdownMenu.Trigger asChild>
                          <button
                            type="button"
                            className="inline-flex items-center justify-center rounded-full border border-white/10 bg-white/5 p-2 text-slate-200 transition hover:bg-white/10"
                            aria-label={`Ações da solicitação #${solicitacao.id}`}
                            data-testid={`solicitacao-acoes-${solicitacao.id}`}
                          >
                            <MoreVertical className="size-4" aria-hidden="true" />
                          </button>
                        </DropdownMenu.Trigger>

                        <DropdownMenu.Portal>
                          <DropdownMenu.Content
                            align="end"
                            sideOffset={6}
                            className="z-(--z-dialogo) min-w-56 rounded-2xl border border-linha bg-superficie-elevada p-1.5 text-corpo text-white shadow-e2"
                          >
                            {statusActions.map((action) => (
                              <DropdownMenu.Item
                                key={action.status}
                                disabled={
                                  atualizarStatusSolicitacao.isPending ||
                                  solicitacao.status === action.status
                                }
                                onSelect={() => void handleStatusChange(solicitacao, action.status)}
                                className={`flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 outline-none transition data-[disabled]:cursor-not-allowed data-[disabled]:opacity-40 data-[highlighted]:bg-white/10 ${action.textClassName}`}
                                data-testid={`solicitacao-status-action-${action.status}-${solicitacao.id}`}
                              >
                                <span
                                  className={`size-2 shrink-0 rounded-full ${action.dotClassName}`}
                                  aria-hidden="true"
                                />
                                {action.label}
                              </DropdownMenu.Item>
                            ))}

                            <DropdownMenu.Separator className="my-1.5 h-px bg-white/10" />

                            <DropdownMenu.Item
                              onSelect={() => setSelectedSolicitacaoId(solicitacao.id)}
                              className="flex cursor-pointer items-center justify-between gap-2 rounded-xl px-3 py-2 text-slate-100 outline-none transition data-[highlighted]:bg-white/10"
                              data-testid={`solicitacao-anexos-${solicitacao.id}`}
                            >
                              Anexos
                              <span className="text-meta text-slate-400">
                                {solicitacao.documentos?.length ?? 0}
                              </span>
                            </DropdownMenu.Item>

                            {pode('solicitacoes.manage') ? (
                              <DropdownMenu.Item
                                asChild
                                className="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-slate-100 outline-none transition data-[highlighted]:bg-white/10"
                              >
                                <Link
                                  to={`/solicitacoes/${solicitacao.id}/editar`}
                                  data-testid={`solicitacao-editar-${solicitacao.id}`}
                                >
                                  Editar
                                </Link>
                              </DropdownMenu.Item>
                            ) : null}
                          </DropdownMenu.Content>
                        </DropdownMenu.Portal>
                      </DropdownMenu.Root>
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
          <Botao
            type="button"
            variante="secundario"
            tamanho="sm"
            onClick={() => setPage((current) => Math.max(1, current - 1))}
            disabled={page <= 1 || solicitacoesQuery.isFetching}
          >
            Anterior
          </Botao>

          <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">
            Página {page} de {totalPages}
          </p>

          <Botao
            type="button"
            variante="secundario"
            tamanho="sm"
            onClick={() => setPage((current) => Math.min(totalPages, current + 1))}
            disabled={page >= totalPages || solicitacoesQuery.isFetching}
          >
            Próxima
          </Botao>
        </div>
      </section>
      ) : null}

      <SolicitacaoGuiaModal
        solicitacao={selectedSolicitacao}
        onClose={() => setSelectedSolicitacaoId(null)}
      />

      <AutomacaoProgressoModal
        execucaoId={progressoExecucaoId}
        onClose={() => setProgressoExecucaoId(null)}
        queryKeysInvalidar={[['solicitacoes']]}
      />

      <AutomacaoUnimedDesativadaModal {...automacaoUnimedModalProps} />
    </div>
  )
}

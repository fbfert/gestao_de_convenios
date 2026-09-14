import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { AvisoErro } from '../../components/ui/AvisoErro'
import { useConfirm } from '../../components/ui/ConfirmDialog'
import { MoreVertical, Plus, X } from 'lucide-react'
import { DropdownMenu } from 'radix-ui'
import { ColunaOrdenavel } from '../../components/ui/ColunaOrdenavel'
import { useOrdenacao } from '../../lib/useOrdenacao'
import { Link, useLocation, useMatch, useNavigate, useSearchParams } from 'react-router-dom'
import { translateStatus } from '../../lib/statusLabels'
import { Select } from '../../components/ui/Select'
import {
  getHttpErrorMessage,
  useAtualizarStatusSolicitacao,
  useCriarSolicitacao,
  useEnviarItemUnimed,
  useRemoverItem,
  useSolicitacoes,
  useVerificarAndamentoItem,
  useVincularDocumento,
} from './useSolicitacoes'
import { ConfirmarExclusao } from '../../components/ui/ConfirmarExclusao'
import { STATUS_QUE_BLOQUEIAM_ADICAO, STATUS_QUE_BLOQUEIAM_ENVIO } from './types'
import type {
  Solicitacao,
  SolicitacaoFilters,
  SolicitacaoForm,
  SolicitacaoItem,
  SolicitacaoStatus,
} from './types'
import {
  useConvenios,
  useEspecialidades,
  usePaciente,
  useProfissionais,
  type MedicoRef,
  type PacienteRef,
} from '../../lib/queries/useReferenceData'
import { formatCarteirinha } from '../../lib/carteirinha'
import { SolicitacaoGuiaModal } from './SolicitacaoGuiaModal'
import { AdicionarSessoesModal } from './AdicionarSessoesModal'
import { SolicitacaoInfoCelula } from './SolicitacaoInfoCelula'
import { formatarData } from './datas'
import { SelecionarPacienteModal } from './SelecionarPacienteModal'
import { SelecionarMedicoModal } from './SelecionarMedicoModal'
import { AutomacaoProgressoModal } from '../automacoes/AutomacaoProgressoModal'
import { AutomacaoUnimedDesativadaModal } from '../configuracoes/AutomacaoUnimedDesativadaModal'
import { useAutomacaoUnimedGate } from '../configuracoes/useAutomacaoUnimedGate'
import { CidsCampo } from '../cids/CidsCampo'
import { SolicitacaoItensFields } from './SolicitacaoItensFields'
import { ResumoPastaPaciente } from './ResumoPastaPaciente'
import { SolicitacaoAnexosStep } from './SolicitacaoAnexosStep'
import { emptyItem, itemComPadraoDoConvenio, itensEstaoCompletos, rotuloDaRemessa } from './solicitacaoItens'
import {
  PedidoMedicoExistentePrompt,
  type PedidoExistenteEscolhido,
} from './PedidoMedicoExistentePrompt'
import { SelecionarItensAntecipacaoModal } from '../antecipacoes/SelecionarItensAntecipacaoModal'
import { Indicadores } from '../../components/ui/Indicadores'
import { Tooltip } from '../../components/ui/Tooltip'
import { usePode } from '../../lib/permissoes'
import { Botao } from '../../components/ui/Botao'
import { Paginacao } from '../../components/ui/Paginacao'
import { useListaNaUrl } from '../../lib/useListaNaUrl'
import { Badge, type BadgeProps } from '../../components/ui/Badge'
// O tom do badge da guia vem do mapa das guias — que já cobre os status
// `historico_*` com tom neutro. Renomeado no import porque este arquivo já tem
// um `statusTone` próprio, o das solicitações.
import { statusTone as guiaStatusTone } from '../guias/statusTone'

const emptyArray: never[] = []

const defaultFilters: SolicitacaoFilters = {
  id: '',
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
  const confirmar = useConfirm()
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/solicitacoes/nova') !== null
  const [searchParams] = useSearchParams()
  const location = useLocation()
  // `state.from` só existe quando chegamos aqui (nova/editar) a partir da
  // lista — usado pra voltar mantendo página/filtro; `fromHref` é o inverso,
  // calculado na PRÓPRIA lista a partir da query string atual, pra passar
  // adiante em `state` (nunca na URL de /nova — essa já usa query string
  // pra outra coisa: pré-preencher paciente/convênio por deep link).
  const voltarPara = (location.state as { from?: string } | null)?.from ?? '/solicitacoes'
  const { filters, page, setFilters, setPage } = useListaNaUrl(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(filters)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedSolicitacaoId, setSelectedSolicitacaoId] = useState<number | null>(null)
  const [progressoExecucaoId, setProgressoExecucaoId] = useState<number | null>(null)
  const [adicionarSessoesId, setAdicionarSessoesId] = useState<number | null>(null)
  const [gerarAntecipacaoId, setGerarAntecipacaoId] = useState<number | null>(null)
  const [form, setForm] = useState<SolicitacaoForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)
  // Erros das ações da listagem (mudar status, excluir item). Eram
  // `window.alert`, que trava a aba até alguém clicar OK e não deixa rastro
  // nenhum na tela depois de fechado.
  const [erroAcao, setErroAcao] = useState<string | null>(null)
  const [pacienteSelecionado, setPacienteSelecionado] = useState<PacienteRef | null>(null)
  const [medicoSelecionado, setMedicoSelecionado] = useState<MedicoRef | null>(null)
  /** Criada com sucesso: troca o formulário pela etapa de anexos, sem navegar pra outro lugar. */
  const [solicitacaoCriada, setSolicitacaoCriada] = useState<Solicitacao | null>(null)
  const [pacienteModalAberto, setPacienteModalAberto] = useState(false)
  const [medicoModalAberto, setMedicoModalAberto] = useState(false)
  const [itemAExcluir, setItemAExcluir] = useState<{
    solicitacaoId: number
    item: SolicitacaoItem
  } | null>(null)
  // Enquanto null, o prompt de "já tem pedido" fica visível (se houver
  // pedido na pasta); qualquer uma das 3 escolhas o resolve. Reseta junto
  // com o paciente — a pergunta é por paciente, não pela sessão inteira.
  const [pedidoExistenteResolvido, setPedidoExistenteResolvido] = useState(false)
  const [pedidoExistenteArquivoId, setPedidoExistenteArquivoId] = useState<number | null>(null)

  const { ordenacao, ordenarPor } = useOrdenacao({
    ordenar_por: 'id',
    direcao: 'desc',
  })

  const conveniosQuery = useConvenios()
  // O código do procedimento é por convênio, então a listagem acompanha o convênio do form.
  const especialidadesQuery = useEspecialidades({ convenio_id: form.convenio_id })
  // Todos os profissionais: cada linha de item filtra pela sua própria especialidade.
  const profissionaisQuery = useProfissionais()
  // Paciente pré-preenchido por link (alerta de guia negada) — ver useEffect
  // abaixo. Sem isso o botão de Paciente mostraria só o id.
  const pacienteIdParam = isCreateRoute ? searchParams.get('paciente_id') : null
  const pacientePreSelecionadoQuery = usePaciente(pacienteIdParam ? Number(pacienteIdParam) : null)
  const solicitacoesQuery = useSolicitacoes({ ...filters, ...ordenacao }, page)
  const criarSolicitacao = useCriarSolicitacao()
  const atualizarStatusSolicitacao = useAtualizarStatusSolicitacao()
  const enviarItemUnimed = useEnviarItemUnimed()
  const removerItem = useRemoverItem()
  const vincularDocumento = useVincularDocumento()
  const verificarAndamentoItem = useVerificarAndamentoItem()
  const {
    tratarErroUnimed,
    modalProps: automacaoUnimedModalProps,
    avisoProps: automacaoUnimedAvisoProps,
  } = useAutomacaoUnimedGate()

  const convenios = useMemo(() => conveniosQuery.data ?? emptyArray, [conveniosQuery.data])
  const especialidades = useMemo(
    () => especialidadesQuery.data ?? emptyArray,
    [especialidadesQuery.data],
  )
  const profissionais = useMemo(
    () => profissionaisQuery.data ?? emptyArray,
    [profissionaisQuery.data],
  )
  const convenioSelecionado = useMemo(
    () => convenios.find((item) => String(item.id) === form.convenio_id),
    [convenios, form.convenio_id],
  )

  const formReady = convenios.length > 0 && especialidades.length > 0
  const formIsComplete =
    formReady &&
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

  /*
    Quantidade sugerida pela regra do convênio.

    Preenche só o que está VAZIO: quem já digitou um número tem a palavra final,
    e trocar de convênio não pode apagar o que a pessoa escreveu. Convênio sem
    `sessoes_por_guia` deixa vazio mesmo — a API recusa em vez de arbitrar, e
    mostrar um número que o salvamento rejeita é o pior dos dois mundos.
  */
  useEffect(() => {
    const padrao = convenioSelecionado?.sessoes_por_guia

    if (!padrao) {
      return
    }

    setForm((current) =>
      current.itens.some((item) => item.quantidade === '')
        ? {
            ...current,
            itens: current.itens.map((item) =>
              item.quantidade === '' ? { ...item, quantidade: String(padrao) } : item,
            ),
          }
        : current,
    )
  }, [convenioSelecionado])

  // Se o convênio mudar depois de um paciente escolhido, a seleção pode não
  // pertencer mais a esse convênio (o cadastro de paciente é por convênio) —
  // limpa para o usuário escolher de novo, em vez de mandar um id incoerente.
  useEffect(() => {
    if (pacienteSelecionado && String(pacienteSelecionado.convenio_id) !== form.convenio_id) {
      setPacienteSelecionado(null)
      setForm((current) => ({ ...current, paciente_id: '' }))
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.convenio_id])

  useEffect(() => {
    if (pacientePreSelecionadoQuery.data) {
      setPacienteSelecionado(pacientePreSelecionadoQuery.data)
    }
  }, [pacientePreSelecionadoQuery.data])

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
  const fromHref = !isCreateRoute && searchParams.toString() ? `/solicitacoes?${searchParams.toString()}` : '/solicitacoes'

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
  // Mesmo motivo do de cima: guardar o id, não o objeto — depois de acrescentar
  // o item, a lista refaz a consulta e o modal precisa ver a versão nova.
  const solicitacaoParaAntecipar = useMemo(
    () => solicitacoes.find((item) => item.id === gerarAntecipacaoId) ?? null,
    [solicitacoes, gerarAntecipacaoId],
  )
  const solicitacaoParaAdicionar = useMemo(
    () => solicitacoes.find((item) => item.id === adicionarSessoesId) ?? null,
    [solicitacoes, adicionarSessoesId],
  )

  /**
   * A mesma regra do backend (`SolicitacaoStatus::BLOQUEIAM_ADICAO`), mais a
   * permissão. Note que NÃO é a lista de bloqueio de envio: `under_review`
   * barra o envio e permite acrescentar.
   */
  const podeAdicionarSessoes = (solicitacao: Solicitacao) =>
    pode('solicitacoes.manage') &&
    !STATUS_QUE_BLOQUEIAM_ADICAO.includes(solicitacao.status as SolicitacaoStatus)

  /** Só faz sentido antecipar quando existe pelo menos uma guia já aprovada (ou finalizada) pra repetir. */
  const podeGerarAntecipacao = (solicitacao: Solicitacao) =>
    pode('antecipacoes.manage') &&
    (solicitacao.itens ?? []).some((item) => item.guia && ['approved', 'finalized'].includes(item.guia.status))

  // Convênio do FILTRO da lista, não o do formulário de Nova Solicitação —
  // achado 03/09/2026: usava form.convenio_id por engano, e como o
  // formulário sempre pré-seleciona o primeiro convênio em ordem alfabética
  // (ver useEffect abaixo), o indicador mostrava sempre "Celos" (Convênios
  // ordena por nome), mesmo sem filtro nenhum aplicado na lista.
  const currentConvenio = useMemo(
    () => convenios.find((item) => String(item.id) === filters.convenio_id),
    [convenios, filters.convenio_id],
  )

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFilters(draftFilters)
  }

  // Filtro exclusivo: liga mostra só as solicitações "Histórico" (rastro de
  // guias antigas migradas sem automação por trás — ver GuiaService) e some
  // com o filtro de Status normal, pra não brigarem pelo mesmo campo.
  const toggleHistoricoBadge = () => {
    const ligado = draftFilters.mostrar_historico === '1'
    const proximo = ligado ? '' : '1'
    setFilters({ ...filters, status: '', mostrar_historico: proximo })
    setDraftFilters((current) => ({ ...current, status: '', mostrar_historico: proximo }))
  }

  const handleNew = () => {
    navigate('/solicitacoes/nova', { state: { from: fromHref } })
    setForm((current) => ({
      ...emptyForm,
      convenio_id: current.convenio_id,
      paciente_id: current.paciente_id,
      medico_id: current.medico_id,
      cid_ids: current.cid_ids,
      itens: [{ ...emptyItem }],
    }))
    setSolicitacaoCriada(null)
    setFormError(null)
  }

  const handleCancel = () => {
    setFormError(null)
    setSolicitacaoCriada(null)
    if (isCreateRoute) {
      navigate(voltarPara)
      return
    }

    setIsFormOpen(false)
  }

  const handleFormSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      const criada = await criarSolicitacao.mutateAsync(form)

      // "Usar pedido existente": o arquivo já está na pasta, só falta
      // vincular como Pedido Médico desta solicitação nova — mesma rota que
      // a etapa de anexos usa pra reaproveitar arquivo, só que acionada aqui
      // em vez de a pessoa repetir a escolha manualmente daqui a pouco.
      if (pedidoExistenteArquivoId) {
        try {
          await vincularDocumento.mutateAsync({
            solicitacaoId: criada.id,
            pacienteArquivoId: pedidoExistenteArquivoId,
          })
        } catch (error) {
          setFormError(
            getHttpErrorMessage(
              error,
              'Solicitação criada, mas não foi possível vincular o pedido médico existente — anexe manualmente na próxima etapa.',
            ),
          )
        }
      }

      // Não reseta nem navega ainda: a próxima etapa (anexos) usa esta
      // solicitação recém-criada, com id real.
      setSolicitacaoCriada(criada)
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível criar a solicitação.'))
    }
  }

  const handleUsarPedidoExistente = (escolha: PedidoExistenteEscolhido) => {
    setPedidoExistenteArquivoId(escolha.arquivoId)
    setPedidoExistenteResolvido(true)

    if (escolha.medico) {
      const medico = escolha.medico
      setMedicoSelecionado({
        id: medico.id,
        nome: medico.nome,
        crm: medico.crm,
        crm_uf: medico.crm_uf,
        especialidade_medica: '',
        telefone: '',
        email: null,
        ativo: true,
      })
    }

    setForm((current) => ({
      ...current,
      medico_id: escolha.medico ? String(escolha.medico.id) : current.medico_id,
      cid_ids: escolha.cidIds.length > 0 ? escolha.cidIds.map(String) : current.cid_ids,
      itens:
        escolha.itens.length > 0
          ? escolha.itens.map((item) => ({
              ...itemComPadraoDoConvenio(convenioSelecionado?.sessoes_por_guia),
              especialidade_id: String(item.especialidade_id),
              profissional_id: String(item.profissional_id),
            }))
          : current.itens,
    }))
  }

  const handleLerNovoPedido = () => {
    setPedidoExistenteResolvido(true)
    const params = new URLSearchParams()
    if (pacienteSelecionado) params.set('paciente_id', String(pacienteSelecionado.id))
    if (form.convenio_id) params.set('convenio_id', form.convenio_id)
    navigate(`/solicitacoes/ler-pedido-medico?${params.toString()}`)
  }

  const handleAnexarNovoPedido = () => {
    setPedidoExistenteResolvido(true)
  }

  const handleConcluirAnexos = () => {
    setForm((current) => ({
      ...emptyForm,
      convenio_id: current.convenio_id,
      paciente_id: current.paciente_id,
      medico_id: current.medico_id,
      cid_ids: current.cid_ids,
      itens: [{ ...emptyItem }],
    }))
    setSolicitacaoCriada(null)
    if (isCreateRoute) {
      navigate(voltarPara)
    } else {
      setIsFormOpen(false)
    }
  }

  const handleStatusChange = async (solicitacao: Solicitacao, status: SolicitacaoStatus) => {
    if (solicitacao.status === status) {
      return
    }

    const statusLabel = translateStatus('solicitacoes', status)
    const confirmado = await confirmar({
      titulo: 'Alterar status da solicitação',
      descricao: `A solicitação #${solicitacao.id} passa para ${statusLabel}.`,
      confirmarTexto: 'Alterar',
    })

    if (!confirmado) {
      return
    }

    try {
      await atualizarStatusSolicitacao.mutateAsync({ id: solicitacao.id, status })
    } catch (error) {
      setErroAcao(
        getHttpErrorMessage(error, 'Não foi possível alterar o status da solicitação.'),
      )
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

  const handleRemoverItem = async () => {
    if (!itemAExcluir) {
      return
    }

    try {
      await removerItem.mutateAsync({
        solicitacaoId: itemAExcluir.solicitacaoId,
        itemId: itemAExcluir.item.id,
      })
      setItemAExcluir(null)
    } catch (error) {
      setErroAcao(getHttpErrorMessage(error, 'Não foi possível excluir o item.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="solicitacoes-page">
      {erroAcao ? (
        <p
          className="rounded-janela border border-perigo/30 bg-perigo-suave px-4 py-3 text-corpo text-perigo-texto"
          role="alert"
          data-testid="solicitacoes-erro-acao"
        >
          {erroAcao}
        </p>
      ) : null}
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

      {(isFormOpen || isCreateRoute) && solicitacaoCriada ? (
        <SolicitacaoAnexosStep solicitacao={solicitacaoCriada} onConcluir={handleConcluirAnexos} />
      ) : null}

      {(isFormOpen || isCreateRoute) && !solicitacaoCriada ? (
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
              <button
                type="button"
                onClick={() => setPacienteModalAberto(true)}
                disabled={form.convenio_id === ''}
                className={`${selectClasses()} flex items-center justify-between text-left disabled:opacity-60`}
                data-testid="solicitacao-paciente"
              >
                <span className={pacienteSelecionado ? '' : 'text-slate-400'}>
                  {pacienteSelecionado
                    ? `${pacienteSelecionado.nome} · ${formatCarteirinha(pacienteSelecionado.carteirinha, pacienteSelecionado.convenio?.carteirinha_blocos ?? undefined)}`
                    : form.convenio_id === ''
                      ? 'Selecione o convênio primeiro'
                      : 'Buscar paciente...'}
                </span>
                <span className="text-cyan-200">🔍</span>
              </button>
            </label>

            <SelecionarPacienteModal
              open={pacienteModalAberto}
              onClose={() => setPacienteModalAberto(false)}
              onSelecionar={(paciente) => {
                setPacienteSelecionado(paciente)
                setForm((current) => ({ ...current, paciente_id: String(paciente.id) }))
                setPedidoExistenteResolvido(false)
                setPedidoExistenteArquivoId(null)
              }}
              convenioId={form.convenio_id}
              carteirinhaBlocos={convenioSelecionado?.carteirinha_blocos}
            />

            {isCreateRoute && pacienteSelecionado && !pedidoExistenteResolvido ? (
              <PedidoMedicoExistentePrompt
                pacienteId={pacienteSelecionado.id}
                onUsarPedido={handleUsarPedidoExistente}
                onLerNovo={handleLerNovoPedido}
                onAnexarNovo={handleAnexarNovoPedido}
              />
            ) : null}

            <ResumoPastaPaciente pacienteId={pacienteSelecionado ? pacienteSelecionado.id : null} />

            <SolicitacaoItensFields
              itens={form.itens}
              onChange={(itens) => setForm((current) => ({ ...current, itens }))}
              especialidades={especialidades}
              profissionais={profissionais}
              disabled={especialidadesQuery.isLoading || profissionaisQuery.isLoading}
              sessoesPorGuia={convenioSelecionado?.sessoes_por_guia ?? null}
            />

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Médico solicitante</span>
              <button
                type="button"
                onClick={() => setMedicoModalAberto(true)}
                className={`${selectClasses()} flex items-center justify-between text-left`}
                data-testid="solicitacao-medico"
              >
                <span className={medicoSelecionado ? '' : 'text-slate-400'}>
                  {medicoSelecionado ? medicoSelecionado.nome : 'Buscar médico...'}
                </span>
                <span className="text-cyan-200">🔍</span>
              </button>
            </label>

            <SelecionarMedicoModal
              open={medicoModalAberto}
              onClose={() => setMedicoModalAberto(false)}
              onSelecionar={(medico) => {
                setMedicoSelecionado(medico)
                setForm((current) => ({ ...current, medico_id: String(medico.id) }))
              }}
            />

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
            <label className="min-w-28 flex-1 space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">ID</span>
              <input
                type="text"
                inputMode="numeric"
                value={draftFilters.id}
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    id: event.target.value.replace(/\D/g, ''),
                  }))
                }
                placeholder="Buscar por ID"
                className={selectClasses()}
                data-testid="solicitacao-filtro-id"
              />
            </label>

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
                Médico
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
                  <ColunaOrdenavel titulo="Itens" className="w-[30%] px-4 py-3" />
                  <ColunaOrdenavel
                    titulo="Status"
                    coluna="status"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                    className="w-[11%] px-4 py-3"
                  />
                  <ColunaOrdenavel
                    titulo="Médico"
                    coluna="medico"
                    ordenacao={ordenacao}
                    onOrdenar={ordenarPor}
                    className="w-[13%] px-4 py-3"
                  />
                  {/* Antes de Ações, com os 7% que sobraram ao encolher Itens e
                      Médico — a tabela é `table-fixed` e as larguras precisam
                      somar 100%. */}
                  <ColunaOrdenavel titulo="Info" className="w-[7%] px-4 py-3" />
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
                        {solicitacao.paciente?.nome ?? solicitacao.paciente_id}
                      </button>
                      {/* A data do pedido é o dado mais consultado da linha, e
                          exigir hover para o mais consultado é caro. As outras
                          duas datas ficam no tooltip da coluna Info. */}
                      {solicitacao.solicitado_em ? (
                        <p
                          className="mt-1 text-meta text-texto-suave"
                          data-testid={`solicitacao-solicitado-em-${solicitacao.id}`}
                        >
                          {formatarData(solicitacao.solicitado_em)}
                        </p>
                      ) : null}
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
                            // Gate por ITEM, e não pela solicitação inteira:
                            // exigir `ready_for_automation` fazia o item novo de
                            // uma solicitação já aprovada nunca poder ser
                            // enviado — que é justamente o caso de "Adicionar
                            // sessões". A lista de status barrados espelha
                            // App\Support\SolicitacaoStatus::BLOQUEIAM_ENVIO, e
                            // a API a aplica de novo do lado de lá.
                            const canSend =
                              isUnimedRda &&
                              !item.guia &&
                              !hasActiveExecution &&
                              !STATUS_QUE_BLOQUEIAM_ENVIO.includes(
                                solicitacao.status as SolicitacaoStatus,
                              )
                            // Guia incerta pos-submit (Finalizar rodou no portal mas o worker
                            // nao leu a confirmacao de volta): sem numero de guia conhecido,
                            // so da pra confirmar buscando por paciente, nao reenviando.
                            const precisaVerificarAndamento =
                              isUnimedRda &&
                              !item.guia &&
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
                                {/* Sem isto, duas remessas da mesma
                                    especialidade com o mesmo profissional
                                    viram duas linhas idênticas e ninguém
                                    entende por que são duas. Linha própria,
                                    embaixo da especialização — não mais
                                    encostada no texto da linha de cima. */}
                                {rotuloDaRemessa(item) ? (
                                  <p>
                                    <span
                                      className="rounded-pilula border border-acento/40 bg-acento-suave px-2 py-0.5 text-meta font-semibold text-acento-intenso"
                                      data-testid={`solicitacao-item-remessa-${item.id}`}
                                    >
                                      {rotuloDaRemessa(item)}
                                    </span>
                                  </p>
                                ) : null}
                                <div className="flex flex-wrap items-center gap-2">
                                  {/* O NÚMERO DA OPERADORA, nunca o id interno.
                                      `numero_operadora` já vem nulo quando o
                                      valor guardado é o de preenchimento do
                                      convênio manual — trocar o id por aquele
                                      texto seria substituir um número errado
                                      por outro, igualmente inútil num telefonema
                                      com o convênio. */}
                                  {item.guia ? (
                                    <span
                                      className="rounded-pilula border border-linha bg-fundo px-2.5 py-1 text-meta font-semibold text-texto"
                                      data-testid={`solicitacao-item-guia-numero-${item.id}`}
                                    >
                                      {item.guia.numero_operadora
                                        ? `Guia ${item.guia.numero_operadora}`
                                        : item.guia.numero_guia
                                          ? 'Guia gerada · sem nº da operadora'
                                          : 'Guia gerada · nº pendente'}
                                    </span>
                                  ) : null}
                                  {/* A SITUAÇÃO REAL da guia, para qualquer
                                      convênio. O "Guia gerada" fixo era
                                      redundante ao lado do número (se tem guia,
                                      foi gerada) e sumia no convênio manual. */}
                                  {item.guia ? (
                                    <Badge
                                      tone={guiaStatusTone(item.guia.status)}
                                      data-testid={`solicitacao-item-guia-status-${item.id}`}
                                    >
                                      {translateStatus('guias', item.guia.status)}
                                    </Badge>
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
                                  ) : isUnimedRda && !item.guia ? (
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
                                  {!item.guia &&
                                  (solicitacao.itens?.length ?? 0) > 1 &&
                                  !STATUS_QUE_BLOQUEIAM_ADICAO.includes(
                                    solicitacao.status as SolicitacaoStatus,
                                  ) ? (
                                    <button
                                      type="button"
                                      onClick={() => setItemAExcluir({ solicitacaoId: solicitacao.id, item })}
                                      className="inline-flex items-center justify-center rounded-full border border-rose-400/30 bg-rose-400/10 p-1.5 text-rose-300 transition hover:bg-rose-400/20"
                                      aria-label="Excluir item"
                                      title="Excluir item"
                                      data-testid={`solicitacao-item-excluir-${item.id}`}
                                    >
                                      <X className="size-4" aria-hidden="true" />
                                    </button>
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
                    <td data-rotulo="Médico" className="px-4 py-4 text-slate-200">
                      {solicitacao.medico?.nome ?? solicitacao.medico_id}
                    </td>
                    {/* `data-rotulo` é o que nomeia a célula no modo cartão
                        (`data-cartoes="lg"`), como todas as outras. */}
                    <td data-rotulo="Info" className="px-4 py-4">
                      <SolicitacaoInfoCelula solicitacao={solicitacao} />
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

                            {/* Dentro do menu, e não ao lado dele: a tabela é
                                `table-fixed` com as larguras somando 100%, e um
                                segundo botão na célula de Ações transborda para
                                debaixo da coluna Info — o ícone de lá passa a
                                interceptar o clique. A suíte E2E pegou isso. */}
                            {podeAdicionarSessoes(solicitacao) ? (
                              <DropdownMenu.Item
                                onSelect={() => setAdicionarSessoesId(solicitacao.id)}
                                className="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-slate-100 outline-none transition data-[highlighted]:bg-white/10"
                                data-testid={`solicitacao-adicionar-sessoes-${solicitacao.id}`}
                              >
                                <Plus className="size-4 shrink-0" aria-hidden="true" />
                                Adicionar sessões
                              </DropdownMenu.Item>
                            ) : null}

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

                            {podeGerarAntecipacao(solicitacao) ? (
                              <DropdownMenu.Item
                                onSelect={() => setGerarAntecipacaoId(solicitacao.id)}
                                className="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-slate-100 outline-none transition data-[highlighted]:bg-white/10"
                                data-testid={`solicitacao-gerar-antecipacao-${solicitacao.id}`}
                              >
                                Gerar Antecipação
                              </DropdownMenu.Item>
                            ) : null}

                            {pode('solicitacoes.manage') ? (
                              <DropdownMenu.Item
                                asChild
                                className="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-slate-100 outline-none transition data-[highlighted]:bg-white/10"
                              >
                                <Link
                                  to={`/solicitacoes/${solicitacao.id}/editar`}
                                  state={{ from: fromHref }}
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

        <Paginacao page={page} totalPages={totalPages} onChange={setPage} />
      </section>
      ) : null}

      <SolicitacaoGuiaModal
        solicitacao={selectedSolicitacao}
        onClose={() => setSelectedSolicitacaoId(null)}
        onAdicionarSessoes={
          selectedSolicitacao && podeAdicionarSessoes(selectedSolicitacao)
            ? () => setAdicionarSessoesId(selectedSolicitacao.id)
            : undefined
        }
      />

      <AdicionarSessoesModal
        solicitacao={solicitacaoParaAdicionar}
        especialidades={especialidades}
        profissionais={profissionais}
        onClose={() => setAdicionarSessoesId(null)}
      />

      <SelecionarItensAntecipacaoModal
        open={gerarAntecipacaoId !== null}
        solicitacao={solicitacaoParaAntecipar}
        onClose={() => setGerarAntecipacaoId(null)}
      />

      <AutomacaoProgressoModal
        execucaoId={progressoExecucaoId}
        onClose={() => setProgressoExecucaoId(null)}
        queryKeysInvalidar={[['solicitacoes']]}
      />

      <AvisoErro {...automacaoUnimedAvisoProps} testId="automacao-unimed-erro" />
      <AutomacaoUnimedDesativadaModal {...automacaoUnimedModalProps} />

      {itemAExcluir ? (
        <ConfirmarExclusao
          titulo="Excluir item da solicitação"
          descricao="O item será apagado da solicitação. Use para corrigir um cadastro errado — itens com Guia já gerada não podem ser excluídos por aqui."
          alvo={`${itemAExcluir.item.especialidade?.nome ?? itemAExcluir.item.especialidade_id} · ${itemAExcluir.item.profissional?.nome ?? itemAExcluir.item.profissional_id} · ${itemAExcluir.item.quantidade}`}
          confirmando={removerItem.isPending}
          onConfirmar={() => void handleRemoverItem()}
          onCancelar={() => setItemAExcluir(null)}
        />
      ) : null}
    </div>
  )
}

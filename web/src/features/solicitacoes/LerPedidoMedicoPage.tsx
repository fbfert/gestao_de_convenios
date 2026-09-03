import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { Select } from '../../components/ui/Select'
import {
  useCids,
  useConvenios,
  useEspecialidades,
  useMedicos,
  usePacientes,
  useProfissionais,
  type EspecialidadeRef,
  type MedicoRef,
  type PacienteRef,
} from '../../lib/queries/useReferenceData'
import {
  getHttpErrorMessage,
  useAnalisarPedidoMedico,
  useCriarEspecialidadeRapida,
  useCriarMedicoRapido,
  useCriarPacienteRapido,
  useCriarSolicitacao,
} from './useSolicitacoes'
import { formatCarteirinha, isCarteirinhaCompleta } from '../../lib/carteirinha'
import { CarteirinhaBlocosInput } from '../../components/ui/CarteirinhaBlocosInput'
import { SolicitacaoItensFields } from './SolicitacaoItensFields'
import { emptyItem, itensEstaoCompletos } from './solicitacaoItens'
import type {
  PedidoMedicoAiResult,
  PedidoMedicoSuggestion,
  SolicitacaoForm,
  SolicitacaoFormItem,
} from './types'
import { Botao } from '../../components/ui/Botao'
import { CidsCampo } from '../cids/CidsCampo'

const emptyArray: never[] = []

const emptyForm: SolicitacaoForm = {
  paciente_id: '',
  convenio_id: '',
  medico_id: '',
  cid_ids: [],
  solicitado_em: new Date().toISOString().slice(0, 10),
  observacoes: '',
  itens: [{ ...emptyItem }],
}

const ETAPAS = [
  'Upload',
  'Convênio',
  'Paciente',
  'Médico solicitante',
  'Especialidades',
  'Revisão',
] as const

/**
 * Acrescenta uma linha de item para a especialidade, se ela ainda nao estiver
 * no pedido. Reaproveita a primeira linha quando ela esta em branco, para o
 * caso comum de um pedido com uma especialidade so nao nascer com uma linha
 * vazia sobrando.
 */
function comEspecialidadeAdicionada(
  itens: SolicitacaoFormItem[],
  especialidadeId: string,
): SolicitacaoFormItem[] {
  if (itens.some((item) => item.especialidade_id === especialidadeId)) {
    return itens
  }

  const indiceVazio = itens.findIndex((item) => item.especialidade_id === '')

  if (indiceVazio >= 0) {
    return itens.map((item, indice) =>
      indice === indiceVazio ? { ...item, especialidade_id: especialidadeId } : item,
    )
  }

  return [...itens, { ...emptyItem, especialidade_id: especialidadeId }]
}

function selectClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function mergeById<T extends { id: number }>(primary: T[], secondary: T[]) {
  const seen = new Set<number>()
  return [...primary, ...secondary].filter((item) => {
    if (seen.has(item.id)) {
      return false
    }
    seen.add(item.id)
    return true
  })
}

function suggestionTitle(suggestion: PedidoMedicoSuggestion) {
  // CRM sozinho não identifica o médico: o número se repete entre estados
  // diferentes, então a UF entra junto sempre que houver um CRM pra mostrar.
  const crm = suggestion.crm ? `${suggestion.crm}${suggestion.crm_uf ? `/${suggestion.crm_uf}` : ''}` : undefined
  const extra = suggestion.carteirinha || crm
  return extra ? `${suggestion.nome} · ${extra}` : suggestion.nome
}

/** Cadastro rápido de especialidade — a única criação que ainda faz sentido
 *  num modal simples, já que não escolhe entre "existente vs novo" (o passo
 *  de Especialidades já lista os cadastros; isto é só um atalho pontual). */
function NovaEspecialidadeModal({
  aberto,
  nomeInicial,
  salvando,
  erro,
  onClose,
  onSubmit,
}: {
  aberto: boolean
  nomeInicial: string
  salvando: boolean
  erro: string | null
  onClose: () => void
  onSubmit: (nome: string) => void
}) {
  const [nome, setNome] = useState(nomeInicial)

  useEffect(() => {
    setNome(nomeInicial)
  }, [nomeInicial, aberto])

  return (
    <Dialog open={aberto} onClose={onClose} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel className="w-full max-w-lg rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60">
            <div className="flex items-start justify-between gap-4">
              <DialogTitle className="text-titulo font-semibold">Nova especialidade</DialogTitle>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-corpo font-semibold text-white transition hover:bg-white/10"
              >
                Fechar
              </button>
            </div>

            <form
              className="mt-6 space-y-4"
              onSubmit={(event) => {
                event.preventDefault()
                onSubmit(nome)
              }}
            >
              <label className="block space-y-2">
                <span className="text-corpo font-medium text-slate-200">Nome</span>
                <input
                  value={nome}
                  onChange={(event) => setNome(event.target.value)}
                  className={selectClasses()}
                  autoFocus
                  data-testid="pedido-medico-nova-especialidade-nome"
                />
              </label>

              {erro ? (
                <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                  {erro}
                </p>
              ) : null}

              <Botao
                type="submit"
                variante="primario"
                className="w-full"
                disabled={salvando || nome.trim() === ''}
                data-testid="pedido-medico-nova-especialidade-salvar"
              >
                {salvando ? 'Salvando...' : 'Salvar'}
              </Botao>
            </form>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}

/** Duas opções de mesmo peso — nenhuma delas some meio-oculta atrás da outra
 *  como "selecionar existente" (um select) vs "novo" (um botão pequeno) hoje. */
function SeletorModo({
  modo,
  onSelecionar,
  rotuloExistente,
  rotuloNovo,
  testIdPrefix,
}: {
  modo: 'existente' | 'novo'
  onSelecionar: (modo: 'existente' | 'novo') => void
  rotuloExistente: string
  rotuloNovo: string
  testIdPrefix: string
}) {
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      {(
        [
          ['existente', rotuloExistente],
          ['novo', rotuloNovo],
        ] as const
      ).map(([valor, rotulo]) => (
        <button
          key={valor}
          type="button"
          onClick={() => onSelecionar(valor)}
          className={`rounded-2xl border px-5 py-4 text-left text-corpo font-semibold transition ${
            modo === valor
              ? 'border-cyan-300/60 bg-cyan-400/15 text-cyan-50'
              : 'border-white/10 bg-white/5 text-slate-200 hover:border-white/20 hover:bg-white/10'
          }`}
          data-testid={`${testIdPrefix}-${valor}`}
        >
          {rotulo}
        </button>
      ))}
    </div>
  )
}

/** Barra de progresso das 6 etapas: clica pra voltar em qualquer uma já
 *  concluída; etapas futuras ficam desabilitadas até a atual estar completa. */
function Etapas({
  passoAtual,
  maxAlcancavel,
  onIr,
}: {
  passoAtual: number
  maxAlcancavel: number
  onIr: (indice: number) => void
}) {
  return (
    <ol className="flex flex-wrap items-center gap-2" data-testid="pedido-medico-etapas">
      {ETAPAS.map((rotulo, indice) => {
        const concluida = indice < passoAtual
        const ativa = indice === passoAtual
        const alcancavel = indice <= maxAlcancavel

        return (
          <li key={rotulo} className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => alcancavel && onIr(indice)}
              disabled={!alcancavel}
              className={`flex items-center gap-2 rounded-full border px-3 py-1.5 text-meta font-semibold transition ${
                ativa
                  ? 'border-cyan-300/60 bg-cyan-400/15 text-cyan-50'
                  : concluida
                    ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100 hover:bg-emerald-400/20'
                    : 'border-white/10 bg-white/5 text-texto-suave'
              } ${alcancavel ? 'cursor-pointer' : 'cursor-not-allowed'}`}
              data-testid={`pedido-medico-etapa-${indice}`}
            >
              <span
                className={`flex size-5 items-center justify-center rounded-full text-meta ${
                  concluida ? 'bg-emerald-400/30' : ativa ? 'bg-cyan-400/30' : 'bg-white/10'
                }`}
              >
                {concluida ? '✓' : indice + 1}
              </span>
              {rotulo}
            </button>
            {indice < ETAPAS.length - 1 ? <span className="h-px w-4 bg-white/10" aria-hidden="true" /> : null}
          </li>
        )
      })}
    </ol>
  )
}

export function LerPedidoMedicoPage() {
  const navigate = useNavigate()
  const [passo, setPasso] = useState(0)
  const [arquivo, setArquivo] = useState<File | null>(null)
  const [resultado, setResultado] = useState<PedidoMedicoAiResult | null>(null)
  const [form, setForm] = useState<SolicitacaoForm>(emptyForm)
  const [createdPacientes, setCreatedPacientes] = useState<PacienteRef[]>([])
  const [createdEspecialidades, setCreatedEspecialidades] = useState<EspecialidadeRef[]>([])
  const [createdMedicos, setCreatedMedicos] = useState<MedicoRef[]>([])
  const [formError, setFormError] = useState<string | null>(null)

  const [pacienteModo, setPacienteModo] = useState<'existente' | 'novo'>('existente')
  const [novoPacienteNome, setNovoPacienteNome] = useState('')
  const [novoPacienteBlocks, setNovoPacienteBlocks] = useState<string[]>([])
  const [novoPacienteCarteirinha, setNovoPacienteCarteirinha] = useState('')
  const [novoPacienteError, setNovoPacienteError] = useState<string | null>(null)

  const [medicoModo, setMedicoModo] = useState<'existente' | 'novo'>('existente')
  const [novoMedicoNome, setNovoMedicoNome] = useState('')
  const [novoMedicoCrm, setNovoMedicoCrm] = useState('')
  const [novoMedicoCrmUf, setNovoMedicoCrmUf] = useState('')
  const [novoMedicoEspecialidade, setNovoMedicoEspecialidade] = useState('')
  const [novoMedicoError, setNovoMedicoError] = useState<string | null>(null)

  const [especialidadeModalAberto, setEspecialidadeModalAberto] = useState(false)
  const [especialidadeNomeInicial, setEspecialidadeNomeInicial] = useState('')
  const [especialidadeError, setEspecialidadeError] = useState<string | null>(null)

  /** Termo de CID sem cadastro parecido que o operador clicou pra cadastrar —
   *  ver CidsCampo. */
  const [cidNovoTermo, setCidNovoTermo] = useState<string | null>(null)

  const conveniosQuery = useConvenios()
  const cidsQuery = useCids()
  const especialidadesQuery = useEspecialidades({ convenio_id: form.convenio_id })
  const medicosQuery = useMedicos()
  const pacientesQuery = usePacientes({ convenio_id: form.convenio_id })
  const profissionaisQuery = useProfissionais()
  const analisarPedido = useAnalisarPedidoMedico()
  const criarSolicitacao = useCriarSolicitacao()
  const criarPaciente = useCriarPacienteRapido()
  const criarEspecialidade = useCriarEspecialidadeRapida()
  const criarMedico = useCriarMedicoRapido()

  const convenios = useMemo(() => conveniosQuery.data ?? emptyArray, [conveniosQuery.data])
  const convenioSelecionado = useMemo(
    () => convenios.find((item) => String(item.id) === form.convenio_id),
    [convenios, form.convenio_id],
  )
  const blocosCarteirinha = convenioSelecionado?.carteirinha_blocos ?? null
  const pacientesBase = useMemo(() => pacientesQuery.data ?? emptyArray, [pacientesQuery.data])
  const especialidadesBase = useMemo(
    () => especialidadesQuery.data ?? emptyArray,
    [especialidadesQuery.data],
  )
  const profissionais = useMemo(
    () => profissionaisQuery.data ?? emptyArray,
    [profissionaisQuery.data],
  )
  const medicosBase = useMemo(() => medicosQuery.data ?? emptyArray, [medicosQuery.data])

  const pacientesSugeridos = useMemo<PacienteRef[]>(
    () =>
      (resultado?.sugestoes.pacientes ?? []).map((item) => ({
        id: item.id,
        nome: item.nome,
        carteirinha: item.carteirinha ?? '',
        convenio_id: Number(form.convenio_id || 0),
      })),
    [form.convenio_id, resultado],
  )
  // As sugestoes vem agrupadas por termo lido; o Select quer uma lista plana.
  // O mergeById adiante cuida das repetidas, que aparecem quando dois termos
  // do documento casam com o mesmo cadastro.
  const especialidadesSugeridas = useMemo<EspecialidadeRef[]>(
    () =>
      (resultado?.sugestoes.especialidades ?? []).flatMap((lida) =>
        lida.matches.map((match) => ({ id: match.id, nome: match.nome })),
      ),
    [resultado],
  )
  const medicosSugeridos = useMemo<MedicoRef[]>(
    () =>
      (resultado?.sugestoes.medicos ?? []).map((item) => ({
        id: item.id,
        nome: item.nome,
        crm: item.crm ?? '',
        crm_uf: item.crm_uf ?? null,
        especialidade_medica: '',
        telefone: '',
        email: null,
        ativo: true,
      })),
    [resultado],
  )

  const pacientes = useMemo(
    () => mergeById(mergeById(pacientesBase, pacientesSugeridos), createdPacientes),
    [createdPacientes, pacientesBase, pacientesSugeridos],
  )
  const especialidades = useMemo(
    () => mergeById(mergeById(especialidadesBase, especialidadesSugeridas), createdEspecialidades),
    [createdEspecialidades, especialidadesBase, especialidadesSugeridas],
  )
  const medicos = useMemo(
    () => mergeById(mergeById(medicosBase, medicosSugeridos), createdMedicos),
    [createdMedicos, medicosBase, medicosSugeridos],
  )

  const pacienteEscolhido = pacientes.find((item) => String(item.id) === form.paciente_id)
  const medicoEscolhido = medicos.find((item) => String(item.id) === form.medico_id)
  const convenioEscolhido = convenios.find((item) => String(item.id) === form.convenio_id)
  const cidsEscolhidos = (cidsQuery.data ?? []).filter((cid) => form.cid_ids.includes(String(cid.id)))
  const itensPreenchidos = form.itens.filter(
    (item) => item.especialidade_id !== '' && item.profissional_id !== '',
  )

  // Uma etapa só libera a próxima quando o que ela pede está decidido — mas
  // dá pra voltar pra qualquer etapa já concluída a qualquer momento.
  const etapaCompleta = [
    resultado !== null,
    form.convenio_id !== '',
    form.paciente_id !== '',
    form.medico_id !== '',
    itensEstaoCompletos(form.itens),
    form.cid_ids.length > 0 && form.solicitado_em !== '',
  ]
  const maxAlcancavel = (() => {
    const primeiraIncompleta = etapaCompleta.findIndex((ok) => !ok)
    return primeiraIncompleta === -1 ? ETAPAS.length - 1 : primeiraIncompleta
  })()

  const extractedPaciente = resultado?.dados.paciente_nome?.trim() ?? ''
  const especialidadesLidas = resultado?.sugestoes.especialidades ?? []
  const extractedMedico = resultado?.dados.medico_nome?.trim() ?? ''

  const irParaEtapa = (indice: number) => {
    if (indice <= maxAlcancavel) {
      setPasso(indice)
    }
  }

  const avancar = () => setPasso((atual) => Math.min(ETAPAS.length - 1, atual + 1))

  const handleAnalyze = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    if (!arquivo) {
      setFormError('Escolha um arquivo PDF, JPG ou PNG.')
      return
    }

    try {
      const data = await analisarPedido.mutateAsync(arquivo)
      const topPaciente = data.sugestoes.pacientes[0]
      const topMedico = data.sugestoes.medicos[0]
      // Uma linha por especialidade lida que casou com um cadastro. As que nao
      // casaram ficam de fora e aparecem na tela como candidatas a cadastro.
      const itensLidos = data.sugestoes.especialidades.reduce(
        (itens, lida) =>
          // So entra sozinha a especialidade que casou com confianca alta.
          // Palpite fraco fica de fora: aplicar "Psicologia" num pedido de
          // "Psicopedagogia" trocaria a terapia do paciente em silencio.
          !lida.sugere_cadastro && lida.matches[0]
            ? comEspecialidadeAdicionada(itens, String(lida.matches[0].id))
            : itens,
        [{ ...emptyItem }] as SolicitacaoFormItem[],
      )
      const observacoes = [
        data.dados.observacoes,
        data.raw_text && !data.dados.observacoes ? data.raw_text : '',
      ]
        .filter(Boolean)
        .join('\n')

      setResultado(data)
      setForm((current) => ({
        ...current,
        // Pré-seleciona quando a IA achou um cadastro com boa similaridade —
        // o operador só confirma na etapa (ou troca) em vez de escolher do zero.
        paciente_id: topPaciente ? String(topPaciente.id) : '',
        itens: itensLidos,
        medico_id: topMedico ? String(topMedico.id) : '',
        solicitado_em: data.dados.solicitado_em || current.solicitado_em,
        observacoes,
        pedido_medico_upload_id: data.upload_id,
        pedido_medico_nome_original: data.arquivo.nome_original,
        pedido_medico_mime: data.arquivo.mime,
        pedido_medico_ai_result: data,
      }))
      setPasso(1)
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível ler o pedido médico.'))
    }
  }

  const handleCriarPaciente = async () => {
    setNovoPacienteError(null)

    if (!form.convenio_id) {
      setNovoPacienteError('Selecione um convênio antes de cadastrar o paciente.')
      return
    }

    try {
      const paciente = await criarPaciente.mutateAsync({
        nome: novoPacienteNome.trim(),
        convenio_id: Number(form.convenio_id),
        carteirinha: novoPacienteCarteirinha.trim(),
      })
      setCreatedPacientes((current) => [...current, paciente])
      setForm((current) => ({ ...current, paciente_id: String(paciente.id) }))
      setNovoPacienteNome('')
      setNovoPacienteBlocks([])
      setNovoPacienteCarteirinha('')
      setPacienteModo('existente')
      avancar()
    } catch (error) {
      setNovoPacienteError(getHttpErrorMessage(error, 'Não foi possível cadastrar o paciente.'))
    }
  }

  const handleCriarMedico = async () => {
    setNovoMedicoError(null)

    try {
      const medico = await criarMedico.mutateAsync({
        nome: novoMedicoNome.trim(),
        crm: novoMedicoCrm.trim() || undefined,
        crm_uf: novoMedicoCrmUf.trim() || undefined,
        especialidade_medica: novoMedicoEspecialidade.trim() || undefined,
      })
      setCreatedMedicos((current) => [...current, medico])
      setForm((current) => ({ ...current, medico_id: String(medico.id) }))
      setNovoMedicoNome('')
      setNovoMedicoCrm('')
      setNovoMedicoCrmUf('')
      setNovoMedicoEspecialidade('')
      setMedicoModo('existente')
      avancar()
    } catch (error) {
      setNovoMedicoError(getHttpErrorMessage(error, 'Não foi possível cadastrar o médico.'))
    }
  }

  const abrirNovaEspecialidade = (nomeInicial: string) => {
    setEspecialidadeError(null)
    setEspecialidadeNomeInicial(nomeInicial)
    setEspecialidadeModalAberto(true)
  }

  const handleCriarEspecialidade = async (nome: string) => {
    setEspecialidadeError(null)

    try {
      const especialidade = await criarEspecialidade.mutateAsync({ nome: nome.trim() })
      setCreatedEspecialidades((current) => [...current, especialidade])
      setForm((current) => ({
        ...current,
        itens: comEspecialidadeAdicionada(current.itens, String(especialidade.id)),
      }))
      setEspecialidadeModalAberto(false)
    } catch (error) {
      setEspecialidadeError(getHttpErrorMessage(error, 'Não foi possível salvar a especialidade.'))
    }
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFormError(null)

    try {
      await criarSolicitacao.mutateAsync(form)
      navigate('/solicitacoes')
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível criar a solicitação.'))
    }
  }

  return (
    <div className="space-y-8" data-testid="ler-pedido-medico-page">
      <section className="space-y-4">
        <div className="flex flex-col gap-4 sm:items-start lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Solicitações</p>
            <h2 className="mt-2 text-display font-semibold text-white">Ler pedido médico</h2>
          </div>
          <Botao type="button" variante="secundario" onClick={() => navigate('/solicitacoes')}>
            Voltar
          </Botao>
        </div>

        {resultado ? <Etapas passoAtual={passo} maxAlcancavel={maxAlcancavel} onIr={irParaEtapa} /> : null}
      </section>

      {/* Etapa 0 — Upload */}
      {passo === 0 ? (
        <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <h3 className="text-subtitulo font-semibold text-white">Envie o pedido médico</h3>
          <p className="mt-1 text-corpo text-slate-300">
            PDF, JPG ou PNG. A IA lê o documento e já sugere paciente, médico e especialidades nas
            próximas etapas.
          </p>
          <form onSubmit={handleAnalyze} className="mt-4 grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Arquivo do pedido médico</span>
              <input
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                onChange={(event) => setArquivo(event.target.files?.[0] ?? null)}
                className="inline-flex items-center justify-center w-full rounded-2xl border border-white/10 bg-white/5 h-10 px-4 text-corpo text-white file:mr-4 file:rounded-xl file:border-0 file:bg-cyan-400 file:px-3 file:py-2 file:text-corpo file:font-semibold file:text-slate-950"
                data-testid="pedido-medico-arquivo"
              />
            </label>
            <Botao
              type="submit"
              variante="primario"
              disabled={analisarPedido.isPending}
              data-testid="pedido-medico-analisar"
            >
              {analisarPedido.isPending ? 'Lendo...' : 'Ler pedido'}
            </Botao>
          </form>

          {formError ? (
            <p className="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
              {formError}
            </p>
          ) : null}
        </section>
      ) : null}

      {/* Etapa 1 — Convênio */}
      {passo === 1 && resultado ? (
        <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <h3 className="text-subtitulo font-semibold text-white">Qual convênio?</h3>
          <p className="mt-1 text-corpo text-slate-300">
            Define o formato da carteirinha e as regras de autorização das próximas etapas.
          </p>

          <label className="mt-4 block space-y-2">
            <span className="text-corpo font-medium text-slate-200">Convênio</span>
            <Select
              value={form.convenio_id}
              onChange={(event) =>
                setForm((current) => ({ ...current, convenio_id: event.target.value, paciente_id: '' }))
              }
              className={selectClasses()}
              disabled={conveniosQuery.isLoading}
              data-testid="pedido-medico-convenio"
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

          <div className="mt-6 flex justify-end">
            <Botao
              type="button"
              variante="primario"
              disabled={!etapaCompleta[1]}
              onClick={avancar}
              data-testid="pedido-medico-etapa1-continuar"
            >
              Continuar
            </Botao>
          </div>
        </section>
      ) : null}

      {/* Etapa 2 — Paciente */}
      {passo === 2 && resultado ? (
        <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6 space-y-4">
          <div>
            <h3 className="text-subtitulo font-semibold text-white">Paciente</h3>
            {extractedPaciente ? (
              <p className="mt-2 text-corpo text-slate-300">
                Nome lido no documento:{' '}
                <span className="text-titulo font-bold text-white">{extractedPaciente}</span>
              </p>
            ) : (
              <p className="mt-1 text-corpo text-slate-300">
                Nenhum nome de paciente identificado no documento.
              </p>
            )}
          </div>

          <SeletorModo
            modo={pacienteModo}
            onSelecionar={(modo) => {
              setPacienteModo(modo)
              if (modo === 'novo' && novoPacienteNome.trim() === '') {
                setNovoPacienteNome(extractedPaciente)
              }
            }}
            rotuloExistente="Selecionar paciente existente"
            rotuloNovo="Cadastrar novo paciente"
            testIdPrefix="pedido-medico-paciente-modo"
          />

          {pacienteModo === 'existente' ? (
            <div className="space-y-3">
              {resultado.sugestoes.pacientes.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {resultado.sugestoes.pacientes.map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => setForm((current) => ({ ...current, paciente_id: String(item.id) }))}
                      className={`rounded-full border px-3 py-1.5 text-meta font-semibold transition ${
                        form.paciente_id === String(item.id)
                          ? 'border-cyan-300/60 bg-cyan-400/25 text-cyan-50'
                          : 'border-cyan-400/30 bg-cyan-400/10 text-cyan-100 hover:bg-cyan-400/20'
                      }`}
                    >
                      {suggestionTitle(item)} · {item.similaridade}%
                    </button>
                  ))}
                </div>
              ) : null}

              <Select
                value={form.paciente_id}
                onChange={(event) => setForm((current) => ({ ...current, paciente_id: event.target.value }))}
                className={selectClasses()}
                disabled={pacientesQuery.isLoading || pacientes.length === 0}
                data-testid="pedido-medico-paciente"
              >
                <option value="" disabled>
                  Selecione
                </option>
                {pacientes.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.nome}
                    {item.carteirinha ? ` · ${formatCarteirinha(item.carteirinha)}` : ''}
                  </option>
                ))}
              </Select>

              {pacienteEscolhido ? (
                <p className="text-meta text-emerald-300">Selecionado: {pacienteEscolhido.nome}</p>
              ) : null}
            </div>
          ) : (
            <div className="space-y-4 rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
              <label className="block space-y-2">
                <span className="text-corpo font-medium text-slate-200">Nome</span>
                <input
                  value={novoPacienteNome}
                  onChange={(event) => setNovoPacienteNome(event.target.value)}
                  className={selectClasses()}
                  data-testid="pedido-medico-novo-paciente-nome"
                />
              </label>

              <div className="space-y-2">
                <span className="text-corpo font-medium text-slate-200">Carteirinha</span>
                {blocosCarteirinha ? (
                  <CarteirinhaBlocosInput
                    blocos={blocosCarteirinha}
                    blocks={novoPacienteBlocks}
                    onChange={(blocks, carteirinha) => {
                      setNovoPacienteBlocks(blocks)
                      setNovoPacienteCarteirinha(carteirinha)
                    }}
                    testIdPrefix="pedido-medico-novo-paciente-carteirinha-blocos"
                  />
                ) : (
                  <input
                    value={novoPacienteCarteirinha}
                    onChange={(event) => setNovoPacienteCarteirinha(event.target.value)}
                    className={selectClasses()}
                    data-testid="pedido-medico-novo-paciente-carteirinha"
                  />
                )}
              </div>

              {novoPacienteError ? (
                <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                  {novoPacienteError}
                </p>
              ) : null}

              <Botao
                type="button"
                variante="primario"
                className="w-full"
                disabled={
                  criarPaciente.isPending ||
                  novoPacienteNome.trim() === '' ||
                  (blocosCarteirinha
                    ? !isCarteirinhaCompleta(novoPacienteBlocks, blocosCarteirinha)
                    : novoPacienteCarteirinha.trim() === '')
                }
                onClick={() => void handleCriarPaciente()}
                data-testid="pedido-medico-novo-paciente-salvar"
              >
                {criarPaciente.isPending ? 'Salvando...' : 'Criar e continuar'}
              </Botao>
            </div>
          )}

          {pacienteModo === 'existente' ? (
            <div className="flex justify-end">
              <Botao
                type="button"
                variante="primario"
                disabled={!etapaCompleta[2]}
                onClick={avancar}
                data-testid="pedido-medico-etapa2-continuar"
              >
                Confirmar e continuar
              </Botao>
            </div>
          ) : null}
        </section>
      ) : null}

      {/* Etapa 3 — Médico solicitante */}
      {passo === 3 && resultado ? (
        <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6 space-y-4">
          <div>
            <h3 className="text-subtitulo font-semibold text-white">Médico solicitante</h3>
            {extractedMedico ? (
              <p className="mt-2 text-corpo text-slate-300">
                Nome lido no documento:{' '}
                <span className="text-titulo font-bold text-white">{extractedMedico}</span>
              </p>
            ) : (
              <p className="mt-1 text-corpo text-slate-300">
                Nenhum nome de médico identificado no documento.
              </p>
            )}
          </div>

          <SeletorModo
            modo={medicoModo}
            onSelecionar={(modo) => {
              setMedicoModo(modo)
              if (modo === 'novo' && novoMedicoNome.trim() === '') {
                setNovoMedicoNome(extractedMedico)
                setNovoMedicoCrm(resultado.dados.medico_crm?.trim() ?? '')
                setNovoMedicoCrmUf(resultado.dados.medico_crm_uf?.trim().toUpperCase() ?? '')
                setNovoMedicoEspecialidade(resultado.dados.medico_especialidade?.trim() ?? '')
              }
            }}
            rotuloExistente="Selecionar médico existente"
            rotuloNovo="Cadastrar novo médico"
            testIdPrefix="pedido-medico-medico-modo"
          />

          {medicoModo === 'existente' ? (
            <div className="space-y-3">
              {resultado.sugestoes.medicos.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {resultado.sugestoes.medicos.map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => setForm((current) => ({ ...current, medico_id: String(item.id) }))}
                      className={`rounded-full border px-3 py-1.5 text-meta font-semibold transition ${
                        form.medico_id === String(item.id)
                          ? 'border-cyan-300/60 bg-cyan-400/25 text-cyan-50'
                          : 'border-cyan-400/30 bg-cyan-400/10 text-cyan-100 hover:bg-cyan-400/20'
                      }`}
                    >
                      {suggestionTitle(item)} · {item.similaridade}%
                    </button>
                  ))}
                </div>
              ) : null}

              <Select
                value={form.medico_id}
                onChange={(event) => setForm((current) => ({ ...current, medico_id: event.target.value }))}
                className={selectClasses()}
                disabled={medicosQuery.isLoading || medicos.length === 0}
                data-testid="pedido-medico-medico"
              >
                <option value="" disabled>
                  Selecione
                </option>
                {medicos.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.nome}
                    {item.crm ? ` · ${item.crm}${item.crm_uf ? `/${item.crm_uf}` : ''}` : ''}
                  </option>
                ))}
              </Select>

              {medicoEscolhido ? (
                <p className="text-meta text-emerald-300">Selecionado: {medicoEscolhido.nome}</p>
              ) : null}
            </div>
          ) : (
            <div className="space-y-4 rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
              <label className="block space-y-2">
                <span className="text-corpo font-medium text-slate-200">Nome</span>
                <input
                  value={novoMedicoNome}
                  onChange={(event) => setNovoMedicoNome(event.target.value)}
                  className={selectClasses()}
                  data-testid="pedido-medico-novo-medico-nome"
                />
              </label>

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="flex gap-3">
                  <label className="flex-1 space-y-2">
                    <span className="text-corpo font-medium text-slate-200">CRM</span>
                    <input
                      value={novoMedicoCrm}
                      onChange={(event) => setNovoMedicoCrm(event.target.value.replace(/\D/g, ''))}
                      inputMode="numeric"
                      placeholder="Somente números"
                      className={selectClasses()}
                      data-testid="pedido-medico-novo-medico-crm"
                    />
                  </label>
                  <label className="w-24 space-y-2">
                    <span className="text-corpo font-medium text-slate-200">UF</span>
                    <input
                      value={novoMedicoCrmUf}
                      onChange={(event) =>
                        setNovoMedicoCrmUf(event.target.value.replace(/[^a-zA-Z]/g, '').slice(0, 2).toUpperCase())
                      }
                      maxLength={2}
                      placeholder="SC"
                      className={selectClasses()}
                      data-testid="pedido-medico-novo-medico-crm-uf"
                    />
                  </label>
                </div>
                <span className="block text-meta text-slate-400 sm:col-span-2">
                  Opcional aqui — dá pra completar depois em Cadastros → Médicos.
                </span>
                <label className="block space-y-2">
                  <span className="text-corpo font-medium text-slate-200">Especialidade médica</span>
                  <input
                    value={novoMedicoEspecialidade}
                    onChange={(event) => setNovoMedicoEspecialidade(event.target.value)}
                    placeholder="Pediatria"
                    className={selectClasses()}
                    data-testid="pedido-medico-novo-medico-especialidade"
                  />
                </label>
              </div>

              {novoMedicoError ? (
                <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                  {novoMedicoError}
                </p>
              ) : null}

              <Botao
                type="button"
                variante="primario"
                className="w-full"
                disabled={criarMedico.isPending || novoMedicoNome.trim() === ''}
                onClick={() => void handleCriarMedico()}
                data-testid="pedido-medico-novo-medico-salvar"
              >
                {criarMedico.isPending ? 'Salvando...' : 'Criar e continuar'}
              </Botao>
            </div>
          )}

          {medicoModo === 'existente' ? (
            <div className="flex justify-end">
              <Botao
                type="button"
                variante="primario"
                disabled={!etapaCompleta[3]}
                onClick={avancar}
                data-testid="pedido-medico-etapa3-continuar"
              >
                Confirmar e continuar
              </Botao>
            </div>
          ) : null}
        </section>
      ) : null}

      {/* Etapa 4 — Especialidades / Itens */}
      {passo === 4 && resultado ? (
        <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6 space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 className="text-subtitulo font-semibold text-white">Especialidades do pedido</h3>
              <p className="mt-1 text-corpo text-slate-300">
                {especialidadesLidas.length > 0
                  ? `${especialidadesLidas.length} lida${especialidadesLidas.length > 1 ? 's' : ''} no documento`
                  : 'Nenhuma especialidade identificada no documento — acrescente manualmente abaixo.'}
              </p>
            </div>
            <Botao
              type="button"
              variante="secundario"
              tamanho="sm"
              onClick={() => abrirNovaEspecialidade('')}
            >
              Nova especialidade
            </Botao>
          </div>

          {especialidadesLidas.length > 0 ? (
            <div className="space-y-2 rounded-superficie border border-linha bg-fundo p-3 shadow-e1">
              {especialidadesLidas.map((lida) => {
                const jaNoPedido = lida.matches.some((match) =>
                  form.itens.some((item) => item.especialidade_id === String(match.id)),
                )

                return (
                  <div
                    key={lida.termo}
                    className="flex flex-wrap items-center gap-2"
                    data-testid={`pedido-medico-especialidade-lida-${lida.termo}`}
                  >
                    <span className="text-meta text-slate-300">
                      {lida.termo}
                      {jaNoPedido ? <span className="ml-1 text-emerald-300">no pedido</span> : null}
                    </span>

                    {lida.sugere_cadastro ? (
                      <button
                        type="button"
                        onClick={() => abrirNovaEspecialidade(lida.termo)}
                        className="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1.5 text-meta font-semibold text-amber-100 transition hover:bg-amber-400/20"
                        data-testid={`pedido-medico-criar-especialidade-${lida.termo}`}
                      >
                        cadastrar "{lida.termo}"
                      </button>
                    ) : null}

                    {lida.matches.length > 0
                      ? lida.matches.map((match) => (
                          <button
                            key={match.id}
                            type="button"
                            onClick={() =>
                              setForm((current) => ({
                                ...current,
                                itens: comEspecialidadeAdicionada(current.itens, String(match.id)),
                              }))
                            }
                            className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                            title="Acrescenta esta especialidade ao pedido"
                          >
                            {match.nome} · {match.similaridade}%
                          </button>
                        ))
                      : null}
                  </div>
                )
              })}
            </div>
          ) : null}

          <SolicitacaoItensFields
            itens={form.itens}
            onChange={(itens) => setForm((current) => ({ ...current, itens }))}
            especialidades={especialidades}
            profissionais={profissionais}
            disabled={especialidadesQuery.isLoading || profissionaisQuery.isLoading}
          />

          <div className="flex justify-end">
            <Botao
              type="button"
              variante="primario"
              disabled={!etapaCompleta[4]}
              onClick={avancar}
              data-testid="pedido-medico-etapa4-continuar"
            >
              Continuar
            </Botao>
          </div>
        </section>
      ) : null}

      {/* Etapa 5 — CID, Data, Observações e Revisão final */}
      {passo === 5 && resultado ? (
        <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <form onSubmit={handleSubmit} className="space-y-5" data-testid="pedido-medico-form">
            <div>
              <h3 className="text-subtitulo font-semibold text-white">Últimos dados e revisão</h3>
              <p className="mt-1 text-corpo text-slate-300">
                Arquivo: {resultado.arquivo.nome_original} · Modelo: {resultado.model}
              </p>
            </div>

            <div className="space-y-3">
              <span className="block text-corpo font-medium text-slate-200">CID</span>

              {resultado.sugestoes.cids.length > 0 ? (
                <div className="space-y-2 rounded-superficie border border-linha bg-fundo p-3 shadow-e1">
                  {resultado.sugestoes.cids.map((lido) => {
                    const jaNoPedido = lido.matches.some((match) =>
                      form.cid_ids.includes(String(match.id)),
                    )

                    return (
                      <div
                        key={lido.termo}
                        className="flex flex-wrap items-center gap-2"
                        data-testid={`pedido-medico-cid-lido-${lido.termo}`}
                      >
                        <span className="text-meta text-slate-300">
                          {lido.termo}
                          {jaNoPedido ? <span className="ml-1 text-emerald-300">no pedido</span> : null}
                        </span>

                        {lido.sugere_cadastro ? (
                          <button
                            type="button"
                            onClick={() => setCidNovoTermo(lido.termo)}
                            className="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1.5 text-meta font-semibold text-amber-100 transition hover:bg-amber-400/20"
                            data-testid={`pedido-medico-criar-cid-${lido.termo}`}
                          >
                            cadastrar "{lido.termo}"
                          </button>
                        ) : null}

                        {lido.matches.length > 0
                          ? lido.matches.map((match) => (
                              <button
                                key={match.id}
                                type="button"
                                onClick={() =>
                                  setForm((current) => ({
                                    ...current,
                                    cid_ids: current.cid_ids.includes(String(match.id))
                                      ? current.cid_ids
                                      : [...current.cid_ids, String(match.id)],
                                  }))
                                }
                                className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                                title="Acrescenta este CID ao pedido"
                              >
                                {match.codigo} · {match.similaridade}%
                              </button>
                            ))
                          : null}
                      </div>
                    )
                  })}
                </div>
              ) : null}

              <CidsCampo
                value={form.cid_ids}
                onChange={(cidIds) => setForm((current) => ({ ...current, cid_ids: cidIds }))}
                testIdPrefix="pedido-medico-cid"
                abrirNovoComTermo={cidNovoTermo}
                onTermoConsumido={() => setCidNovoTermo(null)}
              />
            </div>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Data da solicitação</span>
              <input
                type="date"
                value={form.solicitado_em}
                onChange={(event) => setForm((current) => ({ ...current, solicitado_em: event.target.value }))}
                className={selectClasses()}
                data-testid="pedido-medico-data"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-corpo font-medium text-slate-200">Observações</span>
              <textarea
                value={form.observacoes}
                onChange={(event) => setForm((current) => ({ ...current, observacoes: event.target.value }))}
                className={`${selectClasses()} min-h-32`}
                data-testid="pedido-medico-observacoes"
              />
            </label>

            {/*
              Pré-visualização de verdade, não só uma contagem: mostra cada
              dado exatamente como vai ficar gravado, pra revisar antes de
              criar a solicitação de fato — inclusive o que ainda falta
              (itens sem profissional, por exemplo) fica visível aqui. Fica
              por último, logo antes do botão, porque só faz sentido depois
              de CID/Data/Observações já preenchidos acima.
            */}
            <div
              className="space-y-4 rounded-2xl border border-cyan-300/20 bg-cyan-400/5 p-5"
              data-testid="pedido-medico-preview"
            >
              <p className="text-meta font-semibold uppercase tracking-[0.25em] text-cyan-300/80">
                Pré-visualização da solicitação
              </p>

              <div className="grid gap-3 text-corpo text-slate-200 sm:grid-cols-2">
                <p>
                  <span className="block text-meta uppercase tracking-wide text-slate-400">Convênio</span>
                  {convenioEscolhido?.nome ?? '—'}
                </p>
                <p>
                  <span className="block text-meta uppercase tracking-wide text-slate-400">Paciente</span>
                  {pacienteEscolhido?.nome ?? '—'}
                  {pacienteEscolhido?.carteirinha ? ` · ${formatCarteirinha(pacienteEscolhido.carteirinha)}` : ''}
                </p>
                <p>
                  <span className="block text-meta uppercase tracking-wide text-slate-400">
                    Médico solicitante
                  </span>
                  {medicoEscolhido?.nome ?? '—'}
                  {medicoEscolhido?.crm
                    ? ` · CRM ${medicoEscolhido.crm}${medicoEscolhido.crm_uf ? `/${medicoEscolhido.crm_uf}` : ''}`
                    : ''}
                </p>
                <p>
                  <span className="block text-meta uppercase tracking-wide text-slate-400">
                    Data da solicitação
                  </span>
                  {form.solicitado_em || '—'}
                </p>
              </div>

              <div>
                <span className="block text-meta uppercase tracking-wide text-slate-400">CIDs</span>
                {cidsEscolhidos.length > 0 ? (
                  <div className="mt-1 flex flex-wrap gap-2">
                    {cidsEscolhidos.map((cid) => (
                      <span
                        key={cid.id}
                        className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-meta font-semibold text-cyan-100"
                      >
                        {cid.codigo} — {cid.descricao}
                      </span>
                    ))}
                  </div>
                ) : (
                  <p className="mt-1 text-corpo text-rose-200">Nenhum CID selecionado ainda.</p>
                )}
              </div>

              <div>
                <span className="block text-meta uppercase tracking-wide text-slate-400">
                  Especialidades solicitadas
                </span>
                {itensPreenchidos.length > 0 ? (
                  <div className="mt-1 overflow-hidden rounded-2xl border border-white/10">
                    <table className="w-full border-collapse text-left text-corpo" data-cartoes="md">
                      <thead className="bg-white/5 text-meta uppercase tracking-wide text-slate-400">
                        <tr>
                          <th className="px-3 py-2">Especialidade</th>
                          <th className="px-3 py-2">Profissional</th>
                          <th className="px-3 py-2">Sessões</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-white/10">
                        {itensPreenchidos.map((item, index) => (
                          <tr key={`${item.especialidade_id}-${index}`}>
                            <td data-rotulo="Especialidade" className="px-3 py-2 text-slate-200">
                              {especialidades.find((esp) => String(esp.id) === item.especialidade_id)?.nome ?? '—'}
                            </td>
                            <td data-rotulo="Profissional" className="px-3 py-2 text-slate-200">
                              {profissionais.find((prof) => String(prof.id) === item.profissional_id)?.nome ?? '—'}
                            </td>
                            <td data-rotulo="Sessões" className="px-3 py-2 text-slate-200">{item.quantidade || 10}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <p className="mt-1 text-corpo text-rose-200">Nenhuma especialidade completa ainda.</p>
                )}
              </div>

              {form.observacoes ? (
                <div>
                  <span className="block text-meta uppercase tracking-wide text-slate-400">Observações</span>
                  <p className="mt-1 whitespace-pre-wrap text-corpo text-slate-200">{form.observacoes}</p>
                </div>
              ) : null}
            </div>

            {formError ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                {formError}
              </p>
            ) : null}

            <Botao
              type="submit"
              variante="primario"
              className="w-full"
              disabled={criarSolicitacao.isPending || !etapaCompleta.every(Boolean)}
              data-testid="pedido-medico-submit"
            >
              {criarSolicitacao.isPending ? 'Salvando...' : 'Criar solicitação'}
            </Botao>
          </form>
        </section>
      ) : null}

      <NovaEspecialidadeModal
        aberto={especialidadeModalAberto}
        nomeInicial={especialidadeNomeInicial}
        salvando={criarEspecialidade.isPending}
        erro={especialidadeError}
        onClose={() => setEspecialidadeModalAberto(false)}
        onSubmit={(nome) => void handleCriarEspecialidade(nome)}
      />
    </div>
  )
}

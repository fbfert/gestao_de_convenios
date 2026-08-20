import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { Select } from '../../components/ui/Select'
import {
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

const emptyArray: never[] = []

const emptyForm: SolicitacaoForm = {
  paciente_id: '',
  convenio_id: '',
  medico_id: '',
  cid: '',
  solicitado_em: new Date().toISOString().slice(0, 10),
  observacoes: '',
  itens: [{ ...emptyItem }],
}

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

type QuickModalKind = 'paciente' | 'especialidade' | 'medico'

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
  const extra = suggestion.carteirinha || suggestion.crm
  return extra ? `${suggestion.nome} · ${extra}` : suggestion.nome
}

function QuickCreateModal({
  kind,
  initialName,
  isSaving,
  error,
  blocos,
  onClose,
  onSubmit,
}: {
  kind: QuickModalKind | null
  initialName: string
  isSaving: boolean
  error: string | null
  /** Formato da carteirinha do convenio, ou null para texto livre. */
  blocos: number[] | null
  onClose: () => void
  onSubmit: (nome: string, carteirinha: string) => void
}) {
  const [nome, setNome] = useState(initialName)
  const [carteirinha, setCarteirinha] = useState('')
  const [blocks, setBlocks] = useState<string[]>([])
  const labels = {
    paciente: 'Novo paciente',
    especialidade: 'Nova especialidade',
    medico: 'Novo médico',
  }

  useEffect(() => {
    setNome(initialName)
    setCarteirinha('')
    setBlocks([])
  }, [initialName, kind])

  if (!kind) {
    return null
  }

  const exigeCarteirinha = kind === 'paciente'
  const carteirinhaOk = !exigeCarteirinha
    || (blocos ? isCarteirinhaCompleta(blocks, blocos) : carteirinha.trim() !== '')

  return (
    <Dialog open={kind !== null} onClose={onClose} className="relative z-50">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel className="w-full max-w-lg rounded-[2rem] border border-white/10 bg-slate-950 p-6 text-white shadow-2xl shadow-black/60">
            <div className="flex items-start justify-between gap-4">
              <DialogTitle className="text-xl font-semibold">{labels[kind]}</DialogTitle>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full border border-white/10 bg-white/5 px-3 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
              >
                Fechar
              </button>
            </div>

            <form
              className="mt-6 space-y-4"
              onSubmit={(event) => {
                event.preventDefault()
                onSubmit(nome, carteirinha)
              }}
            >
              <label className="block space-y-2">
                <span className="text-sm font-medium text-slate-200">Nome</span>
                <input
                  value={nome}
                  onChange={(event) => setNome(event.target.value)}
                  className={selectClasses()}
                  autoFocus
                />
              </label>

              {exigeCarteirinha ? (
                <div className="space-y-2">
                  <span className="text-sm font-medium text-slate-200">Carteirinha</span>
                  {blocos ? (
                    <CarteirinhaBlocosInput
                      blocos={blocos}
                      blocks={blocks}
                      onChange={(next, valor) => {
                        setBlocks(next)
                        setCarteirinha(valor)
                      }}
                      testIdPrefix="pedido-medico-carteirinha-blocos"
                    />
                  ) : (
                    <input
                      value={carteirinha}
                      onChange={(event) => setCarteirinha(event.target.value)}
                      className={selectClasses()}
                      data-testid="pedido-medico-carteirinha"
                    />
                  )}
                </div>
              ) : null}

              {error ? (
                <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                  {error}
                </p>
              ) : null}

              <Botao
                type="submit"
                variante="primario"
                className="w-full"
                disabled={isSaving || nome.trim() === '' || !carteirinhaOk}
              >
                {isSaving ? 'Salvando...' : 'Salvar'}
              </Botao>
            </form>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}

export function LerPedidoMedicoPage() {
  const navigate = useNavigate()
  const [arquivo, setArquivo] = useState<File | null>(null)
  const [resultado, setResultado] = useState<PedidoMedicoAiResult | null>(null)
  const [form, setForm] = useState<SolicitacaoForm>(emptyForm)
  const [createdPacientes, setCreatedPacientes] = useState<PacienteRef[]>([])
  const [createdEspecialidades, setCreatedEspecialidades] = useState<EspecialidadeRef[]>([])
  const [createdMedicos, setCreatedMedicos] = useState<MedicoRef[]>([])
  const [quickModal, setQuickModal] = useState<QuickModalKind | null>(null)
  const [quickInitialName, setQuickInitialName] = useState('')
  const [quickError, setQuickError] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const conveniosQuery = useConvenios()
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

  const formIsComplete =
    form.convenio_id !== '' &&
    form.paciente_id !== '' &&
    form.medico_id !== '' &&
    form.solicitado_em !== '' &&
    itensEstaoCompletos(form.itens) &&
    resultado !== null

  const openQuickModal = (kind: QuickModalKind, initialName = '') => {
    setQuickError(null)
    setQuickInitialName(initialName)
    setQuickModal(kind)
  }

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
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível ler o pedido médico.'))
    }
  }

  const handleQuickSubmit = async (nome: string, carteirinha: string) => {
    const trimmed = nome.trim()
    setQuickError(null)

    try {
      if (quickModal === 'paciente') {
        if (!form.convenio_id) {
          setQuickError('Selecione um convênio antes de criar o paciente.')
          return
        }
        const paciente = await criarPaciente.mutateAsync({
          nome: trimmed,
          convenio_id: Number(form.convenio_id),
          carteirinha: carteirinha.trim(),
        })
        setCreatedPacientes((current) => [...current, paciente])
        setForm((current) => ({ ...current, paciente_id: String(paciente.id) }))
      }

      if (quickModal === 'especialidade') {
        const especialidade = await criarEspecialidade.mutateAsync({ nome: trimmed })
        setCreatedEspecialidades((current) => [...current, especialidade])
        setForm((current) => ({
          ...current,
          itens: comEspecialidadeAdicionada(current.itens, String(especialidade.id)),
        }))
      }

      if (quickModal === 'medico') {
        const medico = await criarMedico.mutateAsync({ nome: trimmed })
        setCreatedMedicos((current) => [...current, medico])
        setForm((current) => ({ ...current, medico_id: String(medico.id) }))
      }

      setQuickModal(null)
    } catch (error) {
      setQuickError(getHttpErrorMessage(error, 'Não foi possível salvar o cadastro rápido.'))
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

  const extractedPaciente = resultado?.dados.paciente_nome?.trim() ?? ''
  const especialidadesLidas = resultado?.sugestoes.especialidades ?? []
  const extractedMedico = resultado?.dados.medico_nome?.trim() ?? ''

  return (
    <div className="space-y-8" data-testid="ler-pedido-medico-page">
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Solicitações</p>
            <h2 className="mt-2 text-3xl font-semibold text-white">Ler pedido médico</h2>
          </div>
          <Botao type="button" variante="secundario" onClick={() => navigate('/solicitacoes')}>
            Voltar
          </Botao>
        </div>
      </section>

      <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <form onSubmit={handleAnalyze} className="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Arquivo do pedido médico</span>
            <input
              type="file"
              accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
              onChange={(event) => setArquivo(event.target.files?.[0] ?? null)}
              className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white file:mr-4 file:rounded-xl file:border-0 file:bg-cyan-400 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-950"
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
      </section>

      {resultado ? (
        <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <form onSubmit={handleSubmit} className="space-y-5" data-testid="pedido-medico-form">
            <div>
              <h3 className="text-lg font-semibold text-white">Revisar nova solicitação</h3>
              <p className="mt-1 text-sm text-slate-300">
                Arquivo: {resultado.arquivo.nome_original} · Modelo: {resultado.model}
              </p>
            </div>

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

            <div className="space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm font-medium text-slate-200">
                  Paciente {extractedPaciente ? `lido: ${extractedPaciente}` : ''}
                </p>
                <Botao type="button" variante="secundario" tamanho="sm" onClick={() => openQuickModal('paciente', extractedPaciente)}>
                  Novo paciente
                </Botao>
              </div>
              {resultado.sugestoes.pacientes.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {resultado.sugestoes.pacientes.map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => setForm((current) => ({ ...current, paciente_id: String(item.id) }))}
                      className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
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
            </div>

            <div className="space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm font-medium text-slate-200">
                  Médico solicitante {extractedMedico ? `lido: ${extractedMedico}` : ''}
                </p>
                <Botao type="button" variante="secundario" tamanho="sm" onClick={() => openQuickModal('medico', extractedMedico)}>
                  Novo médico
                </Botao>
              </div>
              {resultado.sugestoes.medicos.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {resultado.sugestoes.medicos.map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => setForm((current) => ({ ...current, medico_id: String(item.id) }))}
                      className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
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
                    {item.crm ? ` · ${item.crm}` : ''}
                  </option>
                ))}
              </Select>
            </div>

            <div className="space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm font-medium text-slate-200">
                  Especialidades do pedido
                  {especialidadesLidas.length > 0
                    ? ` · ${especialidadesLidas.length} lida${especialidadesLidas.length > 1 ? 's' : ''} no documento`
                    : ''}
                </p>
                <Botao type="button" variante="secundario" tamanho="sm" onClick={() => openQuickModal('especialidade', '')}>
                  Nova especialidade
                </Botao>
              </div>

              {/*
                Um bloco por especialidade lida. Quem casou com cadastro vira
                atalho para aplicar; quem nao casou vira convite a cadastrar,
                que e o caso de um pedido com terapia que a clinica ainda nao
                tem registrada.
              */}
              {especialidadesLidas.length > 0 ? (
                <div className="space-y-2 rounded-2xl border border-white/10 bg-white/5 p-3">
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
                        <span className="text-xs text-slate-300">
                          {lida.termo}
                          {jaNoPedido ? (
                            <span className="ml-1 text-emerald-300">no pedido</span>
                          ) : null}
                        </span>

                        {lida.sugere_cadastro ? (
                          <button
                            type="button"
                            onClick={() => openQuickModal('especialidade', lida.termo)}
                            className="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1.5 text-xs font-semibold text-amber-100 transition hover:bg-amber-400/20"
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
                              className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
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
            </div>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Data da solicitação</span>
              <input
                type="date"
                value={form.solicitado_em}
                onChange={(event) => setForm((current) => ({ ...current, solicitado_em: event.target.value }))}
                className={selectClasses()}
                data-testid="pedido-medico-data"
              />
            </label>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Observações</span>
              <textarea
                value={form.observacoes}
                onChange={(event) => setForm((current) => ({ ...current, observacoes: event.target.value }))}
                className={`${selectClasses()} min-h-32`}
                data-testid="pedido-medico-observacoes"
              />
            </label>

            {formError ? (
              <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                {formError}
              </p>
            ) : null}

            <Botao
              type="submit"
              variante="primario"
              className="w-full"
              disabled={criarSolicitacao.isPending || !formIsComplete}
              data-testid="pedido-medico-submit"
            >
              {criarSolicitacao.isPending ? 'Salvando...' : 'Criar solicitação'}
            </Botao>
          </form>
        </section>
      ) : null}

      {formError && !resultado ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
          {formError}
        </p>
      ) : null}

      <QuickCreateModal
        kind={quickModal}
        initialName={quickInitialName}
        isSaving={criarPaciente.isPending || criarEspecialidade.isPending || criarMedico.isPending}
        error={quickError}
        blocos={convenioSelecionado?.carteirinha_blocos ?? null}
        onClose={() => setQuickModal(null)}
        onSubmit={(nome, carteirinha) => void handleQuickSubmit(nome, carteirinha)}
      />
    </div>
  )
}

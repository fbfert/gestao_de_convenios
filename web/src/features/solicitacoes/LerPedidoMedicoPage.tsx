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
import type { PedidoMedicoAiResult, PedidoMedicoSuggestion, SolicitacaoForm } from './types'

const emptyArray: never[] = []

const emptyForm: SolicitacaoForm = {
  paciente_id: '',
  profissional_id: '',
  especialidade_id: '',
  quantidade: '10',
  convenio_id: '',
  medico_id: '',
  solicitado_em: new Date().toISOString().slice(0, 10),
  observacoes: '',
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
  onClose,
  onSubmit,
}: {
  kind: QuickModalKind | null
  initialName: string
  isSaving: boolean
  error: string | null
  onClose: () => void
  onSubmit: (nome: string) => void
}) {
  const [nome, setNome] = useState(initialName)
  const labels = {
    paciente: 'Novo paciente',
    especialidade: 'Nova especialidade',
    medico: 'Novo médico',
  }

  useEffect(() => {
    setNome(initialName)
  }, [initialName, kind])

  if (!kind) {
    return null
  }

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
                onSubmit(nome)
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

              {error ? (
                <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                  {error}
                </p>
              ) : null}

              <button
                type="submit"
                disabled={isSaving || nome.trim() === ''}
                className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
              >
                {isSaving ? 'Salvando...' : 'Salvar'}
              </button>
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
  const especialidadesQuery = useEspecialidades()
  const medicosQuery = useMedicos()
  const pacientesQuery = usePacientes({ convenio_id: form.convenio_id })
  const profissionaisQuery = useProfissionais({ especialidade_id: form.especialidade_id })
  const analisarPedido = useAnalisarPedidoMedico()
  const criarSolicitacao = useCriarSolicitacao()
  const criarPaciente = useCriarPacienteRapido()
  const criarEspecialidade = useCriarEspecialidadeRapida()
  const criarMedico = useCriarMedicoRapido()

  const convenios = useMemo(() => conveniosQuery.data ?? emptyArray, [conveniosQuery.data])
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
  const especialidadesSugeridas = useMemo<EspecialidadeRef[]>(
    () => (resultado?.sugestoes.especialidades ?? []).map((item) => ({ id: item.id, nome: item.nome })),
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
    form.profissional_id !== '' &&
    form.especialidade_id !== '' &&
    form.medico_id !== '' &&
    form.solicitado_em !== '' &&
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
      const topEspecialidade = data.sugestoes.especialidades[0]
      const topMedico = data.sugestoes.medicos[0]
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
        especialidade_id: topEspecialidade ? String(topEspecialidade.id) : current.especialidade_id,
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

  const handleQuickSubmit = async (nome: string) => {
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
        })
        setCreatedPacientes((current) => [...current, paciente])
        setForm((current) => ({ ...current, paciente_id: String(paciente.id) }))
      }

      if (quickModal === 'especialidade') {
        const especialidade = await criarEspecialidade.mutateAsync({ nome: trimmed })
        setCreatedEspecialidades((current) => [...current, especialidade])
        setForm((current) => ({
          ...current,
          especialidade_id: String(especialidade.id),
          profissional_id: '',
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
  const extractedEspecialidade = resultado?.dados.especialidade_nome?.trim() ?? ''
  const extractedMedico = resultado?.dados.medico_nome?.trim() ?? ''

  return (
    <div className="space-y-8" data-testid="ler-pedido-medico-page">
      <section className="space-y-4">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Solicitações</p>
            <h2 className="mt-2 text-3xl font-semibold text-white">Ler pedido médico</h2>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
              Anexe o pedido, revise as sugestões e confirme os campos antes de criar a solicitação.
            </p>
          </div>
          <button
            type="button"
            onClick={() => navigate('/solicitacoes')}
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
          >
            Voltar
          </button>
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
          <button
            type="submit"
            disabled={analisarPedido.isPending}
            className="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
            data-testid="pedido-medico-analisar"
          >
            {analisarPedido.isPending ? 'Lendo...' : 'Ler pedido'}
          </button>
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
                <button
                  type="button"
                  onClick={() => openQuickModal('paciente', extractedPaciente)}
                  className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
                >
                  Novo paciente
                </button>
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
                    {item.carteirinha ? ` · ${item.carteirinha}` : ''}
                  </option>
                ))}
              </Select>
            </div>

            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Profissional executante</span>
              <Select
                value={form.profissional_id}
                onChange={(event) =>
                  setForm((current) => ({ ...current, profissional_id: event.target.value }))
                }
                className={selectClasses()}
                disabled={profissionaisQuery.isLoading || profissionais.length === 0}
                data-testid="pedido-medico-profissional"
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

            <div className="space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm font-medium text-slate-200">
                  Especialidade {extractedEspecialidade ? `lida: ${extractedEspecialidade}` : ''}
                </p>
                <button
                  type="button"
                  onClick={() => openQuickModal('especialidade', extractedEspecialidade)}
                  className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
                >
                  Nova especialidade
                </button>
              </div>
              {resultado.sugestoes.especialidades.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {resultado.sugestoes.especialidades.map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() =>
                        setForm((current) => ({
                          ...current,
                          especialidade_id: String(item.id),
                          profissional_id: '',
                        }))
                      }
                      className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                    >
                      {item.nome} · {item.similaridade}%
                    </button>
                  ))}
                </div>
              ) : null}
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
                disabled={especialidadesQuery.isLoading || especialidades.length === 0}
                data-testid="pedido-medico-especialidade"
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
            </div>

            <div className="space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm font-medium text-slate-200">
                  Médico solicitante {extractedMedico ? `lido: ${extractedMedico}` : ''}
                </p>
                <button
                  type="button"
                  onClick={() => openQuickModal('medico', extractedMedico)}
                  className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
                >
                  Novo médico
                </button>
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

            <button
              type="submit"
              disabled={criarSolicitacao.isPending || !formIsComplete}
              className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
              data-testid="pedido-medico-submit"
            >
              {criarSolicitacao.isPending ? 'Salvando...' : 'Criar solicitação'}
            </button>
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
        onClose={() => setQuickModal(null)}
        onSubmit={(nome) => void handleQuickSubmit(nome)}
      />
    </div>
  )
}

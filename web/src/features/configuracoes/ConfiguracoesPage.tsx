import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { useEspecialidades, useProfissionais } from '../../lib/queries/useReferenceData'
import { useAuthStore } from '../../stores/authStore'
import { themeOptions, useThemeStore } from '../../stores/themeStore'
import {
  getHttpErrorMessage,
  useEmailSettings,
  useSalvarEmailSettings,
  type EmailSettingsForm,
} from './useEmailSettings'
import {
  useAiModels,
  useAiSettings,
  useSalvarAiSettings,
  type AiPromptTemplate,
  type AiSettingsForm,
} from './useAiSettings'
import {
  useReativarUnimed,
  useSalvarUnimedSettings,
  useSalvarUnimedEspecialidadeMapeamento,
  useSalvarUnimedProfissionalMapeamento,
  useUnimedEspecialidadeMapeamentos,
  useUnimedProfissionalMapeamentos,
  useUnimedSettings,
  useUnimedWorkerHealth,
  type UnimedEspecialidadeMapeamentoForm,
  type UnimedProfissionalMapeamentoForm,
  type UnimedSettingsForm,
} from './useUnimedSettings'

const emptySmtpForm: EmailSettingsForm['smtp'] = {
  host: '',
  port: '587',
  username: '',
  password: '',
  encryption: 'tls',
  from_email: '',
  from_name: '',
  ativo: true,
}

const emptyAiOpenaiForm: AiSettingsForm['openai'] = {
  api_key: '',
  base_url: 'https://api.openai.com/v1',
  organization_id: '',
  project_id: '',
  ativo: true,
}

const emptyUnimedCredentialForm: UnimedSettingsForm['credential'] = {
  login: '',
  password: '',
  base_url: 'https://portal.unimed.coop.br',
  ativo: true,
}

const emptyEspecialidadeMapeamentoForm: UnimedEspecialidadeMapeamentoForm = {
  convenio_id: '',
  especialidade_id: '',
  codigo_procedimento: '',
  descricao_operadora: '',
  quantidade_padrao: '10',
  usa_descricao_generica: false,
  valor_generico: '',
  ativo: true,
}

const emptyProfissionalMapeamentoForm: UnimedProfissionalMapeamentoForm = {
  convenio_id: '',
  profissional_id: '',
  codigo_operadora: '',
  nome_operadora: '',
  ativo: true,
}

const defaultAiPrompts: AiPromptTemplate[] = [
  {
    id: null,
    chave: 'ler_solicitacao_medica',
    nome: 'Ler solicitação médica',
    descricao: 'Extrai dados de solicitações médicas para criar Solicitações.',
    model_id: '',
    system_prompt:
      'Você extrai dados de documentos médicos para um sistema de convênios. Responda somente em JSON válido.',
    user_prompt:
      'Leia a solicitação médica escaneada e retorne paciente, médico, convênio, especialidade, data solicitada e observações relevantes.',
    ativo: true,
  },
  {
    id: null,
    chave: 'ler_sessoes_escaneadas',
    nome: 'Ler sessões escaneadas',
    descricao: 'Extrai sessões escaneadas para criar lançamentos no banco.',
    model_id: '',
    system_prompt:
      'Você extrai registros de sessões terapêuticas de documentos escaneados. Responda somente em JSON válido.',
    user_prompt:
      'Leia o registro de sessões escaneado e retorne data, hora início, hora fim, acompanhante, profissional e resumo das atividades de cada sessão.',
    ativo: true,
  },
]

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function ConfiguracoesPage() {
  const user = useAuthStore((state) => state.user)
  const canManageUnimed = user?.permissions?.includes('configuracoes.unimed.manage') ?? user?.role === 'admin'
  const emailSettingsQuery = useEmailSettings()
  const salvarEmailSettings = useSalvarEmailSettings()
  const aiSettingsQuery = useAiSettings()
  const salvarAiSettings = useSalvarAiSettings()
  const aiModelsQuery = useAiModels(false)
  const unimedSettingsQuery = useUnimedSettings()
  const salvarUnimedSettings = useSalvarUnimedSettings()
  const unimedWorkerHealth = useUnimedWorkerHealth()
  const reativarUnimed = useReativarUnimed()
  const especialidadesQuery = useEspecialidades()
  const profissionaisQuery = useProfissionais({ incluir_inativos: true })
  const salvarEspecialidadeMapeamento = useSalvarUnimedEspecialidadeMapeamento()
  const salvarProfissionalMapeamento = useSalvarUnimedProfissionalMapeamento()
  const theme = useThemeStore((state) => state.theme)
  const setTheme = useThemeStore((state) => state.setTheme)
  const [activeTab, setActiveTab] = useState<'geral' | 'emails' | 'ia' | 'unimed'>('emails')
  const [form, setForm] = useState<EmailSettingsForm>({
    smtp: emptySmtpForm,
  })
  const [aiForm, setAiForm] = useState<AiSettingsForm>({
    openai: emptyAiOpenaiForm,
    prompts: defaultAiPrompts,
  })
  const [unimedForm, setUnimedForm] = useState<UnimedSettingsForm>({
    convenio_id: '',
    credential: emptyUnimedCredentialForm,
  })
  const [especialidadeMapeamentoForm, setEspecialidadeMapeamentoForm] =
    useState<UnimedEspecialidadeMapeamentoForm>(emptyEspecialidadeMapeamentoForm)
  const [profissionalMapeamentoForm, setProfissionalMapeamentoForm] =
    useState<UnimedProfissionalMapeamentoForm>(emptyProfissionalMapeamentoForm)
  const [editingEspecialidadeMapeamentoId, setEditingEspecialidadeMapeamentoId] = useState<number | undefined>()
  const [editingProfissionalMapeamentoId, setEditingProfissionalMapeamentoId] = useState<number | undefined>()
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const especialidadeMapeamentosQuery = useUnimedEspecialidadeMapeamentos(unimedForm.convenio_id)
  const profissionalMapeamentosQuery = useUnimedProfissionalMapeamentos(unimedForm.convenio_id)
  const especialidades = useMemo(() => especialidadesQuery.data ?? [], [especialidadesQuery.data])
  const profissionais = useMemo(() => profissionaisQuery.data ?? [], [profissionaisQuery.data])

  useEffect(() => {
    const data = emailSettingsQuery.data

    if (!data) {
      return
    }

    setForm({
      smtp: data.smtp
        ? {
            host: data.smtp.host,
            port: String(data.smtp.port),
            username: data.smtp.username ?? '',
            password: '',
            encryption: data.smtp.encryption ?? '',
            from_email: data.smtp.from_email,
            from_name: data.smtp.from_name ?? '',
            ativo: data.smtp.ativo,
          }
        : emptySmtpForm,
    })
  }, [emailSettingsQuery.data])

  useEffect(() => {
    const data = aiSettingsQuery.data

    if (!data) {
      return
    }

    setAiForm({
      openai: data.openai
        ? {
            api_key: '',
            base_url: data.openai.base_url,
            organization_id: data.openai.organization_id ?? '',
            project_id: data.openai.project_id ?? '',
            ativo: data.openai.ativo,
          }
        : emptyAiOpenaiForm,
      prompts: data.prompts.length > 0 ? data.prompts : defaultAiPrompts,
    })
  }, [aiSettingsQuery.data])

  useEffect(() => {
    const data = unimedSettingsQuery.data

    if (!data) {
      return
    }

    setUnimedForm({
      convenio_id: data.convenio_id ? String(data.convenio_id) : '',
      credential: data.credential
        ? {
            login: data.credential.login,
            password: '',
            base_url: data.credential.base_url ?? '',
            ativo: data.credential.ativo,
          }
        : emptyUnimedCredentialForm,
    })
  }, [unimedSettingsQuery.data])

  useEffect(() => {
    setEspecialidadeMapeamentoForm((current) => ({
      ...current,
      convenio_id: current.convenio_id || unimedForm.convenio_id,
      especialidade_id: current.especialidade_id || (especialidades[0] ? String(especialidades[0].id) : ''),
    }))
    setProfissionalMapeamentoForm((current) => ({
      ...current,
      convenio_id: current.convenio_id || unimedForm.convenio_id,
      profissional_id: current.profissional_id || (profissionais[0] ? String(profissionais[0].id) : ''),
    }))
  }, [unimedForm.convenio_id, especialidades, profissionais])

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      await salvarEmailSettings.mutateAsync(form)
      setMessage('Configurações de email salvas.')
      setForm((current) => ({
        ...current,
        smtp: {
          ...current.smtp,
          password: '',
        },
      }))
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar as configurações.'))
    }
  }

  const updateAiPrompt = (
    index: number,
    field: keyof AiPromptTemplate,
    value: string | boolean,
  ) => {
    setAiForm((current) => ({
      ...current,
      prompts: current.prompts.map((prompt, promptIndex) =>
        promptIndex === index ? { ...prompt, [field]: value } : prompt,
      ),
    }))
  }

  const handleAiSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      await salvarAiSettings.mutateAsync(aiForm)
      setMessage('Configurações de IA salvas.')
      setAiForm((current) => ({
        ...current,
        openai: {
          ...current.openai,
          api_key: '',
        },
      }))
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar as configurações de IA.'))
    }
  }

  const handleUnimedSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      await salvarUnimedSettings.mutateAsync(unimedForm)
      setMessage('Configurações Unimed salvas.')
      setUnimedForm((current) => ({
        ...current,
        credential: {
          ...current.credential,
          password: '',
        },
      }))
    } catch (submitError) {
      setError(
        getHttpErrorMessage(submitError, 'Não foi possível salvar as configurações Unimed.'),
      )
    }
  }

  const handleReativarUnimed = async () => {
    setMessage(null)
    setError(null)

    try {
      await reativarUnimed.mutateAsync()
      setMessage('Automação Unimed reativada.')
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível reativar a automação Unimed.'))
    }
  }

  const handleEspecialidadeMapeamentoSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      await salvarEspecialidadeMapeamento.mutateAsync({
        id: editingEspecialidadeMapeamentoId,
        payload: especialidadeMapeamentoForm,
      })
      setMessage('Mapeamento de especialidade salvo.')
      setEditingEspecialidadeMapeamentoId(undefined)
      setEspecialidadeMapeamentoForm({
        ...emptyEspecialidadeMapeamentoForm,
        convenio_id: unimedForm.convenio_id,
        especialidade_id: especialidades[0] ? String(especialidades[0].id) : '',
      })
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar o mapeamento de especialidade.'))
    }
  }

  const handleProfissionalMapeamentoSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      await salvarProfissionalMapeamento.mutateAsync({
        id: editingProfissionalMapeamentoId,
        payload: profissionalMapeamentoForm,
      })
      setMessage('Mapeamento de profissional salvo.')
      setEditingProfissionalMapeamentoId(undefined)
      setProfissionalMapeamentoForm({
        ...emptyProfissionalMapeamentoForm,
        convenio_id: unimedForm.convenio_id,
        profissional_id: profissionais[0] ? String(profissionais[0].id) : '',
      })
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar o mapeamento de profissional.'))
    }
  }

  return (
    <div className="space-y-6" data-testid="configuracoes-page">
      <section className="space-y-4">
        <div>
          <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
          <h2 className="mt-2 text-3xl font-semibold text-white">Ajustes do sistema</h2>
          <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
            Esta área está reservada para parâmetros operacionais e personalizações futuras do
            tenant.
          </p>
        </div>

        <div className="flex flex-wrap gap-2 border-b border-white/10 pb-3">
          <button
            type="button"
            onClick={() => setActiveTab('geral')}
            className={`rounded-2xl px-4 py-2 text-sm font-semibold transition ${
              activeTab === 'geral'
                ? 'bg-cyan-400 text-slate-950'
                : 'border border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'
            }`}
          >
            Geral
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('emails')}
            className={`rounded-2xl px-4 py-2 text-sm font-semibold transition ${
              activeTab === 'emails'
                ? 'bg-cyan-400 text-slate-950'
                : 'border border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'
            }`}
            data-testid="configuracoes-tab-emails"
          >
            Envio de emails
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('ia')}
            className={`rounded-2xl px-4 py-2 text-sm font-semibold transition ${
              activeTab === 'ia'
                ? 'bg-cyan-400 text-slate-950'
                : 'border border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'
            }`}
            data-testid="configuracoes-tab-ia"
          >
            Configurações de IA
          </button>
          <Link
            to="/configuracoes/templates-emails"
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
            data-testid="configuracoes-email-templates"
          >
            Templates de E-mails
          </Link>
          <button
            type="button"
            onClick={() => setActiveTab('unimed')}
            className={`rounded-2xl px-4 py-2 text-sm font-semibold transition ${
              activeTab === 'unimed'
                ? 'bg-cyan-400 text-slate-950'
                : 'border border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'
            }`}
            data-testid="configuracoes-tab-unimed"
          >
            Unimed RDA
          </button>
        </div>
      </section>

      {activeTab === 'geral' ? (
        <div className="space-y-6" data-testid="configuracoes-geral">
          <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
            <h3 className="text-lg font-semibold text-white">Aparência</h3>
            <p className="mt-2 text-sm text-slate-300">
              Escolha o tema visual do sistema. A preferência vale para este navegador e é aplicada
              imediatamente.
            </p>

            <div className="mt-5 grid gap-3 sm:grid-cols-2">
              {themeOptions.map((option) => {
                const isActive = theme === option.value

                return (
                  <button
                    key={option.value}
                    type="button"
                    onClick={() => setTheme(option.value)}
                    aria-pressed={isActive}
                    className={`rounded-2xl border p-4 text-left transition ${
                      isActive
                        ? 'border-cyan-300/70 bg-cyan-400/10 ring-2 ring-cyan-300/20'
                        : 'border-white/10 bg-white/5 hover:bg-white/10'
                    }`}
                    data-testid={`configuracoes-tema-${option.value}`}
                  >
                    <span className="flex items-center justify-between gap-3">
                      <span className="text-sm font-semibold text-white">{option.label}</span>
                      {isActive ? (
                        <span className="rounded-full bg-cyan-400 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-950">
                          Ativo
                        </span>
                      ) : null}
                    </span>
                    <span className="mt-2 block text-xs leading-5 text-slate-400">
                      {option.description}
                    </span>
                    <span
                      aria-hidden="true"
                      className="mt-3 flex h-12 items-center gap-2 overflow-hidden rounded-xl border border-white/10 px-3"
                      style={
                        option.value === 'claro'
                          ? { background: 'linear-gradient(180deg, #f7f9fc 0%, #eef2f7 100%)' }
                          : { background: 'linear-gradient(180deg, #07111f 0%, #0f172a 100%)' }
                      }
                    >
                      <span
                        className="h-2 w-16 rounded-full"
                        style={{
                          background: option.value === 'claro' ? '#1e293b' : '#e2e8f0',
                        }}
                      />
                      <span
                        className="h-2 w-8 rounded-full"
                        style={{
                          background: option.value === 'claro' ? '#0e7490' : '#22d3ee',
                        }}
                      />
                    </span>
                  </button>
                )
              })}
            </div>
          </section>

          <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
            <h3 className="text-lg font-semibold text-white">Configurações gerais</h3>
            <p className="mt-2 text-sm text-slate-300">
              Esta aba permanece reservada para parâmetros operacionais futuros.
            </p>
          </section>
        </div>
      ) : null}

      {activeTab === 'emails' ? (
        <form
          onSubmit={handleSubmit}
          className="space-y-6"
          data-testid="configuracoes-emails-form"
        >
          <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
            <div className="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <h3 className="text-lg font-semibold text-white">SMTP</h3>
                <p className="mt-1 text-sm text-slate-300">
                  Dados usados pelo tenant para autenticar e identificar o remetente dos emails.
                </p>
              </div>
              <label className="inline-flex items-center gap-2 text-sm font-medium text-slate-200">
                <input
                  type="checkbox"
                  checked={form.smtp.ativo}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      smtp: { ...current.smtp, ativo: event.target.checked },
                    }))
                  }
                  className="size-4 rounded border-white/20 bg-white/10"
                  data-testid="email-smtp-ativo"
                />
                Ativo
              </label>
            </div>

            {emailSettingsQuery.isLoading ? (
              <div className="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                Carregando configurações de email...
              </div>
            ) : null}

            <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Host SMTP</span>
                <input
                  value={form.smtp.host}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      smtp: { ...current.smtp, host: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  placeholder="smtp.exemplo.com"
                  data-testid="email-smtp-host"
                />
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Porta</span>
                <input
                  type="number"
                  min="1"
                  max="65535"
                  value={form.smtp.port}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      smtp: { ...current.smtp, port: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  data-testid="email-smtp-port"
                />
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Criptografia</span>
                <select
                  value={form.smtp.encryption}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      smtp: {
                        ...current.smtp,
                        encryption: event.target.value as EmailSettingsForm['smtp']['encryption'],
                      },
                    }))
                  }
                  className={inputClasses()}
                  data-testid="email-smtp-encryption"
                >
                  <option value="">Nenhuma</option>
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                </select>
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Usuário</span>
                <input
                  value={form.smtp.username}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      smtp: { ...current.smtp, username: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  data-testid="email-smtp-username"
                />
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Senha SMTP</span>
                <input
                  type="password"
                  value={form.smtp.password}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      smtp: { ...current.smtp, password: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  placeholder={
                    emailSettingsQuery.data?.smtp?.senha_configurada
                      ? 'Senha já configurada'
                      : 'Informe a senha'
                  }
                  data-testid="email-smtp-password"
                />
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Email remetente</span>
                <input
                  type="email"
                  value={form.smtp.from_email}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      smtp: { ...current.smtp, from_email: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  data-testid="email-smtp-from-email"
                />
              </label>
              <label className="space-y-2 md:col-span-2 xl:col-span-3">
                <span className="text-sm font-medium text-slate-200">Nome remetente</span>
                <input
                  value={form.smtp.from_name}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      smtp: { ...current.smtp, from_name: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  data-testid="email-smtp-from-name"
                />
              </label>
            </div>
          </section>

          {message ? (
            <p className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
              {message}
            </p>
          ) : null}

          {error ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
              {error}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={salvarEmailSettings.isPending}
            className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
            data-testid="configuracoes-emails-salvar"
          >
            {salvarEmailSettings.isPending ? 'Salvando...' : 'Salvar configurações de email'}
          </button>
        </form>
      ) : null}

      {activeTab === 'unimed' ? (
        <form
          onSubmit={handleUnimedSubmit}
          className="space-y-6"
          data-testid="configuracoes-unimed-form"
        >
          <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
            <div className="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <h3 className="text-lg font-semibold text-white">Portal Unimed RDA</h3>
                <p className="mt-1 text-sm text-slate-300">
                  Convênio e credenciais usados pela automação de guias no portal RDA.
                </p>
              </div>
              <label className="inline-flex items-center gap-2 text-sm font-medium text-slate-200">
                <input
                  type="checkbox"
                  checked={unimedForm.credential.ativo}
                  onChange={(event) =>
                    setUnimedForm((current) => ({
                      ...current,
                      credential: { ...current.credential, ativo: event.target.checked },
                    }))
                  }
                  className="size-4 rounded border-white/20 bg-white/10"
                  data-testid="unimed-credencial-ativo"
                  disabled={!canManageUnimed}
                />
                Ativo
              </label>
            </div>

            {unimedSettingsQuery.isLoading ? (
              <div className="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                Carregando configurações Unimed...
              </div>
            ) : null}

            <div className="mt-5 grid gap-4 md:grid-cols-2">
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Convênio Unimed</span>
                <select
                  value={unimedForm.convenio_id}
                  onChange={(event) =>
                    setUnimedForm((current) => ({
                      ...current,
                      convenio_id: event.target.value,
                    }))
                  }
                  className={inputClasses()}
                  data-testid="unimed-convenio"
                  disabled={!canManageUnimed}
                >
                  <option value="">Nenhum convênio vinculado</option>
                  {(unimedSettingsQuery.data?.convenios ?? []).map((convenio) => (
                    <option key={convenio.id} value={convenio.id}>
                      {convenio.nome}
                    </option>
                  ))}
                </select>
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Login</span>
                <input
                  value={unimedForm.credential.login}
                  onChange={(event) =>
                    setUnimedForm((current) => ({
                      ...current,
                      credential: { ...current.credential, login: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  data-testid="unimed-login"
                  disabled={!canManageUnimed}
                />
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Senha</span>
                <input
                  type="password"
                  value={unimedForm.credential.password}
                  onChange={(event) =>
                    setUnimedForm((current) => ({
                      ...current,
                      credential: { ...current.credential, password: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  placeholder={
                    unimedSettingsQuery.data?.credential?.senha_configurada
                      ? 'Senha já configurada'
                      : 'Informe a senha'
                  }
                  data-testid="unimed-password"
                  disabled={!canManageUnimed}
                />
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Base URL</span>
                <input
                  value={unimedForm.credential.base_url}
                  onChange={(event) =>
                    setUnimedForm((current) => ({
                      ...current,
                      credential: { ...current.credential, base_url: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  placeholder="https://portal.unimed.coop.br"
                  data-testid="unimed-base-url"
                  disabled={!canManageUnimed}
                />
              </label>
            </div>

            <div className="mt-5 flex flex-wrap items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-4">
              <div className="min-w-52 flex-1">
                <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Worker</p>
                <p className="mt-1 text-sm font-medium text-white">
                  {unimedWorkerHealth.data?.status ?? 'Não consultado'}
                </p>
              </div>
              <button
                type="button"
                onClick={() => void unimedWorkerHealth.refetch()}
                disabled={unimedWorkerHealth.isFetching}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10 disabled:opacity-60"
                data-testid="unimed-worker-health"
              >
                {unimedWorkerHealth.isFetching ? 'Consultando...' : 'Healthcheck'}
              </button>
              {unimedSettingsQuery.data?.credential?.automation_paused_at ? (
                <button
                  type="button"
                  onClick={() => void handleReativarUnimed()}
                  disabled={reativarUnimed.isPending || !canManageUnimed}
                  className="rounded-2xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-400/20 disabled:opacity-60"
                  data-testid="unimed-reativar"
                >
                  {reativarUnimed.isPending ? 'Reativando...' : 'Reativar automação'}
                </button>
              ) : null}
              {unimedSettingsQuery.data?.credential?.automation_paused_reason ? (
                <p className="basis-full text-sm text-amber-100" data-testid="unimed-automation-paused">
                  Pausada: {unimedSettingsQuery.data.credential.automation_paused_reason}
                  {unimedSettingsQuery.data.credential.automation_paused_at
                    ? ` · ${unimedSettingsQuery.data.credential.automation_paused_at}`
                    : ''}
                </p>
              ) : null}
            </div>
          </section>

          {canManageUnimed ? (
            <section className="grid gap-6 xl:grid-cols-2">
              <div className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
                <h3 className="text-lg font-semibold text-white">Especialidade x Convênio</h3>
                <form onSubmit={handleEspecialidadeMapeamentoSubmit} className="mt-4 grid gap-3">
                  <select
                    value={especialidadeMapeamentoForm.especialidade_id}
                    onChange={(event) =>
                      setEspecialidadeMapeamentoForm((current) => ({
                        ...current,
                        convenio_id: unimedForm.convenio_id,
                        especialidade_id: event.target.value,
                      }))
                    }
                    className={inputClasses()}
                    data-testid="unimed-mapeamento-especialidade"
                  >
                    {especialidades.map((especialidade) => (
                      <option key={especialidade.id} value={especialidade.id}>
                        {especialidade.nome}
                      </option>
                    ))}
                  </select>
                  <input
                    value={especialidadeMapeamentoForm.codigo_procedimento}
                    onChange={(event) =>
                      setEspecialidadeMapeamentoForm((current) => ({ ...current, codigo_procedimento: event.target.value }))
                    }
                    className={inputClasses()}
                    placeholder="Código procedimento"
                    data-testid="unimed-mapeamento-codigo-procedimento"
                  />
                  <input
                    type="number"
                    min="1"
                    value={especialidadeMapeamentoForm.quantidade_padrao}
                    onChange={(event) =>
                      setEspecialidadeMapeamentoForm((current) => ({ ...current, quantidade_padrao: event.target.value }))
                    }
                    className={inputClasses()}
                    data-testid="unimed-mapeamento-quantidade"
                  />
                  <button
                    type="submit"
                    disabled={!unimedForm.convenio_id || !especialidadeMapeamentoForm.codigo_procedimento}
                    className="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 disabled:opacity-60"
                    data-testid="unimed-mapeamento-especialidade-salvar"
                  >
                    Salvar
                  </button>
                </form>
                <div className="mt-4 space-y-2">
                  {(especialidadeMapeamentosQuery.data ?? []).map((mapeamento) => (
                    <button
                      key={mapeamento.id}
                      type="button"
                      onClick={() => {
                        setEditingEspecialidadeMapeamentoId(mapeamento.id)
                        setEspecialidadeMapeamentoForm({
                          convenio_id: String(mapeamento.convenio_id),
                          especialidade_id: String(mapeamento.especialidade_id),
                          codigo_procedimento: mapeamento.codigo_procedimento,
                          descricao_operadora: mapeamento.descricao_operadora ?? '',
                          quantidade_padrao: String(mapeamento.quantidade_padrao),
                          usa_descricao_generica: mapeamento.usa_descricao_generica,
                          valor_generico: mapeamento.valor_generico ?? '',
                          ativo: mapeamento.ativo,
                        })
                      }}
                      className="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-left text-sm text-slate-200"
                    >
                      {mapeamento.especialidade?.nome ?? mapeamento.especialidade_id} · {mapeamento.codigo_procedimento}
                    </button>
                  ))}
                </div>
              </div>

              <div className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
                <h3 className="text-lg font-semibold text-white">Profissional x Convênio</h3>
                <form onSubmit={handleProfissionalMapeamentoSubmit} className="mt-4 grid gap-3">
                  <select
                    value={profissionalMapeamentoForm.profissional_id}
                    onChange={(event) =>
                      setProfissionalMapeamentoForm((current) => ({
                        ...current,
                        convenio_id: unimedForm.convenio_id,
                        profissional_id: event.target.value,
                      }))
                    }
                    className={inputClasses()}
                    data-testid="unimed-mapeamento-profissional"
                  >
                    {profissionais.map((profissional) => (
                      <option key={profissional.id} value={profissional.id}>
                        {profissional.nome}
                      </option>
                    ))}
                  </select>
                  <input
                    value={profissionalMapeamentoForm.codigo_operadora}
                    onChange={(event) =>
                      setProfissionalMapeamentoForm((current) => ({ ...current, codigo_operadora: event.target.value }))
                    }
                    className={inputClasses()}
                    placeholder="Código operadora"
                    data-testid="unimed-mapeamento-codigo-operadora"
                  />
                  <button
                    type="submit"
                    disabled={!unimedForm.convenio_id || !profissionalMapeamentoForm.codigo_operadora}
                    className="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 disabled:opacity-60"
                    data-testid="unimed-mapeamento-profissional-salvar"
                  >
                    Salvar
                  </button>
                </form>
                <div className="mt-4 space-y-2">
                  {(profissionalMapeamentosQuery.data ?? []).map((mapeamento) => (
                    <button
                      key={mapeamento.id}
                      type="button"
                      onClick={() => {
                        setEditingProfissionalMapeamentoId(mapeamento.id)
                        setProfissionalMapeamentoForm({
                          convenio_id: String(mapeamento.convenio_id),
                          profissional_id: String(mapeamento.profissional_id),
                          codigo_operadora: mapeamento.codigo_operadora,
                          nome_operadora: mapeamento.nome_operadora ?? '',
                          ativo: mapeamento.ativo,
                        })
                      }}
                      className="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-left text-sm text-slate-200"
                    >
                      {mapeamento.profissional?.nome ?? mapeamento.profissional_id} · {mapeamento.codigo_operadora}
                    </button>
                  ))}
                </div>
              </div>
            </section>
          ) : (
            <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6 text-sm text-slate-300">
              Seu usuário não possui permissão para editar configurações Unimed.
            </section>
          )}

          {message ? (
            <p className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
              {message}
            </p>
          ) : null}

          {error ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
              {error}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={salvarUnimedSettings.isPending || !canManageUnimed}
            className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
            data-testid="configuracoes-unimed-salvar"
          >
            {salvarUnimedSettings.isPending ? 'Salvando...' : 'Salvar configurações Unimed'}
          </button>
        </form>
      ) : null}

      {activeTab === 'ia' ? (
        <form onSubmit={handleAiSubmit} className="space-y-6" data-testid="configuracoes-ia-form">
          <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
            <div className="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <h3 className="text-lg font-semibold text-white">OpenAI</h3>
                <p className="mt-1 text-sm text-slate-300">
                  A conexão é usada pelo backend para listar modelos e preparar futuras leituras de
                  documentos.
                </p>
              </div>
              <label className="inline-flex items-center gap-2 text-sm font-medium text-slate-200">
                <input
                  type="checkbox"
                  checked={aiForm.openai.ativo}
                  onChange={(event) =>
                    setAiForm((current) => ({
                      ...current,
                      openai: { ...current.openai, ativo: event.target.checked },
                    }))
                  }
                  className="size-4 rounded border-white/20 bg-white/10"
                  data-testid="ia-openai-ativo"
                />
                Ativo
              </label>
            </div>

            {aiSettingsQuery.isLoading ? (
              <div className="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
                Carregando configurações de IA...
              </div>
            ) : null}

            <div className="mt-5 grid gap-4 md:grid-cols-2">
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">API key</span>
                <input
                  type="password"
                  value={aiForm.openai.api_key}
                  onChange={(event) =>
                    setAiForm((current) => ({
                      ...current,
                      openai: { ...current.openai, api_key: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  placeholder={
                    aiSettingsQuery.data?.openai?.api_key_configurada
                      ? 'API key já configurada'
                      : 'sk-...'
                  }
                  data-testid="ia-openai-api-key"
                />
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Base URL</span>
                <input
                  value={aiForm.openai.base_url}
                  onChange={(event) =>
                    setAiForm((current) => ({
                      ...current,
                      openai: { ...current.openai, base_url: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  data-testid="ia-openai-base-url"
                />
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Organização</span>
                <input
                  value={aiForm.openai.organization_id}
                  onChange={(event) =>
                    setAiForm((current) => ({
                      ...current,
                      openai: { ...current.openai, organization_id: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  placeholder="org_..."
                  data-testid="ia-openai-organization"
                />
              </label>
              <label className="space-y-2">
                <span className="text-sm font-medium text-slate-200">Projeto</span>
                <input
                  value={aiForm.openai.project_id}
                  onChange={(event) =>
                    setAiForm((current) => ({
                      ...current,
                      openai: { ...current.openai, project_id: event.target.value },
                    }))
                  }
                  className={inputClasses()}
                  placeholder="proj_..."
                  data-testid="ia-openai-project"
                />
              </label>
            </div>

            <div className="mt-5 flex flex-wrap items-center gap-3">
              <button
                type="button"
                onClick={() => void aiModelsQuery.refetch()}
                disabled={aiModelsQuery.isFetching}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10 disabled:opacity-60"
                data-testid="ia-openai-listar-modelos"
              >
                {aiModelsQuery.isFetching ? 'Listando modelos...' : 'Listar modelos'}
              </button>
              {aiModelsQuery.isError ? (
                <span className="text-sm text-rose-100">
                  {getHttpErrorMessage(aiModelsQuery.error, 'Não foi possível listar modelos.')}
                </span>
              ) : null}
            </div>

            {aiModelsQuery.data && aiModelsQuery.data.length > 0 ? (
              <div className="mt-5 rounded-3xl border border-white/10 bg-white/5 p-4">
                <p className="text-xs uppercase tracking-[0.25em] text-slate-400">
                  Modelos disponíveis
                </p>
                <div className="mt-3 flex max-h-44 flex-wrap gap-2 overflow-y-auto">
                  {aiModelsQuery.data.map((model) => (
                    <button
                      key={model.id}
                      type="button"
                      onClick={() =>
                        setAiForm((current) => ({
                          ...current,
                          prompts: current.prompts.map((prompt) => ({
                            ...prompt,
                            model_id: prompt.model_id || model.id,
                          })),
                        }))
                      }
                      className="rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                    >
                      {model.id}
                    </button>
                  ))}
                </div>
              </div>
            ) : null}
          </section>

          <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
            <div>
              <h3 className="text-lg font-semibold text-white">Prompts operacionais</h3>
              <p className="mt-1 text-sm text-slate-300">
                Cada prompt define como a IA deve transformar documentos em dados estruturados.
              </p>
            </div>

            <div className="mt-5 space-y-4">
              {aiForm.prompts.map((prompt, index) => (
                <article
                  key={prompt.chave}
                  className="rounded-3xl border border-white/10 bg-white/5 p-4"
                >
                  <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <label className="space-y-2">
                      <span className="text-sm font-medium text-slate-200">Chave</span>
                      <input value={prompt.chave} disabled className={`${inputClasses()} opacity-70`} />
                    </label>
                    <label className="space-y-2">
                      <span className="text-sm font-medium text-slate-200">Nome</span>
                      <input
                        value={prompt.nome}
                        onChange={(event) => updateAiPrompt(index, 'nome', event.target.value)}
                        className={inputClasses()}
                        data-testid={`ia-prompt-nome-${prompt.chave}`}
                      />
                    </label>
                    <label className="space-y-2 xl:col-span-2">
                      <span className="text-sm font-medium text-slate-200">Modelo</span>
                      <input
                        value={prompt.model_id ?? ''}
                        onChange={(event) => updateAiPrompt(index, 'model_id', event.target.value)}
                        className={inputClasses()}
                        placeholder="Selecione ou informe um modelo"
                        list="ia-modelos-disponiveis"
                        data-testid={`ia-prompt-modelo-${prompt.chave}`}
                      />
                    </label>
                  </div>

                  <label className="mt-4 block space-y-2">
                    <span className="text-sm font-medium text-slate-200">Descrição</span>
                    <input
                      value={prompt.descricao ?? ''}
                      onChange={(event) => updateAiPrompt(index, 'descricao', event.target.value)}
                      className={inputClasses()}
                    />
                  </label>

                  <div className="mt-4 grid gap-4 xl:grid-cols-2">
                    <label className="space-y-2">
                      <span className="text-sm font-medium text-slate-200">Prompt de sistema</span>
                      <textarea
                        value={prompt.system_prompt}
                        onChange={(event) =>
                          updateAiPrompt(index, 'system_prompt', event.target.value)
                        }
                        className={`${inputClasses()} min-h-44 font-mono`}
                        data-testid={`ia-prompt-system-${prompt.chave}`}
                      />
                    </label>
                    <label className="space-y-2">
                      <span className="text-sm font-medium text-slate-200">Prompt do usuário</span>
                      <textarea
                        value={prompt.user_prompt}
                        onChange={(event) =>
                          updateAiPrompt(index, 'user_prompt', event.target.value)
                        }
                        className={`${inputClasses()} min-h-44 font-mono`}
                        data-testid={`ia-prompt-user-${prompt.chave}`}
                      />
                    </label>
                  </div>

                  <label className="mt-4 inline-flex items-center gap-2 text-sm font-medium text-slate-200">
                    <input
                      type="checkbox"
                      checked={prompt.ativo}
                      onChange={(event) => updateAiPrompt(index, 'ativo', event.target.checked)}
                      className="size-4 rounded border-white/20 bg-white/10"
                    />
                    Prompt ativo
                  </label>
                </article>
              ))}
            </div>
          </section>

          <datalist id="ia-modelos-disponiveis">
            {(aiModelsQuery.data ?? []).map((model) => (
              <option key={model.id} value={model.id} />
            ))}
          </datalist>

          {message ? (
            <p className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
              {message}
            </p>
          ) : null}

          {error ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
              {error}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={salvarAiSettings.isPending}
            className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
            data-testid="configuracoes-ia-salvar"
          >
            {salvarAiSettings.isPending ? 'Salvando...' : 'Salvar configurações de IA'}
          </button>
        </form>
      ) : null}
    </div>
  )
}

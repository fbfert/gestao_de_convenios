import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
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
  const emailSettingsQuery = useEmailSettings()
  const salvarEmailSettings = useSalvarEmailSettings()
  const aiSettingsQuery = useAiSettings()
  const salvarAiSettings = useSalvarAiSettings()
  const aiModelsQuery = useAiModels(false)
  const [activeTab, setActiveTab] = useState<'geral' | 'emails' | 'ia'>('emails')
  const [form, setForm] = useState<EmailSettingsForm>({
    smtp: emptySmtpForm,
  })
  const [aiForm, setAiForm] = useState<AiSettingsForm>({
    openai: emptyAiOpenaiForm,
    prompts: defaultAiPrompts,
  })
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

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
        </div>
      </section>

      {activeTab === 'geral' ? (
        <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <h3 className="text-lg font-semibold text-white">Configurações gerais</h3>
          <p className="mt-2 text-sm text-slate-300">
            Esta aba permanece reservada para parâmetros operacionais futuros.
          </p>
        </section>
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

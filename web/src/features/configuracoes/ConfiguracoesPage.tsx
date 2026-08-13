import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useEspecialidades, useProfissionais } from '../../lib/queries/useReferenceData'
import { usePode } from '../../lib/permissoes'
import {
  getHttpErrorMessage,
  useEmailSettings,
  useEnviarEmailTeste,
  useSalvarEmailSettings,
  type EmailSettingsForm,
} from './useEmailSettings'
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

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

/**
 * Cada aba virou uma rota propria sob /configuracoes, servida pelo submenu do
 * cabecalho. As duas que sobraram aqui continuam num componente so porque
 * compartilham estado e handlers; o que muda e qual delas e renderizada.
 *
 * A aba de IA saiu: virou ConfiguracoesIaPage (conexao) mais
 * PromptsOperacionaisPage (CRUD dos prompts).
 */
export type ConfiguracoesAba = 'emails' | 'unimed'

const cabecalhoPorAba: Record<ConfiguracoesAba, { titulo: string; descricao: string }> = {
  emails: {
    titulo: 'Envio de e-mails',
    descricao:
      'Servidor SMTP usado para disparar os e-mails do sistema. Sem isso preenchido, nada sai da caixa.',
  },
  unimed: {
    titulo: 'Unimed RDA',
    descricao:
      'Credenciais do portal e o de-para de especialidades e profissionais que a automação usa.',
  },
}

export function ConfiguracoesPage({ aba }: { aba: ConfiguracoesAba }) {
  const activeTab = aba
  const pode = usePode()
  // Antes isto caia em `role === 'admin'`, porque a API nunca mandava
  // `permissions`. Com papel proprio o nome do papel nao diz mais nada sobre o
  // que a pessoa pode; quem responde e a permissao.
  const canManageUnimed = pode('configuracoes.unimed.manage')
  const emailSettingsQuery = useEmailSettings()
  const salvarEmailSettings = useSalvarEmailSettings()
  const enviarEmailTeste = useEnviarEmailTeste()
  const unimedSettingsQuery = useUnimedSettings()
  const salvarUnimedSettings = useSalvarUnimedSettings()
  const unimedWorkerHealth = useUnimedWorkerHealth()
  const reativarUnimed = useReativarUnimed()
  const especialidadesQuery = useEspecialidades()
  const profissionaisQuery = useProfissionais({ incluir_inativos: true })
  const salvarEspecialidadeMapeamento = useSalvarUnimedEspecialidadeMapeamento()
  const salvarProfissionalMapeamento = useSalvarUnimedProfissionalMapeamento()
  const [form, setForm] = useState<EmailSettingsForm>({
    smtp: emptySmtpForm,
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
  const [emailTeste, setEmailTeste] = useState('')
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

  const handleEnviarTeste = async () => {
    const destino = emailTeste.trim()

    if (!destino) {
      return
    }

    setMessage(null)
    setError(null)

    try {
      const resposta = await enviarEmailTeste.mutateAsync(destino)
      setMessage(resposta.mensagem)
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível enviar o e-mail de teste.'))
    }
  }

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
      <section className="space-y-2">
        <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
        <h2 className="text-3xl font-semibold text-white">{cabecalhoPorAba[aba].titulo}</h2>
        <p className="max-w-3xl text-sm leading-6 text-slate-300">
          {cabecalhoPorAba[aba].descricao}
        </p>
      </section>

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

          <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
            <h3 className="text-lg font-semibold text-white">Enviar e-mail de teste</h3>
            <p className="mt-1 text-sm text-slate-300">
              O teste usa o SMTP <strong>já salvo</strong>, não o que está digitado acima. Salve
              antes de testar.
            </p>

            <div className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-start">
              <label className="flex-1 space-y-2">
                <span className="text-sm font-medium text-slate-200">E-mail de destino</span>
                <input
                  type="email"
                  value={emailTeste}
                  onChange={(event) => setEmailTeste(event.target.value)}
                  /*
                    Enter aqui submeteria o formulario de fora, que salva as
                    configuracoes — nao e o que quem digitou o destino espera.
                  */
                  onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                      event.preventDefault()
                      void handleEnviarTeste()
                    }
                  }}
                  className={inputClasses()}
                  placeholder="destinatario@exemplo.com"
                  data-testid="email-teste-destino"
                />
              </label>

              <button
                type="button"
                onClick={() => void handleEnviarTeste()}
                disabled={enviarEmailTeste.isPending || !emailTeste.trim()}
                className="rounded-2xl border border-cyan-300/40 bg-cyan-400/15 px-4 py-3 text-sm font-semibold text-cyan-50 transition hover:bg-cyan-400/25 disabled:opacity-60 sm:mt-7"
                data-testid="email-teste-enviar"
              >
                {enviarEmailTeste.isPending ? 'Enviando...' : 'Enviar teste'}
              </button>
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
    </div>
  )
}

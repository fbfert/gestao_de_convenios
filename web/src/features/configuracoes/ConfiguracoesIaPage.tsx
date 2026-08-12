import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import {
  getHttpErrorMessage,
  useAiModels,
  useAiSettings,
  useSalvarAiSettings,
  type AiOpenaiForm,
} from './useAiSettings'

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

const openaiVazio: AiOpenaiForm = {
  api_key: '',
  base_url: 'https://api.openai.com/v1',
  organization_id: '',
  project_id: '',
  model_id: '',
  ativo: false,
}

/**
 * Conexao com a OpenAI. Os prompts sairam desta tela e ganharam CRUD proprio
 * em /configuracoes/ia/prompts: a credencial e uma so por tenant e muda quase
 * nunca, enquanto os prompts sao varios e mudam com o uso.
 */
export function ConfiguracoesIaPage() {
  const settingsQuery = useAiSettings()
  const salvar = useSalvarAiSettings()
  const modelosQuery = useAiModels(false)
  const [form, setForm] = useState<AiOpenaiForm>(openaiVazio)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const openai = settingsQuery.data?.openai

    if (!openai) {
      return
    }

    setForm({
      // A chave nunca volta do backend, so o indicador de que existe. Manter o
      // campo vazio e o que permite salvar sem reescrever a chave ja gravada.
      api_key: '',
      base_url: openai.base_url,
      organization_id: openai.organization_id ?? '',
      project_id: openai.project_id ?? '',
      model_id: openai.model_id ?? '',
      ativo: openai.ativo,
    })
  }, [settingsQuery.data])

  const alterar = <C extends keyof AiOpenaiForm>(campo: C, valor: AiOpenaiForm[C]) => {
    setForm((atual) => ({ ...atual, [campo]: valor }))
  }

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      await salvar.mutateAsync({ openai: form })
      setMessage('Conexão com a OpenAI salva.')
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar a conexão.'))
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6" data-testid="configuracoes-ia-page">
      <section className="space-y-2">
        <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
        <h2 className="text-3xl font-semibold text-white">Configurações de IA</h2>
        <p className="max-w-3xl text-sm leading-6 text-slate-300">
          Credenciais da OpenAI usadas na leitura automática de documentos. O texto que a IA
          recebe fica em{' '}
          <Link to="/configuracoes/ia/prompts" className="text-cyan-200 underline">
            Prompts Operacionais
          </Link>
          .
        </p>
      </section>

      <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <div className="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <h3 className="text-lg font-semibold text-white">OpenAI</h3>
            <p className="mt-1 text-sm text-slate-300">
              Enquanto estiver inativa, nenhuma leitura automática é executada.
            </p>
          </div>
          <label className="inline-flex items-center gap-2 text-sm font-medium text-slate-200">
            <input
              type="checkbox"
              checked={form.ativo}
              onChange={(event) => alterar('ativo', event.target.checked)}
              className="size-4 rounded border-white/20 bg-white/10"
              data-testid="ia-openai-ativo"
            />
            Ativo
          </label>
        </div>

        {settingsQuery.isLoading ? (
          <div className="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            Carregando configurações de IA...
          </div>
        ) : null}

        <div className="mt-5 grid gap-4 md:grid-cols-2">
          <label className="space-y-2">
            <span className="text-sm font-medium text-slate-200">API key</span>
            <input
              type="password"
              value={form.api_key}
              onChange={(event) => alterar('api_key', event.target.value)}
              className={inputClasses()}
              placeholder={
                settingsQuery.data?.openai?.api_key_configurada
                  ? 'API key já configurada — preencha só para trocar'
                  : 'sk-...'
              }
              data-testid="ia-openai-api-key"
            />
          </label>
          <label className="space-y-2">
            <span className="text-sm font-medium text-slate-200">Base URL</span>
            <input
              value={form.base_url}
              onChange={(event) => alterar('base_url', event.target.value)}
              className={inputClasses()}
              data-testid="ia-openai-base-url"
            />
          </label>
          {/*
            Os dois campos abaixo viram cabecalhos HTTP (OpenAI-Organization e
            OpenAI-Project) e sao identificadores, nao nomes. Um nome amigavel
            aqui derruba a chamada com 400 invalid_project — por isso o aviso
            de "deixe em branco", que e o caso certo para chaves sk-proj-*.
          */}
          <label className="space-y-2">
            <span className="text-sm font-medium text-slate-200">Organização</span>
            <input
              value={form.organization_id}
              onChange={(event) => alterar('organization_id', event.target.value)}
              className={inputClasses()}
              placeholder="org_... (opcional)"
              data-testid="ia-openai-organization"
            />
            <span className="block text-xs text-slate-400">
              O identificador, começando por <code>org_</code> — não o nome da organização.
            </span>
          </label>
          <label className="space-y-2">
            <span className="text-sm font-medium text-slate-200">Projeto</span>
            <input
              value={form.project_id}
              onChange={(event) => alterar('project_id', event.target.value)}
              className={inputClasses()}
              placeholder="proj_... (opcional)"
              data-testid="ia-openai-project"
            />
            <span className="block text-xs text-slate-400">
              O identificador, começando por <code>proj_</code> — não o nome do projeto. Chaves{' '}
              <code>sk-proj-*</code> já vêm presas a um projeto: deixe em branco.
            </span>
          </label>
        </div>

        <label className="mt-4 block space-y-2">
          <span className="text-sm font-medium text-slate-200">Modelo padrão</span>
          <input
            value={form.model_id}
            onChange={(event) => alterar('model_id', event.target.value)}
            className={inputClasses()}
            placeholder="gpt-5.6-luna"
            list="ia-modelos-disponiveis"
            data-testid="ia-openai-model"
          />
          <span className="block text-xs text-slate-400">
            Usado quando o prompt não define um modelo próprio. Digite, escolha na lista do campo
            ou clique num modelo abaixo depois de listar.
          </span>
        </label>

        <div className="mt-5 flex flex-wrap items-center gap-3">
          <button
            type="button"
            onClick={() => void modelosQuery.refetch()}
            disabled={modelosQuery.isFetching}
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10 disabled:opacity-60"
            data-testid="ia-openai-listar-modelos"
          >
            {modelosQuery.isFetching ? 'Listando modelos...' : 'Listar modelos'}
          </button>
          <span className="text-xs text-slate-400">
            Usa a chave já salva. Salve antes de listar.
          </span>
          {modelosQuery.isError ? (
            <span className="text-sm text-rose-100">
              {getHttpErrorMessage(modelosQuery.error, 'Não foi possível listar modelos.')}
            </span>
          ) : null}
        </div>

        {modelosQuery.data && modelosQuery.data.length > 0 ? (
          <div className="mt-5 rounded-3xl border border-white/10 bg-white/5 p-4">
            <p className="text-xs uppercase tracking-[0.25em] text-slate-400">
              Modelos disponíveis
            </p>
            <p className="mt-1 text-xs text-slate-400">
              Clique num modelo para usá-lo como padrão. Lembre de salvar depois.
            </p>
            <div className="mt-3 flex max-h-44 flex-wrap gap-2 overflow-y-auto">
              {modelosQuery.data.map((model) => {
                const selecionado = form.model_id === model.id

                return (
                  <button
                    key={model.id}
                    type="button"
                    onClick={() => alterar('model_id', model.id)}
                    aria-pressed={selecionado}
                    className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                      selecionado
                        ? 'border-cyan-300/70 bg-cyan-400/30 text-cyan-50 ring-2 ring-cyan-300/20'
                        : 'border-cyan-400/20 bg-cyan-400/10 text-cyan-100 hover:bg-cyan-400/20'
                    }`}
                    data-testid={`ia-modelo-${model.id}`}
                  >
                    {model.id}
                    {selecionado ? ' ✓' : ''}
                  </button>
                )
              })}
            </div>
          </div>
        ) : null}
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

      {/* Alimenta o `list` do campo Modelo padrão acima. */}
      <datalist id="ia-modelos-disponiveis">
        {(modelosQuery.data ?? []).map((model) => (
          <option key={model.id} value={model.id} />
        ))}
      </datalist>

      <button
        type="submit"
        disabled={salvar.isPending}
        className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
        data-testid="configuracoes-ia-salvar"
      >
        {salvar.isPending ? 'Salvando...' : 'Salvar conexão'}
      </button>
    </form>
  )
}

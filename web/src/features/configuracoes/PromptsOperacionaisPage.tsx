import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { Link, useMatch, useNavigate } from 'react-router-dom'
import { useAiModels } from './useAiSettings'
import {
  getHttpErrorMessage,
  promptVazio,
  useAiPrompts,
  useAtualizarAiPrompt,
  useCriarAiPrompt,
  useExcluirAiPrompt,
  type AiPrompt,
  type AiPromptForm,
} from './useAiPrompts'
import { Botao } from '../../components/ui/Botao'

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function paraFormulario(prompt: AiPrompt): AiPromptForm {
  return {
    chave: prompt.chave,
    nome: prompt.nome,
    descricao: prompt.descricao ?? '',
    model_id: prompt.model_id ?? '',
    system_prompt: prompt.system_prompt,
    user_prompt: prompt.user_prompt,
    ativo: prompt.ativo,
  }
}

/** `null` = nenhum editor aberto; `'novo'` = criando; número = editando aquele id. */
type Edicao = null | 'novo' | number

export function PromptsOperacionaisPage() {
  const promptsQuery = useAiPrompts()
  const criar = useCriarAiPrompt()
  const atualizar = useAtualizarAiPrompt()
  const excluir = useExcluirAiPrompt()

  const [edicao, setEdicao] = useState<Edicao>(null)
  /*
    A rota manda no que aparece: criar e editar acontecem em tela propria, como
    no resto do sistema. O estado `edicao` continua guardando qual prompt esta
    sendo editado, mas quem decide renderizar o formulario e a URL.
  */
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/configuracoes/ia/prompts/novo') !== null
  const editRouteMatch = useMatch('/configuracoes/ia/prompts/:id/editar')
  const routeEditingId = editRouteMatch ? Number(editRouteMatch.params.id) : null
  const isEditRoute = routeEditingId !== null && Number.isInteger(routeEditingId)
  const isFormRoute = isCreateRoute || isEditRoute
  const carregadoRef = useRef<number | 'novo' | null>(null)
  const [form, setForm] = useState<AiPromptForm>(promptVazio)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [confirmandoExclusao, setConfirmandoExclusao] = useState<number | null>(null)

  /*
    Só busca os modelos quando o editor está aberto. A listagem sozinha não
    precisa deles, e a chamada bate na OpenAI — carregar sempre gastaria uma
    ida à API a cada visita. Antes o `datalist` era montado com uma query que
    nunca disparava, então o campo Modelo nunca oferecia nada.
  */
  const modelosQuery = useAiModels(edicao !== null)

  const prompts = useMemo(() => promptsQuery.data ?? [], [promptsQuery.data])
  const editandoSistema =
    typeof edicao === 'number' && (prompts.find((p) => p.id === edicao)?.sistema ?? false)

  const abrirNovo = () => {
    setEdicao('novo')
    setForm(promptVazio)
    setMessage(null)
    setError(null)
    navigate('/configuracoes/ia/prompts/novo')
  }

  /*
    Hidrata quando a tela e aberta direto pela URL ou recarregada: nesses casos
    abrirNovo/abrirEdicao nunca rodaram, e o formulario apareceria vazio.
  */
  useEffect(() => {
    if (!isFormRoute) {
      carregadoRef.current = null
      setEdicao(null)

      return
    }

    if (isCreateRoute) {
      if (carregadoRef.current !== 'novo') {
        carregadoRef.current = 'novo'
        setEdicao('novo')
        setForm(promptVazio)
      }

      return
    }

    const prompt = prompts.find((item) => item.id === routeEditingId)

    if (!prompt || carregadoRef.current === prompt.id) {
      return
    }

    carregadoRef.current = prompt.id
    setEdicao(prompt.id)
    setForm(paraFormulario(prompt))
  }, [isFormRoute, isCreateRoute, routeEditingId, prompts])

  const abrirEdicao = (prompt: AiPrompt) => {
    setEdicao(prompt.id)
    setForm(paraFormulario(prompt))
    setMessage(null)
    setError(null)
    navigate(`/configuracoes/ia/prompts/${prompt.id}/editar`)
  }

  const fechar = () => {
    setEdicao(null)
    setForm(promptVazio)
    carregadoRef.current = null
    navigate('/configuracoes/ia/prompts')
  }

  const alterar = <C extends keyof AiPromptForm>(campo: C, valor: AiPromptForm[C]) => {
    setForm((atual) => ({ ...atual, [campo]: valor }))
  }

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      if (edicao === 'novo') {
        await criar.mutateAsync(form)
        setMessage(`Prompt "${form.nome}" criado.`)
      } else if (typeof edicao === 'number') {
        await atualizar.mutateAsync({ id: edicao, form })
        setMessage(`Prompt "${form.nome}" salvo.`)
      }

      fechar()
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar o prompt.'))
    }
  }

  const handleExcluir = async (prompt: AiPrompt) => {
    setMessage(null)
    setError(null)

    try {
      await excluir.mutateAsync(prompt.id)
      setMessage(`Prompt "${prompt.nome}" excluído.`)
      setConfirmandoExclusao(null)

      if (edicao === prompt.id) {
        fechar()
      }
    } catch (deleteError) {
      setError(getHttpErrorMessage(deleteError, 'Não foi possível excluir o prompt.'))
    }
  }

  const salvando = criar.isPending || atualizar.isPending

  return (
    <div className="space-y-6" data-testid="prompts-operacionais-page">
      <section className="space-y-2">
        <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
        <h2 className="text-3xl font-semibold text-white">Prompts Operacionais</h2>
        <p className="max-w-3xl text-sm leading-6 text-slate-300">
          Cada prompt define como a IA transforma um documento em dados estruturados. A credencial
          usada no envio fica em{' '}
          <Link to="/configuracoes/ia" className="text-cyan-200 underline">
            Configurações de IA
          </Link>
          .
        </p>
      </section>

      <div className="flex flex-wrap items-center gap-3">
        <Botao type="button" onClick={abrirNovo} data-testid="prompt-novo">
          Novo prompt
        </Botao>
        <span className="text-xs text-slate-400">
          {prompts.length} {prompts.length === 1 ? 'prompt cadastrado' : 'prompts cadastrados'}
        </span>
      </div>

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

      {isFormRoute ? (
        <form
          onSubmit={handleSubmit}
          className="rounded-[1.75rem] border border-cyan-300/30 bg-slate-950/60 p-6"
          data-testid="prompt-form"
        >
          <h3 className="text-lg font-semibold text-white">
            {edicao === 'novo' ? 'Novo prompt' : `Editando: ${form.nome || form.chave}`}
          </h3>

          <div className="mt-5 grid gap-4 md:grid-cols-2">
            <label className="space-y-2">
              <span className="text-sm font-medium text-slate-200">Chave</span>
              <input
                value={form.chave}
                onChange={(event) => alterar('chave', event.target.value)}
                className={`${inputClasses()} ${editandoSistema ? 'opacity-70' : ''}`}
                placeholder="ler_documento_x"
                disabled={editandoSistema}
                required
                data-testid="prompt-chave"
              />
              <span className="block text-xs text-slate-400">
                {editandoSistema
                  ? 'Prompt usado pelo sistema: a chave não pode ser alterada.'
                  : 'Identificador interno. Minúsculas, números e underscore.'}
              </span>
            </label>
            <label className="space-y-2">
              <span className="text-sm font-medium text-slate-200">Nome</span>
              <input
                value={form.nome}
                onChange={(event) => alterar('nome', event.target.value)}
                className={inputClasses()}
                required
                data-testid="prompt-nome"
              />
            </label>
            <label className="space-y-2 md:col-span-2">
              <span className="text-sm font-medium text-slate-200">Descrição</span>
              <input
                value={form.descricao}
                onChange={(event) => alterar('descricao', event.target.value)}
                className={inputClasses()}
                placeholder="Para que serve este prompt"
              />
            </label>
            <label className="space-y-2 md:col-span-2">
              <span className="text-sm font-medium text-slate-200">Modelo</span>
              <input
                value={form.model_id}
                onChange={(event) => alterar('model_id', event.target.value)}
                className={inputClasses()}
                placeholder="Em branco usa o modelo padrão da conexão"
                list="ia-modelos-disponiveis"
                data-testid="prompt-modelo"
              />
              <span className="block text-xs text-slate-400">
                {modelosQuery.isFetching
                  ? 'Carregando os modelos disponíveis...'
                  : modelosQuery.data?.length
                    ? `${modelosQuery.data.length} modelos disponíveis na lista do campo.`
                    : 'Deixe em branco para usar o modelo padrão definido em Configurações de IA.'}
              </span>
            </label>
          </div>

          <div className="mt-4 grid gap-4 xl:grid-cols-2">
            <label className="space-y-2">
              <span className="text-sm font-medium text-slate-200">Prompt de sistema</span>
              <textarea
                value={form.system_prompt}
                onChange={(event) => alterar('system_prompt', event.target.value)}
                className={`${inputClasses()} min-h-44 font-mono`}
                required
                data-testid="prompt-system"
              />
            </label>
            <label className="space-y-2">
              <span className="text-sm font-medium text-slate-200">Prompt do usuário</span>
              <textarea
                value={form.user_prompt}
                onChange={(event) => alterar('user_prompt', event.target.value)}
                className={`${inputClasses()} min-h-44 font-mono`}
                required
                data-testid="prompt-user"
              />
            </label>
          </div>

          <label className="mt-4 inline-flex items-center gap-2 text-sm font-medium text-slate-200">
            <input
              type="checkbox"
              checked={form.ativo}
              onChange={(event) => alterar('ativo', event.target.checked)}
              className="size-4 rounded border-white/20 bg-white/10"
              data-testid="prompt-ativo"
            />
            Prompt ativo
          </label>

          <div className="mt-5 flex flex-wrap gap-3">
            <Botao type="submit" carregando={salvando} data-testid="prompt-salvar">
              {salvando ? 'Salvando...' : 'Salvar prompt'}
            </Botao>
            <Botao type="button" variante="secundario" onClick={fechar}>
              Cancelar
            </Botao>
          </div>
        </form>
      ) : null}

      {promptsQuery.isPending ? (
        <p className="text-sm text-slate-400">Carregando prompts...</p>
      ) : null}

      {promptsQuery.isError ? (
        <p className="text-sm text-rose-300">
          {getHttpErrorMessage(promptsQuery.error, 'Não foi possível carregar os prompts.')}
        </p>
      ) : null}

      <div className="space-y-4">
        {prompts.map((prompt) => (
          <article
            key={prompt.id}
            className="rounded-3xl border border-white/10 bg-white/5 p-5"
            data-testid={`prompt-item-${prompt.chave}`}
          >
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="text-base font-semibold text-white">{prompt.nome}</p>
                  {prompt.sistema ? (
                    <span className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-cyan-100">
                      Sistema
                    </span>
                  ) : null}
                  {prompt.ativo ? null : (
                    <span className="rounded-full border border-white/15 bg-white/5 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-300">
                      Inativo
                    </span>
                  )}
                </div>
                <p className="font-mono text-xs text-slate-400">{prompt.chave}</p>
                {prompt.descricao ? (
                  <p className="text-sm text-slate-300">{prompt.descricao}</p>
                ) : null}
                <p className="text-xs text-slate-400">
                  Modelo: {prompt.model_id || 'padrão do backend'}
                </p>
              </div>

              <div className="flex flex-wrap gap-2">
                <button
                  type="button"
                  onClick={() => abrirEdicao(prompt)}
                  className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
                  data-testid={`prompt-editar-${prompt.chave}`}
                >
                  Editar
                </button>

                {/* Prompt de sistema nao mostra excluir: a API tambem recusa. */}
                {prompt.sistema ? null : confirmandoExclusao === prompt.id ? (
                  <>
                    <button
                      type="button"
                      onClick={() => void handleExcluir(prompt)}
                      disabled={excluir.isPending}
                      className="rounded-2xl bg-rose-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-400 disabled:opacity-60"
                      data-testid={`prompt-confirmar-exclusao-${prompt.chave}`}
                    >
                      {excluir.isPending ? 'Excluindo...' : 'Confirmar'}
                    </button>
                    <button
                      type="button"
                      onClick={() => setConfirmandoExclusao(null)}
                      className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
                    >
                      Cancelar
                    </button>
                  </>
                ) : (
                  <button
                    type="button"
                    onClick={() => setConfirmandoExclusao(prompt.id)}
                    className="rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:bg-rose-500/20"
                    data-testid={`prompt-excluir-${prompt.chave}`}
                  >
                    Excluir
                  </button>
                )}
              </div>
            </div>
          </article>
        ))}

        {!promptsQuery.isPending && prompts.length === 0 ? (
          <p className="rounded-3xl border border-white/10 bg-white/5 p-5 text-sm text-slate-300">
            Nenhum prompt cadastrado.
          </p>
        ) : null}
      </div>

      <datalist id="ia-modelos-disponiveis">
        {(modelosQuery.data ?? []).map((model) => (
          <option key={model.id} value={model.id} />
        ))}
      </datalist>
    </div>
  )
}

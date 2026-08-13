import { useEffect, useState, type FormEvent } from 'react'
import {
  descreverMinutos,
  getHttpErrorMessage,
  paraFormulario,
  useConfiguracoesGlobais,
  useSalvarConfiguracoesGlobais,
  type ConfiguracoesGlobaisForm,
} from './useConfiguracoesGlobais'

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

const formVazio: ConfiguracoesGlobaisForm = {
  sessao_minutos: '480',
  senha_alerta_dias: '7',
  sessoes_padrao: '10',
  itens_por_pagina: '15',
  auditoria_retencao_meses: '12',
}

/** Atalhos de tempo de sessão, para não ter que calcular minutos na mão. */
const atalhosSessao = [
  { rotulo: '30 min', valor: '30' },
  { rotulo: '2 h', valor: '120' },
  { rotulo: '8 h', valor: '480' },
  { rotulo: '24 h', valor: '1440' },
  { rotulo: 'Sem expirar', valor: '0' },
]

export function ConfiguracoesGlobaisPage() {
  const query = useConfiguracoesGlobais()
  const salvar = useSalvarConfiguracoesGlobais()
  const [form, setForm] = useState<ConfiguracoesGlobaisForm>(formVazio)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (query.data) {
      setForm(paraFormulario(query.data))
    }
  }, [query.data])

  const alterar = <C extends keyof ConfiguracoesGlobaisForm>(
    campo: C,
    valor: ConfiguracoesGlobaisForm[C],
  ) => {
    setForm((atual) => ({ ...atual, [campo]: valor }))
  }

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    try {
      await salvar.mutateAsync(form)
      setMessage('Configurações globais salvas.')
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar as configurações.'))
    }
  }

  const minutos = Number(form.sessao_minutos)

  return (
    <form onSubmit={handleSubmit} className="space-y-6" data-testid="configuracoes-globais-page">
      <section className="space-y-2">
        <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
        <h2 className="text-3xl font-semibold text-white">Globais</h2>
        <p className="max-w-3xl text-sm leading-6 text-slate-300">
          Parâmetros de comportamento do sistema, válidos para toda a clínica. Regras de convênio
          — validade de senha, quantidade autorizada, valores — continuam em Convênios, por
          operadora.
        </p>
      </section>

      {query.isPending ? <p className="text-sm text-slate-400">Carregando...</p> : null}

      {query.isError ? (
        <p className="text-sm text-rose-300">
          {getHttpErrorMessage(query.error, 'Não foi possível carregar as configurações.')}
        </p>
      ) : null}

      <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <h3 className="text-lg font-semibold text-white">Sessão</h3>
        <p className="mt-1 text-sm text-slate-300">
          Quanto tempo um login vale. O prazo conta a partir da entrada, não do último clique:
          passado o tempo, é preciso entrar de novo, mesmo com o sistema em uso.
        </p>

        <div className="mt-5 max-w-md space-y-2">
          <label className="space-y-2">
            <span className="text-sm font-medium text-slate-200">Tempo de sessão (minutos)</span>
            <input
              type="number"
              min={0}
              max={43200}
              value={form.sessao_minutos}
              onChange={(event) => alterar('sessao_minutos', event.target.value)}
              className={inputClasses()}
              required
              data-testid="globais-sessao-minutos"
            />
          </label>

          <div className="flex flex-wrap gap-2">
            {atalhosSessao.map((atalho) => (
              <button
                key={atalho.valor}
                type="button"
                onClick={() => alterar('sessao_minutos', atalho.valor)}
                className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                  form.sessao_minutos === atalho.valor
                    ? 'border-cyan-300/70 bg-cyan-400/25 text-cyan-50'
                    : 'border-cyan-400/20 bg-cyan-400/10 text-cyan-100 hover:bg-cyan-400/20'
                }`}
              >
                {atalho.rotulo}
              </button>
            ))}
          </div>

          <p className="text-xs text-slate-400">
            Equivale a <strong className="text-slate-200">{descreverMinutos(minutos)}</strong>.
            {minutos === 0 ? ' A sessão só termina quando o usuário sai.' : ''}
          </p>
        </div>
      </section>

      <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <h3 className="text-lg font-semibold text-white">Operação</h3>
        <p className="mt-1 text-sm text-slate-300">
          Padrões que as telas usam quando você não informa outra coisa.
        </p>

        <div className="mt-5 grid gap-4 md:grid-cols-3">
          <label className="space-y-2">
            <span className="text-sm font-medium text-slate-200">Aviso de senha vencendo</span>
            <input
              type="number"
              min={1}
              max={180}
              value={form.senha_alerta_dias}
              onChange={(event) => alterar('senha_alerta_dias', event.target.value)}
              className={inputClasses()}
              required
              data-testid="globais-senha-alerta-dias"
            />
            <span className="block text-xs text-slate-400">
              Dias de antecedência com que a guia é marcada como prestes a vencer.
            </span>
          </label>

          <label className="space-y-2">
            <span className="text-sm font-medium text-slate-200">Sessões por especialidade</span>
            <input
              type="number"
              min={1}
              max={999}
              value={form.sessoes_padrao}
              onChange={(event) => alterar('sessoes_padrao', event.target.value)}
              className={inputClasses()}
              required
              data-testid="globais-sessoes-padrao"
            />
            <span className="block text-xs text-slate-400">
              Quantidade sugerida ao acrescentar uma especialidade na solicitação.
            </span>
          </label>

          <label className="space-y-2">
            <span className="text-sm font-medium text-slate-200">Itens por página</span>
            <input
              type="number"
              min={5}
              max={200}
              value={form.itens_por_pagina}
              onChange={(event) => alterar('itens_por_pagina', event.target.value)}
              className={inputClasses()}
              required
              data-testid="globais-itens-por-pagina"
            />
            <span className="block text-xs text-slate-400">
              Tamanho padrão das listagens paginadas.
            </span>
          </label>

          <label className="space-y-2">
            <span className="text-sm font-medium text-slate-200">Retenção da auditoria (meses)</span>
            <input
              type="number"
              min={3}
              max={120}
              value={form.auditoria_retencao_meses}
              onChange={(event) => alterar('auditoria_retencao_meses', event.target.value)}
              className={inputClasses()}
              required
              data-testid="globais-auditoria-retencao"
            />
            <span className="block text-xs text-slate-400">
              Todo dia, o que passa deste prazo é exportado em CSV no servidor e depois removido da
              trilha. Mínimo de 3 meses.
            </span>
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
        disabled={salvar.isPending}
        className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
        data-testid="globais-salvar"
      >
        {salvar.isPending ? 'Salvando...' : 'Salvar configurações globais'}
      </button>
    </form>
  )
}

import { Link, useParams } from 'react-router-dom'
import { useState, type FormEvent } from 'react'
import { getHttpErrorMessage, useAutomacao, useAutomacoes, useReprocessarAutomacao } from './useAutomacoes'
import type { AutomacaoFilters } from './types'
import { Botao } from '../../components/ui/Botao'
import { Badge, type BadgeProps } from '../../components/ui/Badge'

const defaultFilters: AutomacaoFilters = {
  status: '',
  operacao: '',
  needs_attention: '',
}

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function attention(status: string) {
  return ['failed', 'uncertain', 'needs_attention'].includes(status)
}

function statusTone(status: string): NonNullable<BadgeProps['tone']> {
  if (attention(status)) {
    return 'perigo'
  }

  if (status === 'succeeded') {
    return 'sucesso'
  }

  return 'acento'
}

export function AutomacoesPage() {
  const { id } = useParams()
  const detalheId = id && /^\d+$/.test(id) ? Number(id) : null
  const [filters, setFilters] = useState(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(defaultFilters)
  const [page, setPage] = useState(1)
  const automacoesQuery = useAutomacoes(filters, page)
  const detalheQuery = useAutomacao(detalheId)
  const reprocessar = useReprocessarAutomacao()
  const automacoes = automacoesQuery.data?.data ?? []
  const totalPages = automacoesQuery.data?.meta?.last_page ?? 1
  const attentionCount = automacoes.filter((item) => attention(item.status)).length

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setPage(1)
    setFilters(draftFilters)
  }

  const handleReprocessar = async (execucaoId: number) => {
    try {
      await reprocessar.mutateAsync(execucaoId)
    } catch (error) {
      window.alert(getHttpErrorMessage(error, 'Não foi possível reprocessar a execução.'))
    }
  }

  if (detalheId !== null) {
    const execucao = detalheQuery.data

    return (
      <div className="space-y-6" data-testid="automacao-detalhe-page">
        <Link to="/automacoes" className="text-sm font-semibold text-cyan-200 hover:text-cyan-100">
          ← Voltar para automações
        </Link>

        {detalheQuery.isLoading ? (
          <div className="rounded-3xl border border-white/10 bg-white/5 p-6 text-slate-300">
            Carregando execução...
          </div>
        ) : detalheQuery.isError || !execucao ? (
          <div className="rounded-3xl border border-rose-400/20 bg-rose-500/10 p-6 text-rose-100">
            Execução não encontrada.
          </div>
        ) : (
          <>
            <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
              <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Automação</p>
                  <h2 className="mt-2 text-3xl font-semibold text-white">Execução #{execucao.id}</h2>
                  <p className="mt-2 text-sm text-slate-300">{execucao.operacao}</p>
                </div>
                <Badge tone={statusTone(execucao.status)} className="w-fit">
                  {execucao.status}
                </Badge>
              </div>

              {attention(execucao.status) ? (
                <div className="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-sm text-rose-100">
                  Esta execução precisa de atenção operacional.
                </div>
              ) : null}

              <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Info label="Guia">{execucao.guia_id ? `#${execucao.guia_id}` : '-'}</Info>
                <Info label="Item">{execucao.solicitacao_item_id ? `#${execucao.solicitacao_item_id}` : '-'}</Info>
                <Info label="Origem">{execucao.parent_id ? `#${execucao.parent_id}` : '-'}</Info>
                <Info label="Fila">{execucao.queued_at ?? '-'}</Info>
                <Info label="Início">{execucao.started_at ?? '-'}</Info>
                <Info label="Conclusão">{execucao.finished_at ?? '-'}</Info>
                <Info label="Erro">{execucao.erro_codigo ?? '-'}</Info>
                <Info label="Mensagem">{execucao.erro_mensagem ?? '-'}</Info>
              </div>

              <button
                type="button"
                onClick={() => void handleReprocessar(execucao.id)}
                disabled={reprocessar.isPending || !['failed', 'needs_attention'].includes(execucao.status)}
                className="mt-5 rounded-2xl border border-cyan-400/30 bg-cyan-400/10 px-4 py-3 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
                data-testid="automacao-reprocessar"
              >
                Reprocessar
              </button>
            </section>

            <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
              <h3 className="text-lg font-semibold text-white">Timeline</h3>
              <div className="mt-4 space-y-3">
                {execucao.eventos?.map((evento) => (
                  <div key={evento.id} className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-200">
                    <p className="font-semibold text-white">{evento.tipo} · {evento.status ?? '-'}</p>
                    <p className="mt-1 text-slate-300">{evento.registrado_em ?? '-'}</p>
                    {evento.evidencias ? (
                      <pre className="mt-3 overflow-auto rounded-2xl bg-slate-950/70 p-3 text-xs text-slate-200">
                        {JSON.stringify(evento.evidencias, null, 2)}
                      </pre>
                    ) : null}
                  </div>
                ))}
                {execucao.eventos?.length === 0 ? (
                  <p className="text-sm text-slate-300">Nenhum evento registrado.</p>
                ) : null}
              </div>
            </section>
          </>
        )}
      </div>
    )
  }

  return (
    <div className="space-y-6" data-testid="automacoes-page">
      <section>
        <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Automações</p>
        <h2 className="mt-2 text-3xl font-semibold text-white">Execuções Unimed</h2>
      </section>

      <section className="grid gap-4 md:grid-cols-3">
        <Summary label="Na página" value={String(automacoes.length)} />
        <Summary label="Atenção" value={String(attentionCount)} tone="attention" />
        <Summary label="Filtro" value={filters.status || filters.operacao || 'Todos'} />
      </section>

      <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <form className="grid gap-3 md:grid-cols-4" onSubmit={handleFilterSubmit}>
          <select
            value={draftFilters.status}
            onChange={(event) => setDraftFilters((current) => ({ ...current, status: event.target.value }))}
            className={inputClasses()}
          >
            <option value="">Todos os status</option>
            <option value="queued">queued</option>
            <option value="running">running</option>
            <option value="succeeded">succeeded</option>
            <option value="failed">failed</option>
            <option value="uncertain">uncertain</option>
            <option value="needs_attention">needs_attention</option>
          </select>
          <select
            value={draftFilters.operacao}
            onChange={(event) => setDraftFilters((current) => ({ ...current, operacao: event.target.value }))}
            className={inputClasses()}
          >
            <option value="">Todas as operações</option>
            <option value="gerar_guia">gerar_guia</option>
            <option value="consult_status_batch">consult_status_batch</option>
            <option value="capture_authorization_data_batch">capture_authorization_data_batch</option>
          </select>
          <label className="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-medium text-slate-200">
            <input
              type="checkbox"
              checked={draftFilters.needs_attention === '1'}
              onChange={(event) =>
                setDraftFilters((current) => ({
                  ...current,
                  needs_attention: event.target.checked ? '1' : '',
                }))
              }
              className="size-4 rounded border-white/20 bg-white/10"
            />
            Atenção
          </label>
          <Botao type="submit">Aplicar</Botao>
        </form>

        <div className="mt-5 overflow-hidden rounded-3xl border border-white/10">
          <table className="w-full border-collapse text-left text-sm">
            <thead className="bg-fundo text-xs uppercase tracking-[0.25em] text-texto-suave">
              <tr>
                <th className="px-4 py-3">ID</th>
                <th className="px-4 py-3">Operação</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Guia</th>
                <th className="px-4 py-3">Fila</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-linha bg-superficie">
              {automacoes.map((execucao) => (
                <tr key={execucao.id}>
                  <td className="px-4 py-4">
                    <Link to={`/automacoes/${execucao.id}`} className="font-semibold text-cyan-100 underline decoration-cyan-300/40 underline-offset-4">
                      #{execucao.id}
                    </Link>
                  </td>
                  <td className="px-4 py-4 text-slate-200">{execucao.operacao}</td>
                  <td className="px-4 py-4">
                    <Badge tone={statusTone(execucao.status)}>{execucao.status}</Badge>
                  </td>
                  <td className="px-4 py-4 text-slate-200">{execucao.guia?.numero_guia ?? execucao.guia_id ?? '-'}</td>
                  <td className="px-4 py-4 text-slate-200">{execucao.queued_at ?? '-'}</td>
                </tr>
              ))}
              {automacoes.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-8 text-center text-slate-300">Nenhuma execução encontrada.</td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>

        <div className="mt-5 flex items-center justify-between">
          <button type="button" onClick={() => setPage((current) => Math.max(1, current - 1))} disabled={page <= 1} className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm text-white disabled:opacity-50">
            Anterior
          </button>
          <p className="text-sm text-slate-300">Página {page} de {totalPages}</p>
          <button type="button" onClick={() => setPage((current) => Math.min(totalPages, current + 1))} disabled={page >= totalPages} className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm text-white disabled:opacity-50">
            Próxima
          </button>
        </div>
      </section>
    </div>
  )
}

function Info({ label, children }: { label: string; children: string }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
      <p className="text-xs uppercase tracking-[0.25em] text-slate-400">{label}</p>
      <p className="mt-2 break-words text-sm font-medium text-white">{children}</p>
    </div>
  )
}

function Summary({ label, value, tone }: { label: string; value: string; tone?: 'attention' }) {
  return (
    <article className={`rounded-3xl border p-4 ${tone === 'attention' ? 'border-rose-400/20 bg-rose-500/10' : 'border-white/10 bg-slate-950/40'}`}>
      <p className="text-xs uppercase tracking-[0.25em] text-slate-400">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-white">{value}</p>
    </article>
  )
}

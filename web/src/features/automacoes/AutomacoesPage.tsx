import { Link, useLocation, useParams } from 'react-router-dom'
import { useEffect, useState, type FormEvent } from 'react'
import {
  getHttpErrorMessage,
  useAtualizarNomeMedico,
  useAutomacao,
  useAutomacoes,
  useReprocessarAutomacao,
} from './useAutomacoes'
import type { AutomacaoExecucao, AutomacaoFilters } from './types'
import { Botao } from '../../components/ui/Botao'
import { Paginacao } from '../../components/ui/Paginacao'
import { useListaNaUrl } from '../../lib/useListaNaUrl'
import { Badge, type BadgeProps } from '../../components/ui/Badge'
import { useConfirm } from '../../components/ui/ConfirmDialog'
import { AutomacaoProgressoModal } from './AutomacaoProgressoModal'

const defaultFilters: AutomacaoFilters = {
  status: '',
  operacao: '',
  needs_attention: '',
  numero_guia: '',
}

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-corpo text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

function attention(status: string) {
  return ['failed', 'uncertain', 'needs_attention'].includes(status)
}

/**
 * O worker nunca decide sozinho um médico que não bateu com confiança — só
 * devolve a melhor sugestão encontrada na Unimed (`buscarPrestadorPorNome`
 * em worker-unimed/src/operations/gerarGuia.js) pro operador confirmar ou
 * corrigir aqui.
 */
function medicoAmbiguoInfo(execucao: AutomacaoExecucao) {
  if (execucao.erro_codigo !== 'PRESTADOR_NOME_AMBIGUO') {
    return null
  }

  const resultado = execucao.resultado ?? {}
  const medicoPayload = (execucao.payload?.medico ?? null) as { id?: number; nome?: string } | null

  if (!medicoPayload?.id) {
    return null
  }

  return {
    medicoId: medicoPayload.id,
    nomeLido: String(resultado.medico_nome_lido ?? medicoPayload.nome ?? ''),
    sugestaoPortal: String(resultado.medico_sugestao_portal ?? ''),
    similaridade: Number(resultado.medico_similaridade ?? 0),
  }
}

function statusTone(status: string): NonNullable<BadgeProps['tone']> {
  if (attention(status)) {
    return 'perigo'
  }

  if (status === 'succeeded') {
    return 'sucesso'
  }

  return 'info'
}

export function AutomacoesPage() {
  const { id } = useParams()
  const location = useLocation()
  const voltarPara = (location.state as { from?: string } | null)?.from ?? '/automacoes'
  const detalheId = id && /^\d+$/.test(id) ? Number(id) : null
  const { filters, page, setFilters, setPage, searchParams } = useListaNaUrl(defaultFilters)
  const [draftFilters, setDraftFilters] = useState(filters)
  const automacoesQuery = useAutomacoes(filters, page)
  const detalheQuery = useAutomacao(detalheId)
  const reprocessar = useReprocessarAutomacao()
  const atualizarNomeMedico = useAtualizarNomeMedico()
  const confirmar = useConfirm()
  const [progressoExecucaoId, setProgressoExecucaoId] = useState<number | null>(null)
  const [nomeCorrigido, setNomeCorrigido] = useState('')
  const automacoes = automacoesQuery.data?.data ?? []
  const totalPages = automacoesQuery.data?.meta?.last_page ?? 1
  const attentionCount = automacoes.filter((item) => item.precisa_atencao).length
  const query = searchParams.toString()
  const fromHref = query ? `/automacoes?${query}` : '/automacoes'
  const execucaoDetalhe = detalheId !== null ? detalheQuery.data : undefined
  const medicoAmbiguo = execucaoDetalhe ? medicoAmbiguoInfo(execucaoDetalhe) : null

  // Só assume a sugestão da Unimed como valor inicial do campo editável uma
  // vez por execução — depois disso, o texto é do operador.
  useEffect(() => {
    if (medicoAmbiguo) {
      setNomeCorrigido(medicoAmbiguo.sugestaoPortal)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [execucaoDetalhe?.id])

  const handleFilterSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFilters(draftFilters)
  }

  const handleReprocessar = async (execucaoId: number) => {
    const ok = await confirmar({
      titulo: 'Tentar novamente',
      descricao: `Reenfileira a execução #${execucaoId} para rodar de novo. Confirma?`,
      confirmarTexto: 'Tentar novamente',
      variante: 'primario',
    })

    if (!ok) {
      return
    }

    try {
      const nova = await reprocessar.mutateAsync(execucaoId)
      setProgressoExecucaoId(nova.id)
    } catch (error) {
      window.alert(getHttpErrorMessage(error, 'Não foi possível reprocessar a execução.'))
    }
  }

  const handleConfirmarNomeMedico = async (execucaoId: number, medicoId: number, nome: string) => {
    const ok = await confirmar({
      titulo: 'Confirmar médico e tentar novamente',
      descricao: `Atualiza o cadastro do médico para "${nome}" e reenfileira a execução #${execucaoId}. Confirma?`,
      confirmarTexto: 'Confirmar e tentar novamente',
      variante: 'primario',
    })

    if (!ok) {
      return
    }

    try {
      await atualizarNomeMedico.mutateAsync({ id: medicoId, nome })
      const nova = await reprocessar.mutateAsync(execucaoId)
      setProgressoExecucaoId(nova.id)
    } catch (error) {
      window.alert(getHttpErrorMessage(error, 'Não foi possível confirmar o médico e reprocessar.'))
    }
  }

  if (detalheId !== null) {
    const execucao = execucaoDetalhe

    return (
      <>
      <div className="space-y-6" data-testid="automacao-detalhe-page">
        <Link to={voltarPara} className="inline-flex min-h-6 items-center text-corpo font-semibold text-cyan-200 hover:text-cyan-100">
          ← Voltar para automações
        </Link>

        {detalheQuery.isLoading ? (
          <div className="rounded-superficie border border-linha bg-fundo p-6 shadow-e1 text-slate-300">
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
                  <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Automação</p>
                  <h2 className="mt-2 text-display font-semibold text-white">Execução #{execucao.id}</h2>
                  <p className="mt-2 text-corpo text-slate-300">{execucao.operacao}</p>
                </div>
                <Badge tone={statusTone(execucao.status)} className="w-fit">
                  {execucao.status}
                </Badge>
              </div>

              {execucao.precisa_atencao ? (
                <div className="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 p-4 text-corpo text-rose-100">
                  Esta execução precisa de atenção operacional.
                </div>
              ) : attention(execucao.status) ? (
                <div className="mt-4 rounded-superficie border border-linha bg-fundo p-4 shadow-e1 text-corpo text-slate-300">
                  Esta execução falhou, mas uma execução mais recente desta guia já teve sucesso — não
                  há mais atenção pendente aqui.
                </div>
              ) : null}

              {medicoAmbiguo ? (
                <div
                  className="mt-4 space-y-3 rounded-2xl border border-amber-400/30 bg-amber-500/10 p-4 text-corpo text-amber-100"
                  data-testid="medico-ambiguo-confirmacao"
                >
                  <p>
                    Não foi possível confirmar com segurança o médico solicitante na Unimed.
                    <br />
                    Nome lido: <strong>{medicoAmbiguo.nomeLido}</strong>
                    <br />
                    Sugestão encontrada no portal: <strong>{medicoAmbiguo.sugestaoPortal}</strong> (
                    {medicoAmbiguo.similaridade}% de similaridade)
                  </p>

                  <label className="block space-y-2">
                    <span className="text-meta uppercase tracking-[0.2em] text-amber-200/80">
                      Nome correto do médico
                    </span>
                    <input
                      value={nomeCorrigido}
                      onChange={(event) => setNomeCorrigido(event.target.value)}
                      className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20"
                      data-testid="medico-ambiguo-nome-input"
                    />
                  </label>

                  <Botao
                    type="button"
                    variante="primario"
                    disabled={
                      atualizarNomeMedico.isPending || reprocessar.isPending || nomeCorrigido.trim() === ''
                    }
                    onClick={() =>
                      void handleConfirmarNomeMedico(execucao.id, medicoAmbiguo.medicoId, nomeCorrigido.trim())
                    }
                    data-testid="medico-ambiguo-confirmar"
                  >
                    Confirmar e tentar novamente
                  </Botao>
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
                className="inline-flex items-center justify-center mt-5 rounded-2xl border border-cyan-400/30 bg-cyan-400/10 h-10 px-4 text-corpo font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
                data-testid="automacao-reprocessar"
              >
                Reprocessar
              </button>
            </section>

            <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
              <h3 className="text-subtitulo font-semibold text-white">Timeline</h3>
              <div className="mt-4 space-y-3">
                {execucao.eventos?.map((evento) => (
                  <div key={evento.id} className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1 text-corpo text-slate-200">
                    <p className="font-semibold text-white">{evento.tipo} · {evento.status ?? '-'}</p>
                    <p className="mt-1 text-slate-300">{evento.registrado_em ?? '-'}</p>
                    {evento.evidencias ? (
                      <pre className="mt-3 overflow-auto rounded-2xl bg-slate-950/70 p-3 text-meta text-slate-200">
                        {JSON.stringify(evento.evidencias, null, 2)}
                      </pre>
                    ) : null}
                  </div>
                ))}
                {execucao.eventos?.length === 0 ? (
                  <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">Nenhum evento registrado.</p>
                ) : null}
              </div>
            </section>
          </>
        )}
      </div>

      <AutomacaoProgressoModal
        execucaoId={progressoExecucaoId}
        onClose={() => setProgressoExecucaoId(null)}
        titulo="Tentando novamente"
        descricao="Acompanhe a nova tentativa desta automação."
        mensagemExecutando="O robô está rodando a automação de novo..."
        queryKeysInvalidar={[['automacoes']]}
      />
      </>
    )
  }

  return (
    <>
    <div className="space-y-6" data-testid="automacoes-page">
      <section>
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Automações</p>
        <h2 className="mt-2 text-display font-semibold text-white">Execuções Unimed</h2>
      </section>

      <section className="grid gap-4 md:grid-cols-3">
        <Summary label="Na página" value={String(automacoes.length)} />
        <Summary label="Atenção" value={String(attentionCount)} tone="attention" />
        <Summary label="Filtro" value={filters.numero_guia || filters.status || filters.operacao || 'Todos'} />
      </section>

      <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <form className="grid gap-3 md:grid-cols-5" onSubmit={handleFilterSubmit}>
          <input
            type="text"
            value={draftFilters.numero_guia}
            onChange={(event) => setDraftFilters((current) => ({ ...current, numero_guia: event.target.value }))}
            placeholder="Buscar por nº da guia"
            className={inputClasses()}
            data-testid="automacao-filtro-numero-guia"
          />
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
          <label className="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-white/5 h-10 px-4 text-corpo font-medium text-slate-200">
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
          <table className="w-full border-collapse text-left text-corpo" data-cartoes="md">
            <thead className="bg-fundo text-meta uppercase tracking-[0.25em] text-texto-suave">
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
                  <td data-rotulo="ID" className="px-4 py-4">
                    <Link to={`/automacoes/${execucao.id}`} state={{ from: fromHref }} className="inline-flex min-h-6 items-center font-semibold text-texto decoration-acento/60 underline-offset-4 transition hover:underline hover:text-acento-intenso">
                      #{execucao.id}
                    </Link>
                  </td>
                  <td data-rotulo="Operação" className="px-4 py-4 text-slate-200">{execucao.operacao}</td>
                  <td data-rotulo="Status" className="px-4 py-4">
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge tone={statusTone(execucao.status)}>{execucao.status}</Badge>
                      {['failed', 'needs_attention'].includes(execucao.status) ? (
                        <button
                          type="button"
                          onClick={() => void handleReprocessar(execucao.id)}
                          disabled={reprocessar.isPending}
                          className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
                          data-testid={`automacao-tentar-novamente-${execucao.id}`}
                        >
                          Tentar novamente
                        </button>
                      ) : null}
                    </div>
                  </td>
                  <td data-rotulo="Guia" className="px-4 py-4 text-slate-200">{execucao.guia?.numero_guia ?? execucao.guia_id ?? '-'}</td>
                  <td data-rotulo="Fila" className="px-4 py-4 text-slate-200">{execucao.queued_at ?? '-'}</td>
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

        <Paginacao page={page} totalPages={totalPages} onChange={setPage} />
      </section>
    </div>

    <AutomacaoProgressoModal
      execucaoId={progressoExecucaoId}
      onClose={() => setProgressoExecucaoId(null)}
      titulo="Tentando novamente"
      descricao="Acompanhe a nova tentativa desta automação."
      mensagemExecutando="O robô está rodando a automação de novo..."
      queryKeysInvalidar={[['automacoes']]}
    />
    </>
  )
}

function Info({ label, children }: { label: string; children: string }) {
  return (
    <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
      <p className="text-meta uppercase tracking-[0.25em] text-slate-400">{label}</p>
      <p className="mt-2 break-words text-corpo font-medium text-white">{children}</p>
    </div>
  )
}

function Summary({ label, value, tone }: { label: string; value: string; tone?: 'attention' }) {
  return (
    <article className={`rounded-3xl border p-4 ${tone === 'attention' ? 'border-rose-400/20 bg-rose-500/10' : 'border-white/10 bg-slate-950/40'}`}>
      <p className="text-meta uppercase tracking-[0.25em] text-slate-400">{label}</p>
      <p className="mt-2 text-titulo font-semibold text-white">{value}</p>
    </article>
  )
}

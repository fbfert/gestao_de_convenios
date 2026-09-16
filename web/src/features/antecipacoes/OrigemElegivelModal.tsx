import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { Link } from 'react-router-dom'
import { X } from 'lucide-react'
import { Badge } from '../../components/ui/Badge'
import { Botao } from '../../components/ui/Botao'
import { useFechamentoExplicito } from '../../lib/useFechamentoExplicito'
import { translateStatus } from '../../lib/statusLabels'
import { formatarData } from '../solicitacoes/datas'
import { useSolicitacao } from '../solicitacoes/useSolicitacoes'
import type { AntecipacaoElegivel } from './types'

type OrigemElegivelModalProps = {
  elegivel: AntecipacaoElegivel | null
  onClose: () => void
  /** URL desta listagem, com a busca — vai no `state.from` que as telas de detalhe leem. */
  voltarPara: string
}

/**
 * De onde veio esta entrada da fila.
 *
 * A linha de elegíveis mostra nome, convênio, data prevista e contagem de
 * itens — não dá pra decidir entre Gerar e Ignorar com isso. Aqui abre a
 * solicitação de origem inteira: pedido médico, CIDs, e cada item com a sua
 * guia, que é o que diz se o ciclo faz sentido renovar.
 *
 * Só consulta: fechar não gera nem dispensa nada.
 *
 * As guias vêm da fila (`elegivel.guias`) e não da solicitação porque a fila
 * já traz só as ELEGÍVEIS, com a especialidade e o profissional resolvidos —
 * a solicitação traz todos os itens, inclusive os que não motivaram o aviso.
 * Os dois aparecem, em blocos separados, justamente por dizerem coisas
 * diferentes.
 */
export function OrigemElegivelModal({ elegivel, onClose, voltarPara }: OrigemElegivelModalProps) {
  const solicitacaoQuery = useSolicitacao(elegivel?.solicitacao_id ?? null)
  const fechamento = useFechamentoExplicito(elegivel !== null, onClose)

  if (!elegivel) {
    return null
  }

  const solicitacao = solicitacaoQuery.data

  return (
    <Dialog {...fechamento} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-2xl rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="origem-elegivel-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <DialogTitle className="text-titulo font-semibold">Origem da antecipação</DialogTitle>
                <p className="mt-1 text-corpo text-slate-300">
                  {elegivel.paciente?.nome ?? 'Paciente não informado'} ·{' '}
                  {elegivel.convenio?.nome ?? 'Convênio não informado'}
                </p>
              </div>
              <button
                type="button"
                onClick={onClose}
                className="rounded-full border border-white/10 bg-white/5 p-2 text-white transition hover:bg-white/10"
                aria-label="Fechar"
              >
                <X className="size-4" aria-hidden="true" />
              </button>
            </div>

            <dl className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                <dt className="text-meta uppercase tracking-[0.25em] text-slate-400">
                  Data prevista
                </dt>
                <dd className="mt-1 text-corpo text-white">{formatarData(elegivel.data_alvo)}</dd>
              </div>
              <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                <dt className="text-meta uppercase tracking-[0.25em] text-slate-400">Solicitação</dt>
                <dd className="mt-1 text-corpo text-white">
                  <Link
                    to={`/solicitacoes/${elegivel.solicitacao_id}/editar`}
                    state={{ from: voltarPara }}
                    className="font-medium text-acento underline-offset-2 hover:underline"
                  >
                    #{elegivel.solicitacao_id}
                  </Link>
                  {solicitacao ? (
                    <span className="text-slate-400">
                      {' '}
                      · {translateStatus('solicitacoes', solicitacao.status)}
                    </span>
                  ) : null}
                </dd>
              </div>
              <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                <dt className="text-meta uppercase tracking-[0.25em] text-slate-400">
                  Data do pedido
                </dt>
                <dd className="mt-1 text-corpo text-white">
                  {solicitacao ? formatarData(solicitacao.solicitado_em) : '—'}
                </dd>
              </div>
              <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                <dt className="text-meta uppercase tracking-[0.25em] text-slate-400">
                  Médico solicitante
                </dt>
                <dd className="mt-1 text-corpo text-white">
                  {solicitacao?.medico
                    ? `${solicitacao.medico.nome} · CRM ${solicitacao.medico.crm}${
                        solicitacao.medico.crm_uf ? `/${solicitacao.medico.crm_uf}` : ''
                      }`
                    : '—'}
                </dd>
              </div>
              <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 sm:col-span-2">
                <dt className="text-meta uppercase tracking-[0.25em] text-slate-400">CID</dt>
                <dd className="mt-1 text-corpo text-white">
                  {(solicitacao?.cids ?? []).length > 0
                    ? solicitacao?.cids
                        ?.map((cid) => `${cid.codigo} — ${cid.descricao}`)
                        .join(' · ')
                    : '—'}
                </dd>
              </div>
            </dl>

            <section className="mt-5 space-y-2">
              <h3 className="text-meta uppercase tracking-[0.25em] text-slate-400">
                Guias que motivaram o aviso
              </h3>
              {elegivel.guias.map((guia) => (
                <div
                  key={guia.guia_id}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3"
                  data-testid="origem-elegivel-guia"
                >
                  <div>
                    <p className="text-corpo font-medium text-white">
                      {guia.especialidade_nome ?? 'Especialidade'} —{' '}
                      {guia.profissional_nome ?? 'Profissional'}
                    </p>
                    <p className="text-meta text-slate-400">
                      Guia{' '}
                      <Link
                        to={`/guias/${guia.guia_id}`}
                        state={{ from: voltarPara }}
                        className="font-medium text-acento underline-offset-2 hover:underline"
                      >
                        {guia.numero_guia ?? `#${guia.guia_id}`}
                      </Link>
                    </p>
                  </div>
                  <Badge tone="neutro">{translateStatus('guias', guia.status)}</Badge>
                </div>
              ))}
            </section>

            <section className="mt-5 space-y-2">
              <h3 className="text-meta uppercase tracking-[0.25em] text-slate-400">
                Todos os itens da solicitação
              </h3>

              {solicitacaoQuery.isLoading ? (
                <p className="text-corpo text-slate-300">Carregando a solicitação...</p>
              ) : null}

              {solicitacaoQuery.isError ? (
                <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
                  Não foi possível carregar a solicitação de origem.
                </p>
              ) : null}

              {(solicitacao?.itens ?? []).map((item) => (
                <div
                  key={item.id}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3"
                  data-testid="origem-elegivel-item"
                >
                  <div>
                    <p className="text-corpo font-medium text-white">
                      {item.especialidade?.nome ?? 'Especialidade'} —{' '}
                      {item.profissional?.nome ?? 'Profissional'}
                    </p>
                    <p className="text-meta text-slate-400">
                      {item.quantidade} sessão(ões)
                      {item.total_na_cadeia && item.total_na_cadeia > 1
                        ? ` · renovação ${item.posicao_na_cadeia} de ${item.total_na_cadeia}`
                        : ''}
                      {item.guia
                        ? ` · guia ${item.guia.numero_guia ?? `#${item.guia.id}`}`
                        : ' · sem guia'}
                    </p>
                  </div>
                  {item.guia ? (
                    <Badge tone="neutro">{translateStatus('guias', item.guia.status)}</Badge>
                  ) : null}
                </div>
              ))}
            </section>

            <div className="mt-6 flex justify-end">
              <Botao
                type="button"
                variante="secundario"
                onClick={onClose}
                data-testid="origem-elegivel-fechar"
              >
                Fechar
              </Botao>
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}

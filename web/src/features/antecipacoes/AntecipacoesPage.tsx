import { useEffect, useState, type FormEvent } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { Badge } from '../../components/ui/Badge'
import { Botao } from '../../components/ui/Botao'
import { useConfirm } from '../../components/ui/ConfirmDialog'
import { Paginacao } from '../../components/ui/Paginacao'
import { Select } from '../../components/ui/Select'
import { Tooltip, iconeLupa } from '../../components/ui/Tooltip'
import { useListaNaUrl } from '../../lib/useListaNaUrl'
import { useConvenios } from '../../lib/queries/useReferenceData'
import { formatarData } from '../solicitacoes/datas'
import { useSolicitacao } from '../solicitacoes/useSolicitacoes'
import type { Solicitacao } from '../solicitacoes/types'
import { usePode } from '../../lib/permissoes'
import { AntecipacaoTooltipDetalhe } from './AntecipacaoTooltipDetalhe'
import { IgnorarAntecipacaoModal } from './IgnorarAntecipacaoModal'
import { OrigemElegivelModal } from './OrigemElegivelModal'
import { SelecionarItensAntecipacaoModal } from './SelecionarItensAntecipacaoModal'
import { SelecionarSolicitacaoModal } from './SelecionarSolicitacaoModal'
import {
  getHttpErrorMessage,
  useAntecipacoes,
  useAntecipacoesElegiveis,
  useDesfazerAntecipacaoIgnorada,
} from './useAntecipacoes'
import type { AntecipacaoElegivel, AntecipacaoFilters, AntecipacaoStatus } from './types'

function statusTone(status: AntecipacaoStatus): 'neutro' | 'sucesso' {
  return status === 'gerada' ? 'sucesso' : 'neutro'
}

function statusLabel(status: AntecipacaoStatus): string {
  return { gerada: 'Gerada', ignorada: 'Ignorada' }[status]
}

const filtrosPadrao: AntecipacaoFilters = {
  status: '',
  paciente_nome: '',
  numero_guia: '',
  convenio_id: '',
  data_de: '',
  data_ate: '',
}

function campoClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function AntecipacoesPage() {
  const pode = usePode()
  const location = useLocation()
  const confirmar = useConfirm()

  // Busca e página vivem na URL, e não em `useState`: abrir uma guia do
  // histórico e voltar devolvia a página 1 sem filtro nenhum. Mesmo padrão
  // das outras listagens do sistema.
  const { filters, page, setFilters, setPage, searchParams } = useListaNaUrl(filtrosPadrao)
  const [draftFilters, setDraftFilters] = useState(filters)
  const [erroDesfazer, setErroDesfazer] = useState<string | null>(null)

  // Elegível clicado em "Gerar" (ou aberto direto pelo alerta) — busca a
  // solicitação inteira (com itens+guia) antes de abrir o checklist, já que
  // a fila só traz um resumo.
  const [elegivelSolicitacaoId, setElegivelSolicitacaoId] = useState<number | null>(null)
  const solicitacaoElegivelQuery = useSolicitacao(elegivelSolicitacaoId)

  const [manualModalAberto, setManualModalAberto] = useState(false)
  const [manualSolicitacao, setManualSolicitacao] = useState<Solicitacao | null>(null)
  const [elegivelParaIgnorar, setElegivelParaIgnorar] = useState<AntecipacaoElegivel | null>(null)
  const [elegivelParaOrigem, setElegivelParaOrigem] = useState<AntecipacaoElegivel | null>(null)

  const elegiveisQuery = useAntecipacoesElegiveis(pode('antecipacoes.view'))
  const historicoQuery = useAntecipacoes(filters, page)
  const conveniosQuery = useConvenios()
  const desfazer = useDesfazerAntecipacaoIgnorada()

  const podeGerenciar = pode('antecipacoes.manage')
  const convenios = conveniosQuery.data ?? []
  const query = searchParams.toString()

  // A URL desta listagem viaja nos links, no `state.from` que GuiaDetalhePage
  // e SolicitacaoEditarPage já leem: o "Voltar" do detalhe reabre exatamente
  // esta página, com esta busca — e não a listagem zerada.
  const voltarPara = query ? `/antecipacoes?${query}` : '/antecipacoes'

  // Veio do botão "Gerar Antecipação" do alerta (Central de Alertas) — abre
  // a checklist direto, sem precisar achar a solicitação na fila manualmente.
  useEffect(() => {
    const state = location.state as { abrirGeracaoParaSolicitacao?: number } | null
    if (state?.abrirGeracaoParaSolicitacao) {
      setElegivelSolicitacaoId(state.abrirGeracaoParaSolicitacao)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // A URL é a fonte da verdade da busca; o rascunho do formulário precisa
  // acompanhá-la quando ela muda por fora (botão Voltar, link com filtro).
  useEffect(() => {
    setDraftFilters(filters)
  }, [filters])

  const solicitacaoParaModal = manualSolicitacao ?? solicitacaoElegivelQuery.data ?? null
  const modalAberto = elegivelSolicitacaoId !== null || manualSolicitacao !== null

  const fecharModalSelecao = () => {
    setElegivelSolicitacaoId(null)
    setManualSolicitacao(null)
  }

  const aplicarBusca = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFilters(draftFilters)
  }

  const limparBusca = () => {
    setDraftFilters(filtrosPadrao)
    setFilters(filtrosPadrao)
  }

  const handleDesfazer = async (antecipacaoId: number, paciente: string) => {
    setErroDesfazer(null)

    const ok = await confirmar({
      titulo: 'Desfazer esta dispensa',
      descricao: (
        <>
          A antecipação de <strong className="font-semibold text-texto">{paciente}</strong> sai do
          histórico e a solicitação volta para a lista de elegíveis, se ainda tiver guia na data.
          Nada que já foi gerado é afetado.
        </>
      ),
      confirmarTexto: 'Desfazer',
      variante: 'perigo',
    })

    if (!ok) {
      return
    }

    try {
      await desfazer.mutateAsync(antecipacaoId)
    } catch (error) {
      setErroDesfazer(getHttpErrorMessage(error, 'Não foi possível desfazer esta antecipação.'))
    }
  }

  const totalPages = historicoQuery.data?.meta?.last_page ?? 1

  return (
    <div className="space-y-8" data-testid="antecipacoes-page">
      <header className="space-y-1">
        <h1 className="text-titulo font-semibold text-white">Antecipações</h1>
        <p className="text-corpo text-slate-300">
          Guias aprovadas perto da data de gerar o próximo ciclo, e o histórico do que já foi
          antecipado manualmente. Antecipar nunca envia nada sozinho pra automação — gera os itens
          e, quando possível, a guia, na mesma solicitação, pra você revisar e enviar quando quiser.
        </p>
      </header>

      <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <h2 className="text-subtitulo font-semibold text-white">Elegíveis</h2>

        {elegiveisQuery.isLoading ? <p className="text-corpo text-slate-300">Carregando...</p> : null}

        {!elegiveisQuery.isLoading && (elegiveisQuery.data ?? []).length === 0 ? (
          <p className="text-corpo text-slate-400">Nenhuma guia elegível no momento.</p>
        ) : null}

        <div className="space-y-2">
          {(elegiveisQuery.data ?? []).map((item) => (
            <div
              key={item.solicitacao_id}
              className="flex flex-col gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
              data-testid="antecipacao-elegivel-item"
            >
              <div>
                {/* O nome abre a origem: é o único lugar onde dá pra conferir
                    de qual pedido essa renovação vem antes de decidir. */}
                <button
                  type="button"
                  onClick={() => setElegivelParaOrigem(item)}
                  className="text-left text-corpo font-medium text-white underline-offset-4 transition hover:text-acento hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/40"
                  data-testid={`antecipacao-elegivel-origem-${item.solicitacao_id}`}
                >
                  {item.paciente?.nome ?? 'Paciente não informado'} ·{' '}
                  {item.convenio?.nome ?? 'Convênio não informado'}
                </button>
                <p className="text-meta text-slate-400">
                  Previsto para {formatarData(item.data_alvo)} · {item.guias.length} item(ns)
                </p>
              </div>
              {podeGerenciar ? (
                <div className="flex items-center gap-2">
                  <Botao
                    type="button"
                    variante="secundario"
                    onClick={() => setElegivelParaIgnorar(item)}
                    data-testid={`antecipacao-elegivel-ignorar-${item.solicitacao_id}`}
                  >
                    Ignorar
                  </Botao>
                  <Botao
                    type="button"
                    variante="primario"
                    disabled={solicitacaoElegivelQuery.isLoading && elegivelSolicitacaoId === item.solicitacao_id}
                    onClick={() => setElegivelSolicitacaoId(item.solicitacao_id)}
                    data-testid={`antecipacao-elegivel-gerar-${item.solicitacao_id}`}
                  >
                    Gerar
                  </Botao>
                </div>
              ) : null}
            </div>
          ))}
        </div>
      </section>

      <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <h2 className="text-subtitulo font-semibold text-white">Histórico</h2>
          {podeGerenciar ? (
            <Botao
              type="button"
              variante="primario"
              onClick={() => setManualModalAberto(true)}
              data-testid="antecipacoes-nova-manual"
            >
              Nova antecipação manual
            </Botao>
          ) : null}
        </div>

        <form className="flex flex-wrap items-end gap-3" onSubmit={aplicarBusca}>
          <label className="flex flex-col gap-2">
            <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Paciente</span>
            <div className="w-full sm:w-52">
              <input
                type="text"
                value={draftFilters.paciente_nome}
                onChange={(event) =>
                  setDraftFilters((atual) => ({ ...atual, paciente_nome: event.target.value }))
                }
                placeholder="Buscar por nome..."
                className={campoClasses()}
                data-testid="antecipacoes-filtro-paciente"
              />
            </div>
          </label>

          <label className="flex flex-col gap-2">
            <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Nº Guia</span>
            <div className="w-full sm:w-44">
              <input
                type="text"
                value={draftFilters.numero_guia}
                onChange={(event) =>
                  setDraftFilters((atual) => ({ ...atual, numero_guia: event.target.value }))
                }
                placeholder="Buscar por número..."
                className={campoClasses()}
                data-testid="antecipacoes-filtro-guia"
              />
            </div>
          </label>

          <label className="flex flex-col gap-2">
            <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Convênio</span>
            <div className="w-full sm:w-44">
              <Select
                value={draftFilters.convenio_id}
                onChange={(event) =>
                  setDraftFilters((atual) => ({ ...atual, convenio_id: event.target.value }))
                }
                className={campoClasses()}
                data-testid="antecipacoes-filtro-convenio"
              >
                <option value="">Todos</option>
                {convenios.map((convenio) => (
                  <option key={convenio.id} value={convenio.id}>
                    {convenio.nome}
                  </option>
                ))}
              </Select>
            </div>
          </label>

          <label className="flex flex-col gap-2">
            <span className="text-meta uppercase tracking-[0.25em] text-slate-400">De</span>
            <div className="w-full sm:w-40">
              <input
                type="date"
                value={draftFilters.data_de}
                onChange={(event) =>
                  setDraftFilters((atual) => ({ ...atual, data_de: event.target.value }))
                }
                className={campoClasses()}
                data-testid="antecipacoes-filtro-data-de"
              />
            </div>
          </label>

          <label className="flex flex-col gap-2">
            <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Até</span>
            <div className="w-full sm:w-40">
              <input
                type="date"
                value={draftFilters.data_ate}
                onChange={(event) =>
                  setDraftFilters((atual) => ({ ...atual, data_ate: event.target.value }))
                }
                className={campoClasses()}
                data-testid="antecipacoes-filtro-data-ate"
              />
            </div>
          </label>

          <label className="flex flex-col gap-2">
            <span className="text-meta uppercase tracking-[0.25em] text-slate-400">Status</span>
            <div className="w-full sm:w-40">
              <Select
                value={draftFilters.status}
                onChange={(event) =>
                  setDraftFilters((atual) => ({
                    ...atual,
                    status: event.target.value as '' | AntecipacaoStatus,
                  }))
                }
                className={campoClasses()}
                data-testid="antecipacoes-filtro-status"
              >
                <option value="">Todas</option>
                <option value="gerada">Gerada</option>
                <option value="ignorada">Ignorada</option>
              </Select>
            </div>
          </label>

          <Botao type="submit" variante="secundario">
            Aplicar
          </Botao>
          <Botao
            type="button"
            variante="fantasma"
            onClick={limparBusca}
            data-testid="antecipacoes-filtro-limpar"
          >
            Limpar
          </Botao>
        </form>

        {historicoQuery.isLoading ? <p className="text-corpo text-slate-300">Carregando...</p> : null}

        {historicoQuery.isError ? (
          <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
            Não foi possível carregar o histórico.
          </p>
        ) : null}

        {erroDesfazer ? (
          <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
            {erroDesfazer}
          </p>
        ) : null}

        {!historicoQuery.isLoading && (historicoQuery.data?.data ?? []).length === 0 ? (
          <p className="text-corpo text-slate-400">
            Nenhuma antecipação encontrada com esses critérios.
          </p>
        ) : null}

        <div className="space-y-2">
          {(historicoQuery.data?.data ?? []).map((antecipacao) => (
            <div
              key={antecipacao.id}
              className="flex flex-col gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
              data-testid={`antecipacao-historico-item-${antecipacao.id}`}
            >
              <div className="space-y-1">
                <p className="flex flex-wrap items-center gap-2 text-corpo font-medium text-white">
                  <span>
                    {antecipacao.solicitacao_origem?.paciente?.nome ?? 'Paciente não informado'} ·{' '}
                    {antecipacao.solicitacao_origem?.convenio?.nome ?? 'Convênio não informado'}
                  </span>
                  <Tooltip rotulo="Detalhes desta antecipação" icone={iconeLupa}>
                    <AntecipacaoTooltipDetalhe antecipacao={antecipacao} />
                  </Tooltip>
                </p>
                <p className="text-meta text-slate-400">
                  {antecipacao.status === 'gerada'
                    ? `${antecipacao.itens_selecionados?.length ?? 0} item(ns) gerado(s)`
                    : 'Dispensada'}{' '}
                  em {formatarData(antecipacao.created_at)}
                  {antecipacao.criado_por ? ` por ${antecipacao.criado_por.nome}` : ''}
                  {antecipacao.observacoes ? ` · ${antecipacao.observacoes}` : ''}
                </p>

                {/*
                  As guias que a antecipação gerou, com link para cada uma —
                  era a pergunta que a tela não respondia: "gerou o quê?".

                  Item sem guia não é erro: em convênio automatizado a guia
                  chega depois, quando a operadora responde. Por isso o texto
                  diz o que está acontecendo, em vez de deixar um espaço vazio.
                */}
                {antecipacao.status === 'gerada' && (antecipacao.itens_gerados?.length ?? 0) > 0 ? (
                  <p
                    className="text-meta text-slate-400"
                    data-testid={`antecipacao-guias-${antecipacao.id}`}
                  >
                    Guias:{' '}
                    {antecipacao.itens_gerados?.map((item, indice) => (
                      <span key={item.item_gerado_id ?? indice}>
                        {indice > 0 ? ' · ' : ''}
                        {item.guia ? (
                          <Link
                            to={`/guias/${item.guia.id}`}
                            state={{ from: voltarPara }}
                            className="font-medium text-acento underline-offset-2 hover:underline"
                            title={item.especialidade ?? undefined}
                          >
                            {item.guia.numero ?? `#${item.guia.id}`}
                          </Link>
                        ) : (
                          <span title={item.especialidade ?? undefined}>
                            {item.especialidade ? `${item.especialidade}: ` : ''}aguardando a
                            operadora
                          </span>
                        )}
                      </span>
                    ))}
                  </p>
                ) : null}
              </div>
              <div className="flex items-center gap-2">
                <Badge tone={statusTone(antecipacao.status)}>{statusLabel(antecipacao.status)}</Badge>
                {/* Só a dispensa se desfaz. Desfazer uma `gerada` teria de
                    apagar itens e guias já criados — a API recusa. */}
                {podeGerenciar && antecipacao.status === 'ignorada' ? (
                  <Botao
                    type="button"
                    variante="secundario"
                    tamanho="sm"
                    disabled={desfazer.isPending}
                    onClick={() =>
                      void handleDesfazer(
                        antecipacao.id,
                        antecipacao.solicitacao_origem?.paciente?.nome ?? 'paciente não informado',
                      )
                    }
                    data-testid={`antecipacao-desfazer-${antecipacao.id}`}
                  >
                    Desfazer
                  </Botao>
                ) : null}
              </div>
            </div>
          ))}
        </div>

        {totalPages > 1 ? (
          <Paginacao page={page} totalPages={totalPages} onChange={setPage} />
        ) : null}
      </section>

      <SelecionarSolicitacaoModal
        open={manualModalAberto}
        onClose={() => setManualModalAberto(false)}
        onSelecionar={(solicitacao) => setManualSolicitacao(solicitacao)}
      />

      <SelecionarItensAntecipacaoModal
        open={modalAberto}
        solicitacao={solicitacaoParaModal}
        onClose={fecharModalSelecao}
      />

      <IgnorarAntecipacaoModal
        elegivel={elegivelParaIgnorar}
        onClose={() => setElegivelParaIgnorar(null)}
      />

      <OrigemElegivelModal
        elegivel={elegivelParaOrigem}
        onClose={() => setElegivelParaOrigem(null)}
        voltarPara={voltarPara}
      />
    </div>
  )
}

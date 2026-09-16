import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { Badge } from '../../components/ui/Badge'
import { Botao } from '../../components/ui/Botao'
import { Select } from '../../components/ui/Select'
import { formatarData } from '../solicitacoes/datas'
import { useSolicitacao } from '../solicitacoes/useSolicitacoes'
import type { Solicitacao } from '../solicitacoes/types'
import { usePode } from '../../lib/permissoes'
import { SelecionarItensAntecipacaoModal } from './SelecionarItensAntecipacaoModal'
import { SelecionarSolicitacaoModal } from './SelecionarSolicitacaoModal'
import {
  useAntecipacoes,
  useAntecipacoesElegiveis,
  useIgnorarAntecipacao,
} from './useAntecipacoes'
import type { AntecipacaoStatus } from './types'

function statusTone(status: AntecipacaoStatus): 'neutro' | 'sucesso' {
  return status === 'gerada' ? 'sucesso' : 'neutro'
}

function statusLabel(status: AntecipacaoStatus): string {
  return { gerada: 'Gerada', ignorada: 'Ignorada' }[status]
}

export function AntecipacoesPage() {
  const pode = usePode()
  const location = useLocation()
  const [historicoStatus, setHistoricoStatus] = useState<'' | AntecipacaoStatus>('')
  const [page, setPage] = useState(1)

  // Elegível clicado em "Gerar" (ou aberto direto pelo alerta) — busca a
  // solicitação inteira (com itens+guia) antes de abrir o checklist, já que
  // a fila só traz um resumo.
  const [elegivelSolicitacaoId, setElegivelSolicitacaoId] = useState<number | null>(null)
  const solicitacaoElegivelQuery = useSolicitacao(elegivelSolicitacaoId)

  const [manualModalAberto, setManualModalAberto] = useState(false)
  const [manualSolicitacao, setManualSolicitacao] = useState<Solicitacao | null>(null)

  const elegiveisQuery = useAntecipacoesElegiveis(pode('antecipacoes.view'))
  const historicoQuery = useAntecipacoes({ status: historicoStatus }, page)
  const ignorar = useIgnorarAntecipacao()

  const podeGerenciar = pode('antecipacoes.manage')

  // Veio do botão "Gerar Antecipação" do alerta (Central de Alertas) — abre
  // a checklist direto, sem precisar achar a solicitação na fila manualmente.
  useEffect(() => {
    const state = location.state as { abrirGeracaoParaSolicitacao?: number } | null
    if (state?.abrirGeracaoParaSolicitacao) {
      setElegivelSolicitacaoId(state.abrirGeracaoParaSolicitacao)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const solicitacaoParaModal = manualSolicitacao ?? solicitacaoElegivelQuery.data ?? null
  const modalAberto = elegivelSolicitacaoId !== null || manualSolicitacao !== null

  const fecharModalSelecao = () => {
    setElegivelSolicitacaoId(null)
    setManualSolicitacao(null)
  }

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
                <p className="text-corpo font-medium text-white">
                  {item.paciente?.nome ?? 'Paciente não informado'} · {item.convenio?.nome ?? 'Convênio não informado'}
                </p>
                <p className="text-meta text-slate-400">
                  Previsto para {formatarData(item.data_alvo)} · {item.guias.length} item(ns)
                </p>
              </div>
              {podeGerenciar ? (
                <div className="flex items-center gap-2">
                  <Botao
                    type="button"
                    variante="secundario"
                    disabled={ignorar.isPending}
                    onClick={() => ignorar.mutate({ solicitacao_origem_id: item.solicitacao_id })}
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
          <div className="flex items-center gap-3">
            <Select
              value={historicoStatus}
              onChange={(event) => {
                setHistoricoStatus(event.target.value as '' | AntecipacaoStatus)
                setPage(1)
              }}
              data-testid="antecipacoes-filtro-status"
            >
              <option value="">Todas</option>
              <option value="gerada">Gerada</option>
              <option value="ignorada">Ignorada</option>
            </Select>
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
        </div>

        {historicoQuery.isLoading ? <p className="text-corpo text-slate-300">Carregando...</p> : null}

        {!historicoQuery.isLoading && (historicoQuery.data?.data ?? []).length === 0 ? (
          <p className="text-corpo text-slate-400">Nenhuma antecipação registrada ainda.</p>
        ) : null}

        <div className="space-y-2">
          {(historicoQuery.data?.data ?? []).map((antecipacao) => (
            <div
              key={antecipacao.id}
              className="flex flex-col gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
              data-testid={`antecipacao-historico-item-${antecipacao.id}`}
            >
              <div className="space-y-1">
                <p className="text-corpo font-medium text-white">
                  {antecipacao.solicitacao_origem?.paciente?.nome ?? 'Paciente não informado'} ·{' '}
                  {antecipacao.solicitacao_origem?.convenio?.nome ?? 'Convênio não informado'}
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
              </div>
            </div>
          ))}
        </div>

        {historicoQuery.data?.meta && historicoQuery.data.meta.last_page > 1 ? (
          <div className="flex items-center justify-center gap-3 pt-2">
            <Botao
              type="button"
              variante="secundario"
              disabled={page <= 1}
              onClick={() => setPage((atual) => Math.max(1, atual - 1))}
            >
              Anterior
            </Botao>
            <span className="text-meta text-slate-400">
              Página {historicoQuery.data.meta.current_page} de {historicoQuery.data.meta.last_page}
            </span>
            <Botao
              type="button"
              variante="secundario"
              disabled={page >= historicoQuery.data.meta.last_page}
              onClick={() => setPage((atual) => atual + 1)}
            >
              Próxima
            </Botao>
          </div>
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

    </div>
  )
}

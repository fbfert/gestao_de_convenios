import { useState } from 'react'
import { Badge } from '../../components/ui/Badge'
import { Botao } from '../../components/ui/Botao'
import { ConfirmarExclusao } from '../../components/ui/ConfirmarExclusao'
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
  useAtualizarAntecipacao,
  useRemoverAntecipacao,
} from './useAntecipacoes'
import type { Antecipacao, AntecipacaoStatus } from './types'

function statusTone(status: AntecipacaoStatus): 'neutro' | 'sucesso' | 'alerta' {
  if (status === 'gerada') return 'sucesso'
  if (status === 'ignorada') return 'neutro'
  return 'alerta'
}

function statusLabel(status: AntecipacaoStatus): string {
  return { pendente: 'Pendente', gerada: 'Gerada', ignorada: 'Ignorada' }[status]
}

export function AntecipacoesPage() {
  const pode = usePode()
  const [historicoStatus, setHistoricoStatus] = useState<'' | AntecipacaoStatus>('')
  const [page, setPage] = useState(1)

  // Elegível clicado em "Gerar" — busca a solicitação inteira (com
  // itens+guia) antes de abrir o checklist, já que a fila só traz um resumo.
  const [elegivelSolicitacaoId, setElegivelSolicitacaoId] = useState<number | null>(null)
  const solicitacaoElegivelQuery = useSolicitacao(elegivelSolicitacaoId)

  const [manualModalAberto, setManualModalAberto] = useState(false)
  const [manualSolicitacao, setManualSolicitacao] = useState<Solicitacao | null>(null)

  const [antecipacaoAExcluir, setAntecipacaoAExcluir] = useState<Antecipacao | null>(null)

  const elegiveisQuery = useAntecipacoesElegiveis(pode('antecipacoes.view'))
  const historicoQuery = useAntecipacoes({ status: historicoStatus }, page)
  const atualizar = useAtualizarAntecipacao()
  const remover = useRemoverAntecipacao()

  const podeGerenciar = pode('antecipacoes.manage')

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
          antecipado manualmente. Antecipar nunca gera nem envia nada sozinho — sempre passa por
          Nova Solicitação, com os dados já pré-preenchidos, pra você revisar e confirmar.
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
                <Botao
                  type="button"
                  variante="secundario"
                  disabled={solicitacaoElegivelQuery.isLoading && elegivelSolicitacaoId === item.solicitacao_id}
                  onClick={() => setElegivelSolicitacaoId(item.solicitacao_id)}
                  data-testid={`antecipacao-elegivel-gerar-${item.solicitacao_id}`}
                >
                  Gerar
                </Botao>
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
              <option value="pendente">Pendente</option>
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
              <div>
                <p className="text-corpo font-medium text-white">
                  {antecipacao.solicitacao_origem?.paciente?.nome ?? 'Paciente não informado'} ·{' '}
                  {antecipacao.solicitacao_origem?.convenio?.nome ?? 'Convênio não informado'}
                </p>
                <p className="text-meta text-slate-400">
                  Criada em {formatarData(antecipacao.created_at)}
                  {antecipacao.criado_por ? ` por ${antecipacao.criado_por.nome}` : ''}
                  {antecipacao.observacoes ? ` · ${antecipacao.observacoes}` : ''}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Badge tone={statusTone(antecipacao.status)}>{statusLabel(antecipacao.status)}</Badge>
                {podeGerenciar && antecipacao.status === 'pendente' ? (
                  <Botao
                    type="button"
                    variante="secundario"
                    disabled={atualizar.isPending}
                    onClick={() => atualizar.mutate({ id: antecipacao.id, status: 'ignorada' })}
                    data-testid={`antecipacao-ignorar-${antecipacao.id}`}
                  >
                    Ignorar
                  </Botao>
                ) : null}
                {podeGerenciar && antecipacao.status === 'ignorada' ? (
                  <Botao
                    type="button"
                    variante="secundario"
                    disabled={atualizar.isPending}
                    onClick={() => atualizar.mutate({ id: antecipacao.id, status: 'pendente' })}
                    data-testid={`antecipacao-reabrir-${antecipacao.id}`}
                  >
                    Reabrir
                  </Botao>
                ) : null}
                {podeGerenciar ? (
                  <Botao
                    type="button"
                    variante="secundario"
                    onClick={() => setAntecipacaoAExcluir(antecipacao)}
                    data-testid={`antecipacao-excluir-${antecipacao.id}`}
                  >
                    Excluir
                  </Botao>
                ) : null}
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

      {antecipacaoAExcluir ? (
        <ConfirmarExclusao
          titulo="Excluir antecipação"
          descricao="Remove só o registro de acompanhamento — não afeta nenhuma guia ou solicitação já gerada."
          alvo={`Antecipação #${antecipacaoAExcluir.id}`}
          confirmando={remover.isPending}
          onConfirmar={() => {
            remover.mutate(antecipacaoAExcluir.id, {
              onSuccess: () => setAntecipacaoAExcluir(null),
            })
          }}
          onCancelar={() => setAntecipacaoAExcluir(null)}
        />
      ) : null}
    </div>
  )
}

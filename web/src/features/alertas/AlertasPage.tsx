import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import { useOcultarAlertaAntecipacaoGuia, useOcultarAlertaNegacaoGuia } from '../guias/useGuias'
import { NIVEIS, rotuloDaChave } from './nivel'
import { useAlertas, useReconhecerAlerta, useSilenciarAlerta, type FiltrosAlertas } from './useAlertas'
import type { Alerta, NivelAlerta, SituacaoAlerta } from './types'

const campo =
  'h-10 rounded-campo border border-borda-campo bg-superficie px-3 text-corpo text-texto'

function formatarData(valor: string | null) {
  if (!valor) return '—'

  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(
    new Date(valor),
  )
}

function Etiqueta({ nivel }: { nivel: NivelAlerta }) {
  const { rotulo, glifo, etiqueta } = NIVEIS[nivel]

  return (
    <span
      className={`inline-flex items-center gap-1 rounded-pilula px-2 py-1 text-meta font-semibold ${etiqueta}`}
    >
      <span aria-hidden="true">{glifo}</span>
      {rotulo}
    </span>
  )
}

/**
 * Central de alertas.
 *
 * Absorve o antigo banner de guias negadas do dashboard, e por isso carrega as
 * duas ações que ele oferecia: ocultar o alerta da guia e abrir nova solicitação
 * a partir dela. Sem elas, a troca seria regressão para quem opera todo dia.
 */
export function AlertasPage() {
  const [filtros, setFiltros] = useState<FiltrosAlertas>({ situacao: 'aberto' })
  const alertasQuery = useAlertas(filtros)
  const reconhecer = useReconhecerAlerta()
  const silenciar = useSilenciarAlerta()
  const ocultarGuiaNegada = useOcultarAlertaNegacaoGuia()
  const ocultarAntecipacao = useOcultarAlertaAntecipacaoGuia()
  const navigate = useNavigate()

  const alertas = alertasQuery.data?.data ?? []

  const chavesComAcoesDeGuia = ['guia.negada', 'antecipacao.devida']

  const acoesDaGuia = (alerta: Alerta) =>
    chavesComAcoesDeGuia.includes(alerta.chave) && alerta.entidade === 'guias' && alerta.entidade_id !== null

  const ocultarAlertaDaGuia = (alerta: Alerta) => {
    if (alerta.entidade_id === null) return
    if (alerta.chave === 'antecipacao.devida') {
      ocultarAntecipacao.mutate(alerta.entidade_id)
    } else {
      ocultarGuiaNegada.mutate(alerta.entidade_id)
    }
  }

  /**
   * "Guia negada" repete um item (especialidade+profissional) numa
   * Solicitação nova, via query params. "Antecipação devida" é diferente:
   * gera itens novos NA MESMA solicitação (renovação — ver
   * App\Services\AntecipacaoService::criar()), então leva pra /antecipacoes
   * já abrindo a checklist de itens daquela solicitação, em vez de abrir
   * Nova Solicitação.
   */
  const acionarAlerta = (alerta: Alerta) => {
    if (alerta.chave === 'antecipacao.devida') {
      const dados = (alerta.dados ?? {}) as { solicitacao_id?: number | null }
      if (!dados.solicitacao_id) return

      navigate('/antecipacoes', { state: { abrirGeracaoParaSolicitacao: dados.solicitacao_id } })
      return
    }

    const dados = (alerta.dados ?? {}) as Record<string, number | null>
    const params = new URLSearchParams()
    if (dados.paciente_id) params.set('paciente_id', String(dados.paciente_id))
    if (dados.convenio_id) params.set('convenio_id', String(dados.convenio_id))
    if (dados.especialidade_id) params.set('especialidade_id', String(dados.especialidade_id))
    if (dados.profissional_id) params.set('profissional_id', String(dados.profissional_id))

    navigate(`/solicitacoes/nova?${params.toString()}`)
  }

  return (
    <div className="space-y-6" data-testid="alertas-page">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-meta font-semibold uppercase tracking-[0.2em] text-acento">Alertas</p>
          <h2 className="mt-2 text-titulo font-semibold text-texto">O que precisa de você</h2>
        </div>
        <Link
          to="/alertas/configuracoes"
          className="inline-flex h-10 items-center rounded-pilula border border-acento/40 px-4 text-corpo font-semibold text-acento transition hover:bg-acento-suave"
        >
          Configurar regras
        </Link>
      </div>

      <div className="flex flex-wrap gap-3">
        <select
          className={campo}
          value={filtros.situacao ?? 'aberto'}
          onChange={(evento) =>
            setFiltros((atual) => ({ ...atual, situacao: evento.target.value as SituacaoAlerta }))
          }
          aria-label="Situação"
        >
          <option value="aberto">Abertos</option>
          <option value="silenciado">Silenciados</option>
          <option value="resolvido">Resolvidos</option>
        </select>

        <select
          className={campo}
          value={filtros.nivel ?? ''}
          onChange={(evento) =>
            setFiltros((atual) => ({ ...atual, nivel: evento.target.value as NivelAlerta | '' }))
          }
          aria-label="Nível"
        >
          <option value="">Todos os níveis</option>
          <option value="vermelho">Crítico</option>
          <option value="amarelo">Atenção</option>
          <option value="verde">Informativo</option>
        </select>

        <select
          className={campo}
          value={filtros.chave ?? ''}
          onChange={(evento) => setFiltros((atual) => ({ ...atual, chave: evento.target.value }))}
          aria-label="Tipo"
        >
          <option value="">Todos os tipos</option>
          <option value="senha.vencendo">Senha vencendo</option>
          <option value="guia.negada">Guia negada</option>
          <option value="automacao.falhas_em_serie">Automação falhando em série</option>
          <option value="componente.fora">Componente fora do ar</option>
          <option value="antecipacao.devida">Antecipação devida</option>
        </select>
      </div>

      {alertasQuery.isLoading ? (
        <p className="rounded-janela border border-linha bg-superficie p-6 text-corpo text-texto-suave">
          Carregando alertas...
        </p>
      ) : alertas.length === 0 ? (
        <p className="rounded-janela border border-linha bg-superficie p-6 text-corpo text-texto-suave">
          Nenhum alerta nesta situação.
        </p>
      ) : (
        <ul className="space-y-3">
          {alertas.map((alerta) => (
            <li
              key={alerta.id}
              className="rounded-janela border border-linha bg-superficie p-5 shadow-e1"
              data-testid={`alerta-${alerta.id}`}
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <Etiqueta nivel={alerta.nivel} />
                    <span className="text-meta text-texto-suave">{rotuloDaChave(alerta.chave)}</span>
                  </div>
                  <p className="text-corpo font-medium text-texto">{alerta.titulo}</p>
                  {alerta.descricao ? (
                    <p className="text-meta text-texto-suave">{alerta.descricao}</p>
                  ) : null}
                  <p className="text-meta text-texto-suave">
                    Aberto em {formatarData(alerta.aberto_em)}
                    {alerta.reconhecido_por ? ` · visto por ${alerta.reconhecido_por}` : ''}
                  </p>
                </div>

                <div className="flex flex-wrap gap-2">
                  {acoesDaGuia(alerta) ? (
                    <>
                      <Botao
                        variante="secundario"
                        tamanho="sm"
                        onClick={() => ocultarAlertaDaGuia(alerta)}
                        data-testid={`alerta-ocultar-${alerta.id}`}
                      >
                        Ocultar
                      </Botao>
                      <Botao
                        variante="primario"
                        tamanho="sm"
                        onClick={() => acionarAlerta(alerta)}
                        data-testid={`alerta-acao-${alerta.id}`}
                      >
                        {alerta.chave === 'antecipacao.devida' ? 'Gerar Antecipação' : 'Nova Solicitação'}
                      </Botao>
                    </>
                  ) : null}

                  {alerta.resolvido_em === null ? (
                    <>
                      <Botao
                        variante="secundario"
                        tamanho="sm"
                        onClick={() => reconhecer.mutate(alerta.id)}
                        disabled={alerta.reconhecido_em !== null}
                      >
                        {alerta.reconhecido_em ? 'Visto' : 'Marcar como visto'}
                      </Botao>
                      <Botao
                        variante="secundario"
                        tamanho="sm"
                        onClick={() =>
                          silenciar.mutate({
                            id: alerta.id,
                            ate: new Date(Date.now() + 7 * 24 * 3600 * 1000).toISOString(),
                          })
                        }
                      >
                        Silenciar 7 dias
                      </Botao>
                    </>
                  ) : null}
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

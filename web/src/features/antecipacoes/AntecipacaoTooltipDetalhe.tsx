import { formatarData, formatarDataHora } from '../solicitacoes/datas'
import type { Antecipacao } from './types'

/**
 * Conteúdo do tooltip ao lado de "Paciente · Convênio" no histórico.
 *
 * A linha do histórico responde "o quê" e "quando"; o que faltava era o
 * contexto que decide se a ação foi certa: de qual solicitação veio, qual era
 * a data prevista, e — no caso de uma dispensa — por quê. Fica em tooltip e
 * não na linha porque é informação de conferência, consultada em um registro
 * de cada vez, e empilhá-la em 20 linhas tornaria a lista ilegível.
 *
 * Arquivo separado do componente da página para não misturar a árvore de
 * `AntecipacoesPage` com marcação que só existe dentro do painel do
 * `Tooltip`.
 */
export function AntecipacaoTooltipDetalhe({ antecipacao }: { antecipacao: Antecipacao }) {
  const gerada = antecipacao.status === 'gerada'
  const quando = gerada ? antecipacao.gerado_em : antecipacao.ignorado_em
  const itensGerados = antecipacao.itens_gerados ?? []
  const comGuia = itensGerados.filter((item) => item.guia)

  return (
    <span className="block space-y-2">
      <span className="block">
        <span className="block text-meta font-semibold text-white">
          {gerada ? 'Antecipação gerada' : 'Antecipação ignorada'}
        </span>
        <span className="block">
          {formatarDataHora(quando ?? antecipacao.created_at)}
          {antecipacao.criado_por ? ` · ${antecipacao.criado_por.nome}` : ''}
        </span>
      </span>

      <span className="block">
        Solicitação de origem: #{antecipacao.solicitacao_origem?.id ?? '—'}
      </span>

      <span className="block">Data prevista: {formatarData(antecipacao.data_alvo)}</span>

      {gerada ? (
        <span className="block">
          {itensGerados.length} item(ns) criado(s) por renovação nesta mesma solicitação
          {itensGerados.length > 0
            ? ` · ${comGuia.length} com guia, ${itensGerados.length - comGuia.length} aguardando a operadora`
            : ''}
          .
          {itensGerados.length > 0 ? (
            <span className="mt-1 block">
              {itensGerados
                .map(
                  (item) =>
                    `${item.especialidade ?? 'Especialidade'}: ${
                      item.guia ? (item.guia.numero ?? `#${item.guia.id}`) : 'aguardando'
                    }`,
                )
                .join(' · ')}
            </span>
          ) : null}
        </span>
      ) : (
        <span className="block">
          Nenhum item ou guia foi criado. A solicitação saiu da lista de elegíveis.
        </span>
      )}

      <span className="block">
        {antecipacao.observacoes ? (
          <>
            <span className="block text-meta font-semibold text-white">Motivo</span>
            {antecipacao.observacoes}
          </>
        ) : (
          <span className="text-slate-400">Sem motivo registrado.</span>
        )}
      </span>
    </span>
  )
}

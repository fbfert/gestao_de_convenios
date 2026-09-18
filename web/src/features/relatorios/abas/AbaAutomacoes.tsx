import { GraficoBarras, GraficoLinha } from '../graficos/Graficos'
import type { Relatorio, Serie } from '../tipos'

/**
 * A aba de Automações.
 *
 * Responde três perguntas, nesta ordem: o robô trabalhou (execuções por dia),
 * o que quebrou (pareto de erro), e se está mais lento (histograma de duração).
 *
 * O histograma vem depois do pareto de propósito. Duração ruim quase sempre é
 * consequência de erro — tentativa que estoura tempo e falha —, então olhar a
 * causa antes do sintoma poupa a investigação errada.
 */
export function AbaAutomacoes({ relatorio, carregando }: { relatorio?: Relatorio; carregando: boolean }) {
  const serie = (key: string): Serie | undefined =>
    relatorio?.series.find((atual) => atual.key === key)

  return (
    <div className="space-y-4">
      <GraficoLinha
        serie={serie('execucoes_por_dia')}
        carregando={carregando}
        descricao="Pela data em que a execução TERMINOU: o relatório mede trabalho concluído."
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <GraficoBarras serie={serie('execucoes_por_operacao')} carregando={carregando} horizontal />
        <GraficoBarras
          serie={serie('pareto_de_erros')}
          carregando={carregando}
          horizontal
          descricao="Agrupado pelo código que a automação registrou na falha."
        />
        <GraficoBarras
          serie={serie('duracao_das_execucoes')}
          carregando={carregando}
          descricao="Quantas execuções caíram em cada faixa de tempo."
        />
        <GraficoLinha
          serie={serie('sincronizacao_clinica')}
          carregando={carregando}
          descricao="Sincronização de profissionais e pacientes com o clinica."
        />
      </div>
    </div>
  )
}

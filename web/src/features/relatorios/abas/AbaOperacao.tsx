import { GraficoArea, GraficoBarras, GraficoFunil, GraficoLinha } from '../graficos/Graficos'
import type { Relatorio, Serie } from '../tipos'

/**
 * A aba de Operação.
 *
 * Cada gráfico pega a série pela CHAVE que o serviço declarou, e não pela
 * posição: reordenar a lista na API não pode trocar o gráfico de lugar aqui, e
 * uma série que deixe de existir vira "sem dados no período" em vez de um
 * gráfico com o dado errado dentro.
 */
export function AbaOperacao({ relatorio, carregando }: { relatorio?: Relatorio; carregando: boolean }) {
  const serie = (key: string): Serie | undefined =>
    relatorio?.series.find((atual) => atual.key === key)

  return (
    <div className="space-y-4">
      <GraficoArea
        serie={serie('guias_por_status')}
        carregando={carregando}
        descricao="Pela data da transição, não pela data em que a guia foi criada."
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <GraficoLinha serie={serie('tempo_de_decisao')} carregando={carregando} formato="horas" />
        <GraficoFunil
          serie={serie('funil_de_guias')}
          carregando={carregando}
          descricao="Guias que passaram por cada etapa dentro do período."
        />
        <GraficoBarras serie={serie('solicitacoes_por_especialidade')} carregando={carregando} horizontal />
        <GraficoBarras serie={serie('guias_por_convenio')} carregando={carregando} horizontal />
      </div>

      <GraficoBarras
        serie={serie('sessoes_por_profissional')}
        carregando={carregando}
        horizontal
        altura="h-96"
      />
    </div>
  )
}

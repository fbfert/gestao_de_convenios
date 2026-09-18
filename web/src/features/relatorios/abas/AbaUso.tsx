import { GraficoBarras, GraficoLinha } from '../graficos/Graficos'
import type { Relatorio, Serie } from '../tipos'

/**
 * A aba de Uso do sistema.
 *
 * O gráfico por hora do dia é o que esta aba tem de próprio: ele desenha o
 * expediente real da clínica — onde estão os picos, e se alguém está
 * trabalhando fora de hora. É o único da tela em que zero é informação, e não
 * ausência: saber que ninguém usa o sistema às 3h faz parte da resposta.
 */
export function AbaUso({ relatorio, carregando }: { relatorio?: Relatorio; carregando: boolean }) {
  const serie = (key: string): Serie | undefined =>
    relatorio?.series.find((atual) => atual.key === key)

  return (
    <div className="space-y-4">
      <GraficoLinha
        serie={serie('acoes_por_dia')}
        carregando={carregando}
        descricao="Tudo o que foi registrado na trilha de auditoria — criação, alteração e acesso."
      />

      <GraficoBarras
        serie={serie('acoes_por_hora')}
        carregando={carregando}
        descricao="Hora sem movimento vale zero aqui, e não some: é o desenho do expediente."
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <GraficoBarras serie={serie('acoes_por_usuario')} carregando={carregando} horizontal altura="h-96" />
        <GraficoBarras serie={serie('acoes_por_entidade')} carregando={carregando} horizontal altura="h-96" />
      </div>

      <GraficoBarras serie={serie('importacoes_por_tipo')} carregando={carregando} horizontal />
    </div>
  )
}

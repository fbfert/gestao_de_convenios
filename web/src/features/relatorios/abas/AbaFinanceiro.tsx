import { GraficoBarras, GraficoLinha, GraficoPizza } from '../graficos/Graficos'
import type { Relatorio, Serie } from '../tipos'

/**
 * A aba Financeira.
 *
 * O gráfico de cima é o que a aba existe para mostrar: o que a clínica executou
 * contra o que a operadora pagou. A distância entre as duas linhas é a glosa, e
 * os gráficos de baixo explicam de onde ela vem.
 *
 * Todo valor em `moeda`: a API devolve centavos inteiros e o formato é quem
 * divide por cem. Passar `inteiro` aqui mostraria "250000" onde deveria haver
 * "R$ 2.500,00" — e é um erro que não quebra nada, só mente.
 */
export function AbaFinanceiro({ relatorio, carregando }: { relatorio?: Relatorio; carregando: boolean }) {
  const serie = (key: string): Serie | undefined =>
    relatorio?.series.find((atual) => atual.key === key)

  return (
    <div className="space-y-4">
      <GraficoLinha
        serie={serie('executado_x_pago')}
        carregando={carregando}
        formato="moeda"
        descricao="Executado pela data da sessão; pago pela data em que o analítico foi importado."
      />

      <div className="grid gap-4 lg:grid-cols-2">
        <GraficoBarras
          serie={serie('glosa_por_motivo')}
          carregando={carregando}
          formato="moeda"
          horizontal
          altura="h-96"
          descricao="Do maior para o menor — o topo da lista é onde a clínica mais perde."
        />
        <GraficoPizza
          serie={serie('situacao_das_conciliacoes')}
          carregando={carregando}
          descricao="Conciliações abertas no período."
        />
      </div>

      <GraficoBarras
        serie={serie('repasse_por_profissional')}
        carregando={carregando}
        formato="moeda"
        horizontal
        altura="h-96"
        descricao="Estimativa: executado × o percentual de repasse cadastrado em cada profissional."
      />
    </div>
  )
}

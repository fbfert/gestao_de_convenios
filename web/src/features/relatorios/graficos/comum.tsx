import type { LegendPayload, TooltipContentProps } from 'recharts'
import type { Paleta } from '../../../lib/graficos'
import { formatar, formatarDiaCurto } from '../formato'
import type { Formato, Serie } from '../tipos'

/**
 * O que os cinco gráficos compartilham: eixos com a pele da casa, tooltip e
 * legenda do design system, e o rótulo de cada ponto.
 *
 * Tooltip e legenda são componentes nossos, passados ao Recharts por `content`.
 * Os padrões da biblioteca trazem fundo branco, borda cinza e sombra próprios —
 * no tema de alto contraste isso apareceria como uma caixa de outro produto
 * flutuando sobre a tela, e no tema claro já destoa das outras superfícies.
 */

/** Tamanho de fonte dos eixos, em px. Recharts pinta SVG, e SVG não herda classe. */
export const FONTE_EIXO = 12

export type Ponto = Record<string, number | string>

/** Eixo X: datas viram `01/09`; categorias passam direto. */
export function rotuloDoX(valor: unknown): string {
  const texto = String(valor ?? '')

  return /^\d{4}-\d{2}-\d{2}$/.test(texto) ? formatarDiaCurto(texto) : texto
}

export function propsDoEixo(paleta: Paleta) {
  return {
    stroke: paleta.eixo,
    tick: { fill: paleta.eixo, fontSize: FONTE_EIXO },
    tickLine: false,
  }
}

/**
 * Os tipos vêm do próprio Recharts, e não de uma cópia local.
 *
 * E sem apertar os genéricos: `content` espera uma função que aceite QUALQUER
 * payload da biblioteca, então qualquer tipo mais estreito — inclusive um que
 * pareça equivalente — é recusado por variância, com um erro que fala de
 * genéricos e não do que está errado.
 */
type PropsDoTooltip = TooltipContentProps

/**
 * Tooltip com a pele da casa.
 *
 * Recebe o `formato` da série para o valor sair igual ao que o KPI e a tabela
 * mostram — dinheiro em centavos apareceria como "250000" sem isso.
 */
export function criarTooltip(formato: Formato = 'inteiro') {
  return function TooltipDoGrafico({ active, label, payload }: PropsDoTooltip) {
    if (!active || !payload?.length) {
      return null
    }

    return (
      <div className="rounded-campo border border-linha bg-superficie-elevada px-3 py-2 shadow-e2">
        <p className="text-meta font-medium text-texto">{rotuloDoX(label)}</p>
        <ul className="mt-1 space-y-0.5">
          {payload.map((item) => (
            <li key={String(item.dataKey ?? item.name)} className="flex items-center gap-2 text-meta text-texto-suave">
              <span
                aria-hidden="true"
                className="size-2 shrink-0 rounded-pilula"
                style={{ backgroundColor: item.color }}
              />
              <span>{String(item.name ?? '')}</span>
              <span className="ml-auto tabular-nums text-texto">{formatar(item.value, formato)}</span>
            </li>
          ))}
        </ul>
      </div>
    )
  }
}

type LegendaProps = { payload?: readonly LegendPayload[] }

export function LegendaDoGrafico({ payload }: LegendaProps) {
  if (!payload?.length) {
    return null
  }

  return (
    <ul className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 pt-2">
      {payload.map((item) => (
        <li key={item.value} className="flex items-center gap-1.5 text-meta text-texto-suave">
          <span
            aria-hidden="true"
            className="size-2.5 shrink-0 rounded-pilula"
            style={{ backgroundColor: item.color }}
          />
          {item.value}
        </li>
      ))}
    </ul>
  )
}

/** Série sem ponto algum — a moldura mostra "sem dados no período". */
export function estaVazia(serie: Serie | undefined): boolean {
  return !serie || serie.pontos.length === 0
}

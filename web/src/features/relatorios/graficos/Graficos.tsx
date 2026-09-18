import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Funnel,
  FunnelChart,
  LabelList,
  Legend,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { corDaSerie, usePaleta } from '../../../lib/graficos'
import { formatar } from '../formato'
import type { Formato, Serie } from '../tipos'
import { criarTooltip, LegendaDoGrafico, propsDoEixo, rotuloDoX } from './comum'
import { MolduraGrafico } from './MolduraGrafico'

/**
 * Os cinco tipos de gráfico do contrato, um componente cada.
 *
 * Todos recebem a `Serie` inteira — chave, rótulo, pontos e campos — em vez de
 * props soltas. É o que faz a aba virar configuração: a tela escolhe o
 * componente pelo `tipo` da série e não precisa saber o que tem dentro.
 *
 * Nenhuma cor literal: tudo vem de `usePaleta()`, que lê os tokens do design
 * system e re-lê quando o tema muda.
 */

type Props = {
  serie: Serie | undefined
  carregando?: boolean
  /** Como formatar os valores no tooltip e no eixo Y. */
  formato?: Formato
  descricao?: string
  altura?: string
}

const vazia = (serie: Serie | undefined) => !serie || serie.pontos.length === 0

export function GraficoLinha({ serie, carregando, formato = 'inteiro', descricao, altura }: Props) {
  const paleta = usePaleta()
  const eixo = propsDoEixo(paleta)

  return (
    <MolduraGrafico
      titulo={serie?.label ?? ''}
      descricao={descricao}
      carregando={carregando}
      vazio={vazia(serie)}
      altura={altura}
      testId={`grafico-${serie?.key}`}
    >
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={serie?.pontos} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
          <CartesianGrid stroke={paleta.grade} strokeDasharray="3 3" vertical={false} />
          <XAxis dataKey="x" tickFormatter={rotuloDoX} {...eixo} />
          <YAxis {...eixo} width={56} tickFormatter={(v) => formatar(v, formato)} />
          <Tooltip content={criarTooltip(formato)} />
          <Legend content={LegendaDoGrafico} />
          {serie?.campos.map((campo, i) => (
            <Line
              key={campo.key}
              type="monotone"
              dataKey={campo.key}
              name={campo.label}
              stroke={corDaSerie(paleta, i)}
              strokeWidth={2}
              dot={false}
              activeDot={{ r: 4 }}
            />
          ))}
        </LineChart>
      </ResponsiveContainer>
    </MolduraGrafico>
  )
}

export function GraficoArea({ serie, carregando, formato = 'inteiro', descricao, altura }: Props) {
  const paleta = usePaleta()
  const eixo = propsDoEixo(paleta)

  return (
    <MolduraGrafico
      titulo={serie?.label ?? ''}
      descricao={descricao}
      carregando={carregando}
      vazio={vazia(serie)}
      altura={altura}
      testId={`grafico-${serie?.key}`}
    >
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={serie?.pontos} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
          <CartesianGrid stroke={paleta.grade} strokeDasharray="3 3" vertical={false} />
          <XAxis dataKey="x" tickFormatter={rotuloDoX} {...eixo} />
          <YAxis {...eixo} width={56} tickFormatter={(v) => formatar(v, formato)} />
          <Tooltip content={criarTooltip(formato)} />
          <Legend content={LegendaDoGrafico} />
          {serie?.campos.map((campo, i) => (
            <Area
              key={campo.key}
              type="monotone"
              dataKey={campo.key}
              name={campo.label}
              // Empilhada: a leitura é "quantas guias no total, e como se
              // repartem". Séries soltas se sobrepõem e escondem umas às outras.
              stackId="total"
              stroke={corDaSerie(paleta, i)}
              fill={corDaSerie(paleta, i)}
              fillOpacity={0.25}
              strokeWidth={2}
            />
          ))}
        </AreaChart>
      </ResponsiveContainer>
    </MolduraGrafico>
  )
}

export function GraficoBarras({
  serie,
  carregando,
  formato = 'inteiro',
  descricao,
  altura,
  horizontal = false,
}: Props & { horizontal?: boolean }) {
  const paleta = usePaleta()
  const eixo = propsDoEixo(paleta)

  return (
    <MolduraGrafico
      titulo={serie?.label ?? ''}
      descricao={descricao}
      carregando={carregando}
      vazio={vazia(serie)}
      altura={altura}
      testId={`grafico-${serie?.key}`}
    >
      <ResponsiveContainer width="100%" height="100%">
        <BarChart
          data={serie?.pontos}
          layout={horizontal ? 'vertical' : 'horizontal'}
          margin={{ top: 8, right: 8, bottom: 0, left: horizontal ? 8 : 0 }}
        >
          <CartesianGrid stroke={paleta.grade} strokeDasharray="3 3" vertical={horizontal} horizontal={!horizontal} />
          {horizontal ? (
            <>
              <XAxis type="number" {...eixo} tickFormatter={(v) => formatar(v, formato)} />
              {/* Largura generosa: nome de profissional e motivo de glosa são
                  longos, e cortar o rótulo apaga justamente o que identifica a barra. */}
              <YAxis type="category" dataKey="x" {...eixo} width={160} />
            </>
          ) : (
            <>
              <XAxis dataKey="x" tickFormatter={rotuloDoX} {...eixo} interval="preserveStartEnd" />
              <YAxis {...eixo} width={56} tickFormatter={(v) => formatar(v, formato)} />
            </>
          )}
          <Tooltip content={criarTooltip(formato)} cursor={{ fill: paleta.grade, fillOpacity: 0.3 }} />
          <Legend content={LegendaDoGrafico} />
          {serie?.campos.map((campo, i) => (
            <Bar
              key={campo.key}
              dataKey={campo.key}
              name={campo.label}
              fill={corDaSerie(paleta, i)}
              radius={horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]}
            />
          ))}
        </BarChart>
      </ResponsiveContainer>
    </MolduraGrafico>
  )
}

export function GraficoFunil({ serie, carregando, descricao, altura }: Props) {
  const paleta = usePaleta()
  const campo = serie?.campos[0]?.key ?? 'total'

  return (
    <MolduraGrafico
      titulo={serie?.label ?? ''}
      descricao={descricao}
      carregando={carregando}
      vazio={vazia(serie)}
      altura={altura}
      testId={`grafico-${serie?.key}`}
    >
      <ResponsiveContainer width="100%" height="100%">
        <FunnelChart>
          <Tooltip content={criarTooltip('inteiro')} />
          <Funnel dataKey={campo} data={serie?.pontos} isAnimationActive={false} nameKey="x">
            {serie?.pontos.map((ponto, i) => (
              <Cell key={String(ponto.x)} fill={corDaSerie(paleta, i)} />
            ))}
            <LabelList position="right" dataKey="x" fill={paleta.eixo} stroke="none" />
          </Funnel>
        </FunnelChart>
      </ResponsiveContainer>
    </MolduraGrafico>
  )
}

export function GraficoPizza({ serie, carregando, descricao, altura }: Props) {
  const paleta = usePaleta()
  const campo = serie?.campos[0]?.key ?? 'total'

  return (
    <MolduraGrafico
      titulo={serie?.label ?? ''}
      descricao={descricao}
      carregando={carregando}
      vazio={vazia(serie)}
      altura={altura}
      testId={`grafico-${serie?.key}`}
    >
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Tooltip content={criarTooltip('inteiro')} />
          <Legend content={LegendaDoGrafico} />
          <Pie
            data={serie?.pontos}
            dataKey={campo}
            nameKey="x"
            // Rosca em vez de pizza cheia: o furo no meio dá referência de
            // tamanho e evita a leitura de ângulo, que é onde a pizza erra.
            innerRadius="45%"
            outerRadius="75%"
            paddingAngle={2}
            isAnimationActive={false}
          >
            {serie?.pontos.map((ponto, i) => (
              <Cell key={String(ponto.x)} fill={corDaSerie(paleta, i)} />
            ))}
          </Pie>
        </PieChart>
      </ResponsiveContainer>
    </MolduraGrafico>
  )
}

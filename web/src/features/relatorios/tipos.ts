/**
 * O contrato de `GET /api/relatorios/{aba}`, igual para as quatro abas.
 *
 * Um formato só é o que permite a tela ter um componente por TIPO de
 * visualização em vez de um por aba: a aba vira configuração, e uma aba nova
 * não pede front novo.
 */

export const ABAS = ['operacao', 'financeiro', 'automacoes', 'uso'] as const

export type Aba = (typeof ABAS)[number]

export const ROTULO_DA_ABA: Record<Aba, string> = {
  operacao: 'Operação',
  financeiro: 'Financeiro',
  automacoes: 'Automações',
  uso: 'Uso do sistema',
}

/** Uma permissão por aba — a API cobra a mesma no middleware da rota. */
export const PERMISSAO_DA_ABA: Record<Aba, string> = {
  operacao: 'relatorios.operacao',
  financeiro: 'relatorios.financeiro',
  automacoes: 'relatorios.automacoes',
  uso: 'relatorios.uso',
}

export type Formato = 'inteiro' | 'percentual' | 'moeda' | 'horas' | 'data_hora' | 'texto'

/** Para onde o indicador deve ir — quem decide a cor da variação. */
export type Sentido = 'maior_melhor' | 'menor_melhor' | 'neutro'

export type Kpi = {
  key: string
  label: string
  /** `null` é ausência de base de cálculo, e nunca zero. */
  valor: number | null
  /** `null` quando a comparação não foi pedida, ou quando o KPI é um retrato do agora. */
  anterior: number | null
  formato: Formato
  sentido: Sentido
  hint: string | null
}

export type TipoDeSerie = 'linha' | 'area' | 'barras' | 'funil' | 'pizza'

export type PontoDeSerie = { x: string } & Record<string, number | string>

export type Serie = {
  key: string
  label: string
  tipo: TipoDeSerie
  /** Vazio significa "não houve nada no período" — a tela diz isso em palavras. */
  pontos: PontoDeSerie[]
  campos: { key: string; label: string }[]
}

export type ColunaDeTabela = {
  key: string
  label: string
  formato: Formato
}

export type LinhaDeTabela = Record<string, unknown>

export type TabelaDeRelatorio = {
  key: string
  label: string
  colunas: ColunaDeTabela[]
  linhas: LinhaDeTabela[]
}

export type Relatorio = {
  periodo: { de: string; ate: string; granularidade: 'dia' | 'semana' | 'mes'; dias: number }
  comparacao: { de: string; ate: string } | null
  filtros_aplicados: Record<string, number>
  kpis: Kpi[]
  series: Serie[]
  tabelas: TabelaDeRelatorio[]
  gerado_em: string
  /** Veio do reaproveitamento de cinco minutos — explica por que o número não mudou. */
  cache: boolean
}

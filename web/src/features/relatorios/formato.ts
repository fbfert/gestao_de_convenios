import type { Formato } from './tipos'

/**
 * Como cada número do relatório aparece na tela.
 *
 * A API devolve o número cru — dinheiro em centavos inteiros, percentual como
 * número, hora como decimal — e a formatação acontece só aqui. É o que faz o
 * mesmo valor sair igual no KPI, na tabela e no eixo do gráfico.
 */

/** Ausência de dado. Nunca "0", que afirmaria que a medição aconteceu e deu zero. */
export const AUSENTE = '—'

const inteiro = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 })
const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
const umaCasa = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })

export function formatar(valor: unknown, formato: Formato): string {
  if (valor === null || valor === undefined || valor === '') {
    return AUSENTE
  }

  switch (formato) {
    case 'inteiro':
      return inteiro.format(Number(valor))
    // Centavos inteiros na API; a divisão acontece uma vez, aqui.
    case 'moeda':
      return moeda.format(Number(valor) / 100)
    case 'percentual':
      return `${umaCasa.format(Number(valor))}%`
    case 'horas':
      return `${umaCasa.format(Number(valor))} h`
    case 'duracao':
      return formatarDuracao(Number(valor))
    case 'data_hora':
      return formatarDataHora(String(valor))
    default:
      return String(valor)
  }
}

/**
 * Segundos na unidade que a pessoa usa para falar daquele tempo.
 *
 * A execução típica da automação leva segundos, e "0,1 h" não diz nada nem
 * permite comparar duas linhas da tabela. Os cortes são onde a leitura vira
 * desconfortável: acima de 90 segundos ninguém conta em segundos, e acima de 90
 * minutos ninguém conta em minutos.
 */
export function formatarDuracao(segundos: number): string {
  if (segundos < 90) {
    return `${inteiro.format(Math.round(segundos))} s`
  }

  const minutos = segundos / 60

  return minutos < 90 ? `${umaCasa.format(minutos)} min` : `${umaCasa.format(minutos / 60)} h`
}

function formatarDataHora(valor: string): string {
  const data = new Date(valor)

  if (Number.isNaN(data.getTime())) {
    return valor
  }

  return data.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/** Data ISO (`2026-09-01`) como a pessoa lê. */
export function formatarDia(iso: string): string {
  const [ano, mes, dia] = iso.split('-')

  return dia ? `${dia}/${mes}/${ano}` : iso
}

/** Rótulo curto para o eixo do gráfico: `01/09`. */
export function formatarDiaCurto(iso: string): string {
  const [, mes, dia] = iso.split('-')

  return dia ? `${dia}/${mes}` : iso
}

/**
 * A variação percentual entre o período e o anterior.
 *
 * `null` quando não dá para dividir: sem valor anterior, ou com anterior zero.
 * "Subiu de 0 para 5" não é +∞ nem +100% — é uma frase, não um percentual, e a
 * tela mostra os dois números em vez de inventar um terceiro.
 */
export function variacao(valor: number | null, anterior: number | null): number | null {
  if (valor === null || anterior === null || anterior === 0) {
    return null
  }

  return ((valor - anterior) / Math.abs(anterior)) * 100
}

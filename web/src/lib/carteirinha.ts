/**
 * Carteirinha por blocos.
 *
 * O formato é dado do convênio (`convenios.carteirinha_blocos`), não uma regra
 * de código: `[4, 4, 6, 2, 1]` é a Unimed, 17 dígitos. Convênio sem formato
 * declarado usa texto livre, e nada aqui se aplica.
 *
 * O banco guarda sempre os dígitos corridos; os blocos existem só para
 * digitação e leitura.
 */

/** Preset da Unimed, oferecido como atalho na tela de Convênios. */
export const UNIMED_BLOCK_SIZES = [4, 4, 6, 2, 1] as const

export function tamanhoCarteirinha(blocos: readonly number[]): number {
  return blocos.reduce((total, size) => total + size, 0)
}

/** Fatia um valor já salvo em blocos, para popular o formulário na edição. */
export function splitCarteirinha(value: string, blocos: readonly number[]): string[] {
  const digits = (value ?? '').replace(/\D/g, '')
  let offset = 0

  return blocos.map((size) => {
    const block = digits.slice(offset, offset + size)
    offset += size
    return block
  })
}

export function joinCarteirinha(blocks: readonly string[]): string {
  return blocks.join('')
}

export function isCarteirinhaCompleta(
  blocks: readonly string[],
  blocos: readonly number[],
): boolean {
  return blocos.every((size, index) => (blocks[index] ?? '').length === size)
}

/**
 * Formata para leitura ("0123 4567 890123 45 6").
 *
 * Sem `blocos` — listas onde o convênio do paciente não vem carregado — cai no
 * preset da Unimed, que é o comportamento que a tela já tinha. Valores cujo
 * total de dígitos não bate voltam intactos, o que preserva as carteirinhas de
 * outros convênios e os cadastros legados.
 */
export function formatCarteirinha(
  value: string | null | undefined,
  blocos: readonly number[] = UNIMED_BLOCK_SIZES,
): string {
  if (!value) {
    return ''
  }

  const digits = value.replace(/\D/g, '')

  if (digits.length !== tamanhoCarteirinha(blocos) || digits.length !== value.trim().length) {
    return value
  }

  let offset = 0

  return blocos
    .map((size) => {
      const block = digits.slice(offset, offset + size)
      offset += size
      return block
    })
    .join(' ')
}

/** Ex.: "4-4-6-2-1" → [4,4,6,2,1]. Entrada inválida vira lista vazia. */
export function parseBlocos(texto: string): number[] {
  return texto
    .split(/[^0-9]+/)
    .filter((parte) => parte !== '')
    .map((parte) => Number.parseInt(parte, 10))
    .filter((n) => Number.isFinite(n) && n > 0)
}

export function formatBlocos(blocos: readonly number[] | null | undefined): string {
  return (blocos ?? []).join('-')
}

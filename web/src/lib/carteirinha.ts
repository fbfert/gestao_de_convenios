/**
 * Carteirinha da Unimed: 17 dígitos divididos em blocos de 4 + 4 + 6 + 2 + 1.
 * Guardamos sempre os 17 dígitos corridos; os blocos existem só para digitação e leitura.
 */
export const UNIMED_BLOCK_SIZES = [4, 4, 6, 2, 1] as const

export const UNIMED_CARTEIRINHA_LENGTH = UNIMED_BLOCK_SIZES.reduce((total, size) => total + size, 0)

/** Fatia um valor já salvo em blocos, para popular o formulário na edição. */
export function splitUnimedCarteirinha(value: string): string[] {
  const digits = value.replace(/\D/g, '')
  let offset = 0

  return UNIMED_BLOCK_SIZES.map((size) => {
    const block = digits.slice(offset, offset + size)
    offset += size
    return block
  })
}

export function joinUnimedCarteirinha(blocks: readonly string[]): string {
  return blocks.join('')
}

export function isUnimedCarteirinhaCompleta(blocks: readonly string[]): boolean {
  return UNIMED_BLOCK_SIZES.every((size, index) => (blocks[index] ?? '').length === size)
}

/**
 * Formata para leitura ("0123 4567 890123 45 6"). Valores que não têm os 17 dígitos
 * — outros convênios, cadastros legados — voltam intactos.
 */
export function formatUnimedCarteirinha(value: string | null | undefined): string {
  if (!value) {
    return ''
  }

  const digits = value.replace(/\D/g, '')
  if (digits.length !== UNIMED_CARTEIRINHA_LENGTH || digits.length !== value.trim().length) {
    return value
  }

  let offset = 0

  return UNIMED_BLOCK_SIZES.map((size) => {
    const block = digits.slice(offset, offset + size)
    offset += size
    return block
  }).join(' ')
}

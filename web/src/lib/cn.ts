import { clsx, type ClassValue } from 'clsx'
import { extendTailwindMerge } from 'tailwind-merge'

/**
 * Papéis tipográficos do design system (xiax-agenda §5).
 *
 * Precisa ser declarado aqui, e não só no `@theme`, porque o tailwind-merge
 * classifica `text-*` por uma lista interna: ele conhece a escala do próprio
 * framework e sabe que `text-white` é cor, mas `text-corpo` e `text-sobre-acento`
 * são ambos
 * nomes que ele nunca viu — e, sem esta lista, os dois caem no mesmo grupo de
 * COR. O último da composição vence e o outro é descartado silenciosamente.
 *
 * No xiax-agenda isso chegou a produção: o botão primário compõe
 * `text-sobre-acento` (cor) com `text-corpo` (tamanho), perdia a cor e pintava
 * o rótulo em `--texto` sobre o verde do acento — 2,52:1 medido, contra o piso
 * de 4,5:1. O contrato de contraste não viu nada, porque ele confere os tokens,
 * não o que a tela pinta. Ver §11.4 do documento do DS.
 */
const PAPEIS_DE_TAMANHO = [
  'display',
  'titulo',
  'subtitulo',
  'corpo-lg',
  'corpo',
  'rotulo',
  'meta',
] as const

const twMerge = extendTailwindMerge({
  extend: {
    classGroups: {
      'font-size': [{ text: [...PAPEIS_DE_TAMANHO] }],
    },
  },
})

/** Compõe classes condicionais e resolve conflitos de utilitários Tailwind. */
export function cn(...entradas: ClassValue[]): string {
  return twMerge(clsx(entradas))
}

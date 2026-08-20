import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

/**
 * Compõe classes condicionais e resolve conflitos de utilitários Tailwind.
 * Mesma receita do clinica.gestaonossa.com.br (src/lib/cn.ts) — sem a
 * extensão de papéis tipográficos de lá, porque aqui não adotamos a escala
 * text-display/titulo/corpo/... (fora de escopo da migração de tema).
 */
export function cn(...entradas: ClassValue[]): string {
  return twMerge(clsx(entradas))
}

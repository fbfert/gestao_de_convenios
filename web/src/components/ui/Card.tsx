import { cva, type VariantProps } from 'class-variance-authority'
import type { HTMLAttributes } from 'react'

import { cn } from '../../lib/cn'

const card = cva('rounded-janela border border-linha bg-superficie-elevada shadow-e2', {
  variants: {
    preenchimento: {
      nenhum: '',
      padrao: 'p-6',
    },
  },
  defaultVariants: { preenchimento: 'padrao' },
})

export interface CardProps extends HTMLAttributes<HTMLDivElement>, VariantProps<typeof card> {}

/**
 * Painel/cartão do design system — não existe componente nomeado equivalente
 * no clinica nem no gescon hoje, mas o padrão `rounded-[1.75rem] border
 * border-white/10 bg-slate-950/60 p-6` se repete em quase toda tela do
 * gescon; isso vira um único lugar. Portado em 20/08/2026.
 */
export function Card({ preenchimento, className, ...props }: CardProps) {
  return <div className={cn(card({ preenchimento }), className)} {...props} />
}

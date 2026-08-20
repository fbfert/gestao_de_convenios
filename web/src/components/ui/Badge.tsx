import { cva, type VariantProps } from 'class-variance-authority'
import type { HTMLAttributes } from 'react'

import { cn } from '../../lib/cn'

const badge = cva(
  'inline-flex items-center gap-1 rounded-pilula px-2.5 py-1 text-xs font-semibold',
  {
    variants: {
      tone: {
        acento: 'bg-acento-suave text-acento-intenso',
        sucesso: 'bg-sucesso-suave text-sucesso-texto',
        alerta: 'bg-alerta-suave text-alerta-texto',
        perigo: 'bg-perigo-suave text-perigo-texto',
        neutro: 'bg-neutro-desativado text-texto-suave',
      },
    },
    defaultVariants: { tone: 'neutro' },
  },
)

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement>, VariantProps<typeof badge> {}

/**
 * Badge de status por tom fixo — versão simplificada do ChipStatus do clinica
 * (que deriva cor a partir de cadastro por tenant; o gescon não tem essa
 * customização hoje, então aqui é só variante cva). Pensado pra substituir as
 * funções `statusTone()` reimplementadas em cada feature (guias, solicitações,
 * lançamentos...).
 */
export function Badge({ tone, className, ...props }: BadgeProps) {
  return <span className={cn(badge({ tone }), className)} {...props} />
}

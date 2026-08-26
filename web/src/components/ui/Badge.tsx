import { cva, type VariantProps } from 'class-variance-authority'
import type { HTMLAttributes } from 'react'

import { cn } from '../../lib/cn'

const badge = cva(
  // `whitespace-nowrap`: em coluna estreita de tabela o chip herda largura
  // de min-content e o texto quebra a cada espaco — a pilula arredondada
  // vira um blob de varias linhas e infla a altura da linha inteira.
  'inline-flex items-center gap-1 whitespace-nowrap rounded-pilula px-2.5 py-1 text-meta font-semibold',
  {
    variants: {
      tone: {
        acento: 'bg-acento-suave text-acento-intenso',
        info: 'bg-info-suave text-info-texto',
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

import { cva, type VariantProps } from 'class-variance-authority'
import { LoaderCircle } from 'lucide-react'
import { Slot } from 'radix-ui'
import type { ButtonHTMLAttributes, Ref } from 'react'

import { cn } from '../../lib/cn'

const botao = cva(
  [
    'inline-flex items-center justify-center gap-2 select-none whitespace-nowrap font-medium',
    'rounded-controle transition-colors duration-(--duracao-1)',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco',
    'disabled:bg-neutro-desativado disabled:text-texto-desativado disabled:cursor-not-allowed disabled:pointer-events-none',
  ],
  {
    variants: {
      variante: {
        primario: 'bg-acento text-sobre-acento hover:bg-acento-intenso',
        secundario: 'border border-borda-campo bg-superficie text-texto hover:bg-fundo',
        perigo: 'bg-perigo text-sobre-perigo hover:bg-perigo-intenso',
        fantasma: 'text-texto hover:bg-acento-suave',
      },
      tamanho: {
        sm: 'h-8 px-3 text-meta',
        md: 'h-10 px-4 text-corpo',
        lg: 'h-11 px-5 text-corpo',
      },
    },
    defaultVariants: { variante: 'primario', tamanho: 'md' },
  },
)

export interface BotaoProps
  extends ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof botao> {
  /** Spinner + aria-busy + disabled; largura preservada (o rótulo não some) */
  carregando?: boolean
  /** Radix Slot — vira <Link> mantendo a skin (carregando não se aplica) */
  asChild?: boolean
  ref?: Ref<HTMLButtonElement>
}

/**
 * Botão do design system — mesma anatomia do Botao do clinica
 * (componentes/ui/botao.tsx lá), portado pro gescon em 20/08/2026.
 */
export function Botao({
  variante,
  tamanho,
  carregando = false,
  asChild = false,
  className,
  children,
  type = 'button',
  disabled,
  ...props
}: BotaoProps) {
  const classes = cn(botao({ variante, tamanho }), className)

  if (asChild) {
    return (
      <Slot.Root className={classes} {...props}>
        {children}
      </Slot.Root>
    )
  }

  return (
    <button
      type={type}
      className={classes}
      disabled={disabled === true || carregando}
      aria-busy={carregando || undefined}
      {...props}
    >
      {carregando ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : null}
      {children}
    </button>
  )
}

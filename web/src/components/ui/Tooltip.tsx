import { useId, type ReactNode } from 'react'

/**
 * Dica de ajuda ao lado do rotulo de um campo.
 *
 * Abre no hover e tambem no foco do teclado: so no hover, a explicacao ficaria
 * inacessivel para quem navega por tab e para telas de toque. O painel fica
 * dentro do mesmo elemento `group` do botao, entao continua aberto enquanto o
 * mouse passa por cima do texto — necessario para dicas de mais de uma linha.
 */
export function Tooltip({
  children,
  rotulo = 'O que significa este campo?',
}: {
  children: ReactNode
  rotulo?: string
}) {
  const id = useId()

  return (
    <span className="group relative inline-flex align-middle">
      <button
        type="button"
        aria-label={rotulo}
        aria-describedby={id}
        className="flex h-5 w-5 items-center justify-center rounded-full border border-white/15 bg-white/5 text-slate-300 transition hover:border-cyan-300/50 hover:bg-cyan-400/10 hover:text-cyan-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/40"
      >
        <svg aria-hidden="true" viewBox="0 0 16 16" className="h-3 w-3">
          <circle
            cx="7"
            cy="7"
            r="4.25"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.6"
          />
          <path
            d="M10.2 10.2 14 14"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.6"
            strokeLinecap="round"
          />
        </svg>
      </button>

      <span
        id={id}
        role="tooltip"
        className="invisible absolute left-0 top-full z-30 mt-2 w-72 max-w-[calc(100vw-3rem)] rounded-xl border border-white/10 bg-slate-900 p-3 text-left text-xs font-normal leading-5 text-slate-200 opacity-0 shadow-xl shadow-slate-950/50 transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100"
      >
        {children}
      </span>
    </span>
  )
}

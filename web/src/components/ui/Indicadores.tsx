import type { ReactNode } from 'react'

export type Indicador = {
  rotulo: string
  valor: ReactNode
  /** Destaque discreto, para quando o número exige atenção. */
  tom?: 'padrao' | 'alerta'
}

/**
 * Linha compacta de contadores — Total, Ativos, Inativos e afins.
 *
 * Substitui os cartões que empilhavam rótulo e número: três deles ocupavam uma
 * faixa inteira da tela para mostrar três dígitos, empurrando a listagem, que é
 * o que a pessoa veio ver, para baixo da dobra.
 */
export function Indicadores({ itens }: { itens: Indicador[] }) {
  return (
    <div className="flex flex-wrap items-center gap-2" data-testid="indicadores">
      {itens.map((item) => (
        <span
          key={item.rotulo}
          className={[
            'inline-flex items-baseline gap-2 rounded-full border px-3 py-1.5',
            item.tom === 'alerta'
              ? 'border-amber-300/30 bg-amber-400/10'
              : 'border-white/10 bg-slate-950/40',
          ].join(' ')}
        >
          <span className="text-[0.65rem] uppercase tracking-[0.2em] text-slate-400">
            {item.rotulo}
          </span>
          <span
            className={[
              'text-sm font-semibold tabular-nums',
              item.tom === 'alerta' ? 'text-amber-100' : 'text-white',
            ].join(' ')}
          >
            {item.valor}
          </span>
        </span>
      ))}
    </div>
  )
}

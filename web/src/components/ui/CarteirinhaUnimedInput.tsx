import { UNIMED_BLOCK_SIZES, joinUnimedCarteirinha } from '../../lib/carteirinha'

type CarteirinhaUnimedInputProps = {
  /** Um bloco por posição de UNIMED_BLOCK_SIZES. Estado do chamador: derivar da string
   *  concatenada faria os dígitos migrarem de bloco quando um bloco anterior esvazia. */
  blocks: string[]
  onChange: (blocks: string[], carteirinha: string) => void
  disabled?: boolean
  testIdPrefix?: string
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function CarteirinhaUnimedInput({
  blocks,
  onChange,
  disabled = false,
  testIdPrefix = 'carteirinha-unimed',
}: CarteirinhaUnimedInputProps) {
  return (
    <div className="space-y-2">
      <div className="grid grid-cols-[1fr_1fr_1.5fr_.8fr_.7fr] gap-2">
        {UNIMED_BLOCK_SIZES.map((size, index) => (
          <input
            key={index}
            value={blocks[index] ?? ''}
            onChange={(event) => {
              const next = [...blocks]
              next[index] = event.target.value.replace(/\D/g, '').slice(0, size)
              onChange(next, joinUnimedCarteirinha(next))
            }}
            inputMode="numeric"
            maxLength={size}
            disabled={disabled}
            className={fieldClasses()}
            data-testid={`${testIdPrefix}-${index + 1}`}
          />
        ))}
      </div>
      <p className="text-xs text-slate-400">17 dígitos no padrão 4 · 4 · 6 · 2 · 1.</p>
    </div>
  )
}

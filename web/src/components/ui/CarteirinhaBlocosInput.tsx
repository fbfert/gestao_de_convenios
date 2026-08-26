import { joinCarteirinha, tamanhoCarteirinha } from '../../lib/carteirinha'

type CarteirinhaBlocosInputProps = {
  /** Tamanhos dos blocos, vindos de `convenios.carteirinha_blocos`. */
  blocos: readonly number[]
  /** Um valor por bloco. Estado do chamador: derivar da string concatenada
   *  faria os dígitos migrarem de bloco quando um bloco anterior esvazia. */
  blocks: string[]
  onChange: (blocks: string[], carteirinha: string) => void
  disabled?: boolean
  testIdPrefix?: string
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

export function CarteirinhaBlocosInput({
  blocos,
  blocks,
  onChange,
  disabled = false,
  testIdPrefix = 'carteirinha-blocos',
}: CarteirinhaBlocosInputProps) {
  return (
    <div className="space-y-2">
      {/*
        As colunas acompanham o tamanho de cada bloco, então um bloco de 6
        dígitos fica mais largo que um de 1. `minmax(0,Nfr)` evita que um bloco
        grande estoure a linha em telas estreitas.
      */}
      <div
        className="grid gap-2"
        style={{
          gridTemplateColumns: blocos.map((size) => `minmax(0, ${size}fr)`).join(' '),
        }}
      >
        {blocos.map((size, index) => (
          <input
            key={index}
            value={blocks[index] ?? ''}
            onChange={(event) => {
              const next = [...blocks]
              next[index] = event.target.value.replace(/\D/g, '').slice(0, size)
              onChange(next, joinCarteirinha(next))
            }}
            // Sem isto, um bloco já preenchido (ex.: editar um paciente
            // existente) bloqueia a digitação: com maxLength no limite, o
            // navegador recusa o caractere novo até o texto atual ser
            // selecionado. Mais crítico no bloco de 1 dígito (dígito
            // verificador) — é onde menos sobra espaço pra digitar por cima.
            onFocus={(event) => event.target.select()}
            inputMode="numeric"
            maxLength={size}
            disabled={disabled}
            className={fieldClasses()}
            data-testid={`${testIdPrefix}-${index + 1}`}
          />
        ))}
      </div>
      <p className="text-meta text-slate-400">
        {tamanhoCarteirinha(blocos)} dígitos no padrão {blocos.join(' · ')}.
      </p>
    </div>
  )
}

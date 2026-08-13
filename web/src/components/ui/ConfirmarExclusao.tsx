import { useEffect, useRef, useState, type FormEvent } from 'react'

const PALAVRA = 'EXCLUIR'

/**
 * Confirmação em duas etapas para exclusão que não tem volta.
 *
 * A primeira barreira é o próprio diálogo; a segunda é digitar a palavra. O
 * `window.confirm` sozinho vira reflexo: quem clica dez vezes por dia aperta
 * "OK" sem ler. Digitar exige parar e olhar o que está sendo apagado.
 */
export function ConfirmarExclusao({
  titulo,
  descricao,
  alvo,
  confirmando = false,
  onConfirmar,
  onCancelar,
}: {
  titulo: string
  /** O que acontece de fato, em uma frase. */
  descricao: string
  /** Nome do que será apagado, mostrado em destaque. */
  alvo: string
  confirmando?: boolean
  onConfirmar: () => void
  onCancelar: () => void
}) {
  const [texto, setTexto] = useState('')
  const inputRef = useRef<HTMLInputElement | null>(null)

  useEffect(() => {
    inputRef.current?.focus()

    const aoTeclar = (evento: KeyboardEvent) => {
      if (evento.key === 'Escape') {
        onCancelar()
      }
    }

    document.addEventListener('keydown', aoTeclar)

    return () => document.removeEventListener('keydown', aoTeclar)
  }, [onCancelar])

  const liberado = texto.trim().toUpperCase() === PALAVRA

  const enviar = (evento: FormEvent) => {
    evento.preventDefault()

    if (liberado && !confirmando) {
      onConfirmar()
    }
  }

  return (
    <div
      className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-label={titulo}
      data-testid="confirmar-exclusao"
    >
      <form
        onSubmit={enviar}
        className="w-full max-w-lg space-y-4 rounded-[1.75rem] border border-rose-400/20 bg-slate-950 p-6 shadow-2xl shadow-slate-950/60"
      >
        <div>
          <h3 className="text-lg font-semibold text-white">{titulo}</h3>
          <p className="mt-2 text-sm leading-6 text-slate-300">{descricao}</p>
        </div>

        <p className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white">
          {alvo}
        </p>

        <label className="block space-y-2">
          <span className="text-sm text-slate-200">
            Para confirmar, digite <strong className="text-rose-200">{PALAVRA}</strong>
          </span>
          <input
            ref={inputRef}
            value={texto}
            onChange={(evento) => setTexto(evento.target.value)}
            autoComplete="off"
            spellCheck={false}
            className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-rose-300/70 focus:ring-2 focus:ring-rose-300/20"
            data-testid="confirmar-exclusao-texto"
          />
        </label>

        <div className="flex flex-wrap justify-end gap-3">
          <button
            type="button"
            onClick={onCancelar}
            className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
            data-testid="confirmar-exclusao-cancelar"
          >
            Cancelar
          </button>

          <button
            type="submit"
            disabled={!liberado || confirmando}
            className="rounded-2xl border border-rose-400/30 bg-rose-500/20 px-4 py-3 text-sm font-semibold text-rose-50 transition hover:bg-rose-500/30 disabled:opacity-40"
            data-testid="confirmar-exclusao-confirmar"
          >
            {confirmando ? 'Excluindo...' : 'Excluir definitivamente'}
          </button>
        </div>
      </form>
    </div>
  )
}

import { useEffect, useRef, useState, type FormEvent } from 'react'

import { Botao } from './Botao'

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
      className="fixed inset-0 z-(--z-dialogo) flex items-center justify-center bg-texto/40 p-4"
      role="dialog"
      aria-modal="true"
      aria-label={titulo}
      data-testid="confirmar-exclusao"
    >
      <form
        onSubmit={enviar}
        className="w-full max-w-lg space-y-4 rounded-janela border border-perigo/30 bg-superficie-elevada p-6 shadow-e3"
      >
        <div>
          <h3 className="text-lg font-semibold text-texto">{titulo}</h3>
          <p className="mt-2 text-sm leading-6 text-texto-suave">{descricao}</p>
        </div>

        <p className="rounded-campo border border-linha bg-fundo px-4 py-3 text-sm text-texto">
          {alvo}
        </p>

        <label className="block space-y-2">
          <span className="text-sm text-texto">
            Para confirmar, digite <strong className="text-perigo-texto">{PALAVRA}</strong>
          </span>
          <input
            ref={inputRef}
            value={texto}
            onChange={(evento) => setTexto(evento.target.value)}
            autoComplete="off"
            spellCheck={false}
            className="w-full rounded-campo border border-borda-campo bg-superficie px-4 py-3 text-texto outline-none transition focus-visible:border-perigo focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-perigo"
            data-testid="confirmar-exclusao-texto"
          />
        </label>

        <div className="flex flex-wrap justify-end gap-3">
          <Botao type="button" variante="secundario" onClick={onCancelar} data-testid="confirmar-exclusao-cancelar">
            Cancelar
          </Botao>

          <Botao
            type="submit"
            variante="perigo"
            disabled={!liberado}
            carregando={confirmando}
            data-testid="confirmar-exclusao-confirmar"
          >
            Excluir definitivamente
          </Botao>
        </div>
      </form>
    </div>
  )
}

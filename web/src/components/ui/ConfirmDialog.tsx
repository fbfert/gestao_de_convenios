import { createContext, useCallback, useContext, useState, type ReactNode } from 'react'
import { Botao } from './Botao'

export type ConfirmOptions = {
  titulo: string
  descricao: ReactNode
  confirmarTexto?: string
  cancelarTexto?: string
  /** 'perigo' para ações que negam/cancelam algo; 'primario' para o resto. */
  variante?: 'perigo' | 'primario'
}

type ConfirmFn = (options: ConfirmOptions) => Promise<boolean>

const ConfirmContext = createContext<ConfirmFn | null>(null)

type PendingConfirm = ConfirmOptions & { resolve: (resultado: boolean) => void }

/**
 * Confirmação genérica reutilizável em qualquer tela, no padrão visual do
 * app — para ações que mutam estado (finalizar, negar, consultar convênio,
 * reprocessar) mas não são destrutivas o bastante para exigir digitar uma
 * palavra, como o ConfirmarExclusao.
 *
 * Uso: `const confirmar = useConfirm(); if (await confirmar({ titulo, descricao })) { ... }`
 */
export function ConfirmDialogProvider({ children }: { children: ReactNode }) {
  const [pendente, setPendente] = useState<PendingConfirm | null>(null)

  const confirmar = useCallback<ConfirmFn>((options) => {
    return new Promise<boolean>((resolve) => {
      setPendente({ ...options, resolve })
    })
  }, [])

  const responder = (resultado: boolean) => {
    pendente?.resolve(resultado)
    setPendente(null)
  }

  return (
    <ConfirmContext.Provider value={confirmar}>
      {children}

      {pendente ? (
        <div
          className="fixed inset-0 z-(--z-dialogo) flex items-center justify-center bg-texto/40 p-4"
          role="alertdialog"
          aria-modal="true"
          aria-label={pendente.titulo}
          data-testid="confirm-dialog"
        >
          <div className="w-full max-w-md space-y-4 rounded-janela border border-linha bg-superficie-elevada p-6 shadow-e3">
            <div>
              <h3 className="text-lg font-semibold text-texto">{pendente.titulo}</h3>
              <div className="mt-2 text-sm leading-6 text-texto-suave">{pendente.descricao}</div>
            </div>

            <div className="flex flex-wrap justify-end gap-3">
              <Botao
                type="button"
                variante="secundario"
                onClick={() => responder(false)}
                data-testid="confirm-dialog-cancelar"
              >
                {pendente.cancelarTexto ?? 'Cancelar'}
              </Botao>
              <Botao
                type="button"
                variante={pendente.variante ?? 'perigo'}
                onClick={() => responder(true)}
                data-testid="confirm-dialog-confirmar"
              >
                {pendente.confirmarTexto ?? 'Confirmar'}
              </Botao>
            </div>
          </div>
        </div>
      ) : null}
    </ConfirmContext.Provider>
  )
}

export function useConfirm(): ConfirmFn {
  const ctx = useContext(ConfirmContext)

  if (!ctx) {
    throw new Error('useConfirm precisa estar dentro de <ConfirmDialogProvider>.')
  }

  return ctx
}

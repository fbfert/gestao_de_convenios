import { useCallback, useRef, useState } from 'react'
import { getHttpErrorMessage } from '../../lib/httpError'
import { isUnimedCredencialInativaError } from '../../lib/unimedAutomacaoError'
import type { AutomacaoUnimedDesativadaModalProps } from './AutomacaoUnimedDesativadaModal'

/**
 * Centraliza o "gate" de automação Unimed desativada: qualquer chamador
 * que trate erro de mutation Unimed usa este hook — se o erro for
 * especificamente credencial inativa, abre o modal de ativação e guarda
 * `retry` pra refazer a ação sozinho após ativar; senão vira aviso na própria
 * tela. `modalProps` e `avisoProps` são espalhados direto em
 * `<AutomacaoUnimedDesativadaModal {...modalProps} />` e `<AvisoErro
 * {...avisoProps} />` no JSX do chamador.
 *
 * O caso "senão" era `window.alert`, que trava a aba até alguém clicar e some
 * sem deixar a mensagem para reler.
 */
export function useAutomacaoUnimedGate() {
  const [aberto, setAberto] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const acaoPendenteRef = useRef<(() => void) | null>(null)

  const tratarErroUnimed = useCallback(
    (error: unknown, mensagemPadrao: string, retry: () => void) => {
      if (isUnimedCredencialInativaError(error)) {
        acaoPendenteRef.current = retry
        setAberto(true)
        return
      }

      setErro(getHttpErrorMessage(error, mensagemPadrao))
    },
    [],
  )

  const modalProps: AutomacaoUnimedDesativadaModalProps = {
    aberto,
    onClose: () => {
      acaoPendenteRef.current = null
      setAberto(false)
    },
    onAtivada: () => {
      setAberto(false)
      const acao = acaoPendenteRef.current
      acaoPendenteRef.current = null
      acao?.()
    },
  }

  const avisoProps = {
    mensagem: erro,
    onFechar: () => setErro(null),
  }

  return { tratarErroUnimed, modalProps, avisoProps }
}

import { useCallback, useRef, useState } from 'react'
import { getHttpErrorMessage } from '../../lib/httpError'
import { isUnimedCredencialInativaError } from '../../lib/unimedAutomacaoError'
import type { AutomacaoUnimedDesativadaModalProps } from './AutomacaoUnimedDesativadaModal'

/**
 * Centraliza o "gate" de automação Unimed desativada: qualquer chamador
 * que trate erro de mutation Unimed troca o `window.alert()` cru por este
 * hook — se o erro for especificamente credencial inativa, abre o modal de
 * ativação e guarda `retry` pra refazer a ação sozinho após ativar; senão
 * cai no alert de sempre. `modalProps` é espalhado direto em
 * `<AutomacaoUnimedDesativadaModal {...modalProps} />` no JSX do chamador.
 */
export function useAutomacaoUnimedGate() {
  const [aberto, setAberto] = useState(false)
  const acaoPendenteRef = useRef<(() => void) | null>(null)

  const tratarErroUnimed = useCallback(
    (error: unknown, mensagemPadrao: string, retry: () => void) => {
      if (isUnimedCredencialInativaError(error)) {
        acaoPendenteRef.current = retry
        setAberto(true)
        return
      }

      window.alert(getHttpErrorMessage(error, mensagemPadrao))
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

  return { tratarErroUnimed, modalProps }
}

import { useCallback } from 'react'
import { useAuthStore } from '../stores/authStore'

export type Pode = (permissao?: string) => boolean

/**
 * Consulta as permissões do usuário logado.
 *
 * Esconder um item do menu é conveniência, não segurança: quem barra de fato é
 * o middleware `permission:` da API. Por isso o caso "ainda não sei" é
 * permissivo — sessão gravada antes de a API passar a mandar `permissions`
 * mostraria um menu vazio até o GET /user responder, e menu vazio parece
 * sistema quebrado.
 */
export function usePode(): Pode {
  const permissions = useAuthStore((state) => state.user?.permissions)

  return useCallback(
    (permissao?: string) => {
      if (!permissao || permissions === undefined) {
        return true
      }

      return permissions.includes(permissao)
    },
    [permissions],
  )
}

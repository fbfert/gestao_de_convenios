import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import type { ComponenteSaude } from './types'

/**
 * Saúde dos componentes do tenant, lida pelo card do Dashboard.
 *
 * Mesmo `refetchInterval` de 30s do resto do Dashboard: um card de saúde que só
 * atualiza no F5 responde sobre o passado, que é o oposto do que ele existe para
 * dizer. `refetchOnWindowFocus` religado pelo mesmo motivo que o painel — o app
 * o desliga globalmente no App.tsx.
 */
export function useSaudeComponentes() {
  return useQuery({
    queryKey: ['saude', 'componentes'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: ComponenteSaude[] }>('/saude')

      return data.data
    },
    refetchOnWindowFocus: true,
    refetchInterval: 30000,
  })
}

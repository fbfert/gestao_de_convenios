import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import type { Antecipacao, AntecipacaoFilters, PaginatedResponse } from './types'

export function useAntecipacoes(filters: AntecipacaoFilters, page: number) {
  return useQuery({
    queryKey: ['antecipacoes', filters, page],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<Antecipacao>>('/antecipacoes', {
        params: {
          ...filters,
          page,
          per_page: 10,
        },
      })

      return data
    },
  })
}

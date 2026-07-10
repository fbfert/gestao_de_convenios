import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { Conciliacao, ConciliacaoFilters, PaginatedResponse } from './types'

export function useConciliacoes(filters: ConciliacaoFilters, page: number) {
  return useQuery({
    queryKey: ['conciliacoes', filters, page],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<Conciliacao>>('/conciliacoes', {
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

function useConciliacaoMutation(endpoint: 'marcar-conferido' | 'marcar-pago') {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await apiClient.patch<{ data: Conciliacao }>(
        `/conciliacoes/${id}/${endpoint}`,
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['conciliacoes'] })
    },
  })
}

export function useMarcarConferidoConciliacao() {
  return useConciliacaoMutation('marcar-conferido')
}

export function useMarcarPagoConciliacao() {
  return useConciliacaoMutation('marcar-pago')
}

export function useGerarConciliacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (guiaId: number) => {
      const { data } = await apiClient.post<{ data: Conciliacao }>(`/guias/${guiaId}/conciliacao`)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['conciliacoes'] })
      await queryClient.invalidateQueries({ queryKey: ['guias'] })
    },
  })
}

export { getHttpErrorMessage }


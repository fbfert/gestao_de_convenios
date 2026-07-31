import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { AutomacaoExecucao, AutomacaoFilters, PaginatedResponse } from './types'

export function useAutomacoes(filters: AutomacaoFilters, page: number) {
  return useQuery({
    queryKey: ['automacoes', filters, page],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<AutomacaoExecucao>>('/automacoes', {
        params: {
          ...filters,
          needs_attention: filters.needs_attention || undefined,
          page,
          per_page: 10,
        },
      })

      return data
    },
  })
}

export function useAutomacao(id: number | null) {
  return useQuery({
    queryKey: ['automacoes', id],
    enabled: id !== null,
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AutomacaoExecucao }>(`/automacoes/${id}`)
      return data.data
    },
  })
}

export function useReprocessarAutomacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await apiClient.post<{ data: AutomacaoExecucao }>(
        `/automacoes/${id}/reprocessar`,
      )
      return data.data
    },
    onSuccess: async (_data, id) => {
      await queryClient.invalidateQueries({ queryKey: ['automacoes'] })
      await queryClient.invalidateQueries({ queryKey: ['automacoes', id] })
    },
  })
}

export { getHttpErrorMessage }

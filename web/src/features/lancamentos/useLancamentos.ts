import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { Lancamento, LancamentoFilters, LancamentoForm, PaginatedResponse } from './types'

export function useLancamentos(filters: LancamentoFilters, page: number) {
  return useQuery({
    queryKey: ['lancamentos', filters, page],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<Lancamento>>('/lancamentos', {
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

export function useCriarLancamento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: LancamentoForm) => {
      const { data } = await apiClient.post<{ data: Lancamento }>(
        `/antecipacoes/${Number(payload.antecipacao_id)}/lancamentos`,
        {
          profissional_id: Number(payload.profissional_id),
          data_sessao: payload.data_sessao,
        },
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['lancamentos'] })
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
    },
  })
}

export { getHttpErrorMessage }

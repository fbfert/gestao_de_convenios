import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
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

export function useAntecipacao(id: number | null) {
  return useQuery({
    queryKey: ['antecipacoes', id],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Antecipacao }>(`/antecipacoes/${id}`)
      return data.data
    },
    enabled: id !== null,
  })
}

export type AntecipacaoEditForm = {
  qtd_autorizada: string
  ciclo_inicio: string
  ciclo_fim: string
}

export function useAtualizarAntecipacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: AntecipacaoEditForm }) => {
      const { data } = await apiClient.patch<{ data: Antecipacao }>(`/antecipacoes/${id}`, {
        qtd_autorizada: Number(payload.qtd_autorizada),
        ciclo_inicio: payload.ciclo_inicio,
        ciclo_fim: payload.ciclo_fim,
      })
      return data.data
    },
    onSuccess: async (_data, { id }) => {
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes', id] })
    },
  })
}

export { getHttpErrorMessage }

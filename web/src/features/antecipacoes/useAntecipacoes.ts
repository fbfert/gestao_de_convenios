import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type {
  Antecipacao,
  AntecipacaoElegivel,
  AntecipacaoFilters,
  CriarAntecipacaoPayload,
  PaginatedResponse,
} from './types'

export { getHttpErrorMessage }

export function useAntecipacoesElegiveis(enabled = true) {
  return useQuery({
    queryKey: ['antecipacoes', 'elegiveis'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AntecipacaoElegivel[] }>('/antecipacoes/elegiveis')

      return data.data
    },
    enabled,
  })
}

export function useAntecipacoes(filters: AntecipacaoFilters, page: number) {
  return useQuery({
    queryKey: ['antecipacoes', filters, page],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<Antecipacao>>('/antecipacoes', {
        params: { ...filters, page, per_page: 20 },
      })

      return data
    },
  })
}

function invalidarAntecipacoes(queryClient: ReturnType<typeof useQueryClient>) {
  return queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
}

export function useCriarAntecipacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: CriarAntecipacaoPayload) => {
      const { data } = await apiClient.post<{ data: Antecipacao }>('/antecipacoes', payload)

      return data.data
    },
    onSuccess: async () => {
      await invalidarAntecipacoes(queryClient)
    },
  })
}

export function useAtualizarAntecipacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      id,
      status,
      observacoes,
    }: {
      id: number
      status?: 'pendente' | 'ignorada'
      observacoes?: string | null
    }) => {
      const { data } = await apiClient.patch<{ data: Antecipacao }>(`/antecipacoes/${id}`, {
        status,
        observacoes,
      })

      return data.data
    },
    onSuccess: async () => {
      await invalidarAntecipacoes(queryClient)
    },
  })
}

export function useRemoverAntecipacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/antecipacoes/${id}`)
    },
    onSuccess: async () => {
      await invalidarAntecipacoes(queryClient)
    },
  })
}

export function useMarcarAntecipacaoGerada() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, solicitacao_gerada_id }: { id: number; solicitacao_gerada_id: number }) => {
      const { data } = await apiClient.patch<{ data: Antecipacao }>(`/antecipacoes/${id}/marcar-gerada`, {
        solicitacao_gerada_id,
      })

      return data.data
    },
    onSuccess: async () => {
      await invalidarAntecipacoes(queryClient)
    },
  })
}

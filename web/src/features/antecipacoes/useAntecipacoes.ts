import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type {
  Antecipacao,
  AntecipacaoElegivel,
  AntecipacaoFilters,
  CriarAntecipacaoPayload,
  IgnorarAntecipacaoPayload,
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
        params: { ...filters, page },
      })

      return data
    },
  })
}

function invalidarAposGerar(queryClient: ReturnType<typeof useQueryClient>) {
  // 'guias' junto: gerar cria item+guia na solicitação de origem (mesmo
  // motivo que useAdicionarItem já invalida os dois).
  return Promise.all([
    queryClient.invalidateQueries({ queryKey: ['antecipacoes'] }),
    queryClient.invalidateQueries({ queryKey: ['solicitacoes'] }),
    queryClient.invalidateQueries({ queryKey: ['guias'] }),
  ])
}

/** Gera de fato: cria item(ns) novo(s) por renovação na solicitação de origem. */
export function useCriarAntecipacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: CriarAntecipacaoPayload) => {
      const { data } = await apiClient.post<{ data: Antecipacao }>('/antecipacoes', payload)

      return data.data
    },
    onSuccess: async () => {
      await invalidarAposGerar(queryClient)
    },
  })
}

export function useIgnorarAntecipacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: IgnorarAntecipacaoPayload) => {
      const { data } = await apiClient.post<{ data: Antecipacao }>('/antecipacoes/ignorar', payload)

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
    },
  })
}

export function useAtualizarAntecipacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, observacoes }: { id: number; observacoes?: string | null }) => {
      const { data } = await apiClient.patch<{ data: Antecipacao }>(`/antecipacoes/${id}`, { observacoes })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
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
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
    },
  })
}

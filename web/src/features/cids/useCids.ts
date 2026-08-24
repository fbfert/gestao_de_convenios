import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { Cid, CidPayload } from './types'
import type { Ordenacao } from '../../lib/useOrdenacao'

type ListResponse<T> = {
  data: T[]
}

export function useCidsCrud(busca: string, ordenacao?: Ordenacao) {
  return useQuery({
    queryKey: ['cids-crud', busca, ordenacao],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<Cid>>('/cids', {
        params: {
          busca: busca || undefined,
          incluir_inativos: true,
          ordenar_por: ordenacao?.ordenar_por,
          direcao: ordenacao?.direcao,
        },
      })

      return data.data
    },
  })
}

export function useCriarCid() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: CidPayload) => {
      const { data } = await apiClient.post<{ data: Cid }>('/cids', payload)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['cids-crud'] })
      await queryClient.invalidateQueries({ queryKey: ['cids'] })
    },
  })
}

export function useAtualizarCid() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: Partial<CidPayload> }) => {
      const { data } = await apiClient.patch<{ data: Cid }>(`/cids/${id}`, payload)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['cids-crud'] })
      await queryClient.invalidateQueries({ queryKey: ['cids'] })
    },
  })
}

export { getHttpErrorMessage }

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { Medico, MedicoForm } from './types'

type ListResponse<T> = {
  data: T[]
}

export function useMedicos(busca: string) {
  return useQuery({
    queryKey: ['medicos', busca],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<Medico>>('/medicos', {
        params: {
          busca: busca || undefined,
        },
      })

      return data.data
    },
  })
}

export function useCriarMedico() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: MedicoForm) => {
      const { data } = await apiClient.post<{ data: Medico }>('/medicos', payload)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['medicos'] })
    },
  })
}

export function useAtualizarMedico() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: Partial<MedicoForm> }) => {
      const { data } = await apiClient.patch<{ data: Medico }>(`/medicos/${id}`, payload)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['medicos'] })
    },
  })
}

export { getHttpErrorMessage }

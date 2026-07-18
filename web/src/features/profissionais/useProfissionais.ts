import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { Profissional, ProfissionalPayload } from './types'

type ListResponse<T> = {
  data: T[]
}

export function useProfissionaisCrud(filtros?: {
  busca?: string
  especialidade_id?: string | number
  incluir_inativos?: boolean
}) {
  return useQuery({
    queryKey: [
      'profissionais-crud',
      filtros?.busca ?? '',
      filtros?.especialidade_id ?? '',
      filtros?.incluir_inativos ? '1' : '0',
    ],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<Profissional>>('/profissionais', {
        params: {
          busca: filtros?.busca || undefined,
          especialidade_id: filtros?.especialidade_id || undefined,
          incluir_inativos: filtros?.incluir_inativos || undefined,
        },
      })

      return data.data
    },
  })
}

export function useCriarProfissional() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: ProfissionalPayload) => {
      const { data } = await apiClient.post<{ data: Profissional }>('/profissionais', payload)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['profissionais-crud'] })
      await queryClient.invalidateQueries({ queryKey: ['profissionais'] })
    },
  })
}

export function useAtualizarProfissional() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: Partial<ProfissionalPayload> }) => {
      const { data } = await apiClient.patch<{ data: Profissional }>(`/profissionais/${id}`, payload)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['profissionais-crud'] })
      await queryClient.invalidateQueries({ queryKey: ['profissionais'] })
    },
  })
}

export { getHttpErrorMessage }

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { Especialidade, EspecialidadePayload } from './types'

type ListResponse<T> = {
  data: T[]
}

export function useEspecialidadesCrud(busca: string) {
  return useQuery({
    queryKey: ['especialidades-crud', busca],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<Especialidade>>('/especialidades', {
        params: {
          busca: busca || undefined,
          incluir_inativos: true,
          // A tela de cadastro edita o codigo de cada convenio, entao precisa
          // de todos eles junto da listagem.
          com_codigos: 1,
        },
      })

      return data.data
    },
  })
}

export function useCriarEspecialidade() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: EspecialidadePayload) => {
      const { data } = await apiClient.post<{ data: Especialidade }>('/especialidades', payload)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['especialidades-crud'] })
      await queryClient.invalidateQueries({ queryKey: ['especialidades'] })
    },
  })
}

export function useAtualizarEspecialidade() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: Partial<EspecialidadePayload> }) => {
      const { data } = await apiClient.patch<{ data: Especialidade }>(`/especialidades/${id}`, payload)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['especialidades-crud'] })
      await queryClient.invalidateQueries({ queryKey: ['especialidades'] })
    },
  })
}

export { getHttpErrorMessage }

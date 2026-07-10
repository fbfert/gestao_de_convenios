import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type {
  Guia,
  GuiaFilters,
  GuiaFinalizarForm,
  GuiaForm,
  PaginatedResponse,
} from './types'

export function useGuias(filters: GuiaFilters, page: number) {
  return useQuery({
    queryKey: ['guias', filters, page],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<Guia>>('/guias', {
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

export function useCriarGuia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: GuiaForm) => {
      const { data } = await apiClient.post<{ data: Guia }>('/guias', {
        ...payload,
        solicitacao_id: payload.solicitacao_id ? Number(payload.solicitacao_id) : null,
        convenio_id: Number(payload.convenio_id),
        paciente_id: Number(payload.paciente_id),
        profissional_id: Number(payload.profissional_id),
        especialidade_id: Number(payload.especialidade_id),
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['guias'] })
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
    },
  })
}

export function useFinalizarGuia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: GuiaFinalizarForm }) => {
      const { data } = await apiClient.patch<{ data: Guia }>(`/guias/${id}/finalizar`, payload)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['guias'] })
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
    },
  })
}

export function useNegarGuia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await apiClient.patch<{ data: Guia }>(`/guias/${id}/negar`)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['guias'] })
    },
  })
}

export { getHttpErrorMessage }

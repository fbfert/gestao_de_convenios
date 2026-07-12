import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type {
  PaginatedResponse,
  Solicitacao,
  SolicitacaoFilters,
  SolicitacaoForm,
} from './types'

export function useSolicitacoes(filters: SolicitacaoFilters, page: number) {
  return useQuery({
    queryKey: ['solicitacoes', filters, page],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<Solicitacao>>('/solicitacoes', {
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

export function useCriarSolicitacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: SolicitacaoForm) => {
      const { data } = await apiClient.post<{ data: Solicitacao }>('/solicitacoes', {
        ...payload,
        paciente_id: Number(payload.paciente_id),
        profissional_id: Number(payload.profissional_id),
        especialidade_id: Number(payload.especialidade_id),
        convenio_id: Number(payload.convenio_id),
        medico_id: Number(payload.medico_id),
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
    },
  })
}

function useSolicitacaoStatusMutation(endpoint: 'aprovar' | 'negar') {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await apiClient.patch<{ data: Solicitacao }>(`/solicitacoes/${id}/${endpoint}`)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
    },
  })
}

export function useAprovarSolicitacao() {
  return useSolicitacaoStatusMutation('aprovar')
}

export function useNegarSolicitacao() {
  return useSolicitacaoStatusMutation('negar')
}

export { getHttpErrorMessage }

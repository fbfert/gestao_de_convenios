import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type {
  PaginatedResponse,
  PedidoMedicoAiResult,
  Solicitacao,
  SolicitacaoFilters,
  SolicitacaoForm,
  SolicitacaoStatus,
} from './types'
import type { EspecialidadeRef, MedicoRef, PacienteRef } from '../../lib/queries/useReferenceData'

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
      const itens = payload.itens?.length
        ? payload.itens
        : [
            {
              especialidade_id: payload.especialidade_id,
              profissional_id: payload.profissional_id,
              quantidade: payload.quantidade || '10',
              observacoes: undefined,
            },
          ]

      const { data } = await apiClient.post<{ data: Solicitacao }>('/solicitacoes', {
        ...payload,
        paciente_id: Number(payload.paciente_id),
        profissional_id: Number(payload.profissional_id),
        especialidade_id: Number(payload.especialidade_id),
        convenio_id: Number(payload.convenio_id),
        medico_id: Number(payload.medico_id),
        itens: itens.map((item) => ({
          especialidade_id: Number(item.especialidade_id),
          profissional_id: Number(item.profissional_id),
          quantidade: item.quantidade ? Number(item.quantidade) : undefined,
          observacoes: item.observacoes || undefined,
        })),
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
    },
  })
}

export function useAnalisarPedidoMedico() {
  return useMutation({
    mutationFn: async (arquivo: File) => {
      const formData = new FormData()
      formData.append('arquivo', arquivo)

      const { data } = await apiClient.post<{ data: PedidoMedicoAiResult }>(
        '/solicitacoes/ler-pedido-medico',
        formData,
      )

      return data.data
    },
  })
}

export function useCriarPacienteRapido() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: { nome: string; convenio_id: number }) => {
      const { data } = await apiClient.post<{ data: PacienteRef }>(
        '/solicitacoes/pacientes-rapido',
        payload,
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['pacientes'] })
    },
  })
}

export function useCriarEspecialidadeRapida() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: { nome: string }) => {
      const { data } = await apiClient.post<{ data: EspecialidadeRef }>(
        '/solicitacoes/especialidades-rapido',
        payload,
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['especialidades'] })
    },
  })
}

export function useCriarMedicoRapido() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: { nome: string }) => {
      const { data } = await apiClient.post<{ data: MedicoRef }>(
        '/solicitacoes/medicos-rapido',
        payload,
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['medicos'] })
    },
  })
}

export async function abrirPedidoMedico(solicitacaoId: number, nomeOriginal?: string | null) {
  const { data } = await apiClient.get<Blob>(`/solicitacoes/${solicitacaoId}/pedido-medico`, {
    responseType: 'blob',
  })
  const url = window.URL.createObjectURL(data)
  const opened = window.open(url, '_blank', 'noreferrer')

  if (opened) {
    window.setTimeout(() => window.URL.revokeObjectURL(url), 1000)
    return
  }

  const link = document.createElement('a')
  link.href = url
  link.download = nomeOriginal || `pedido-medico-${solicitacaoId}`
  link.click()
  window.setTimeout(() => window.URL.revokeObjectURL(url), 1000)
}

export type SolicitacaoStatusPayload = {
  id: number
  status: SolicitacaoStatus
}

export function useAtualizarStatusSolicitacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, status }: SolicitacaoStatusPayload) => {
      const { data } = await apiClient.patch<{ data: Solicitacao }>(`/solicitacoes/${id}/status`, {
        status,
      })
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
    },
  })
}

export function useEnviarItemUnimed() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (itemId: number) => {
      const { data } = await apiClient.post<{
        data: {
          id: number
          status: string
          operacao: string
          solicitacao_item_id: number
          queued_at: string | null
        }
      }>(`/solicitacao-itens/${itemId}/enviar-unimed`)

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
    },
  })
}

export function useAprovarSolicitacao() {
  return useAtualizarStatusSolicitacao()
}

export function useNegarSolicitacao() {
  return useAtualizarStatusSolicitacao()
}

export { getHttpErrorMessage }

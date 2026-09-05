import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type {
  PaginatedResponse,
  PedidoMedicoAiResult,
  Solicitacao,
  SolicitacaoDocumentoTipo,
  SolicitacaoFilters,
  SolicitacaoForm,
  SolicitacaoStatus,
} from './types'
import type { CidRef, EspecialidadeRef, MedicoRef, PacienteRef } from '../../lib/queries/useReferenceData'

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
        convenio_id: Number(payload.convenio_id),
        medico_id: Number(payload.medico_id),
        cid_ids: payload.cid_ids.map(Number),
        itens: payload.itens.map((item) => ({
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

export function useSolicitacao(id: number | null) {
  return useQuery({
    queryKey: ['solicitacoes', id],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Solicitacao }>(`/solicitacoes/${id}`)
      return data.data
    },
    enabled: id !== null,
  })
}

export type SolicitacaoEditForm = {
  medico_id: string
  cid_ids: string[]
  solicitado_em: string
  observacoes: string
}

export function useAtualizarSolicitacao() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: SolicitacaoEditForm }) => {
      const { data } = await apiClient.patch<{ data: Solicitacao }>(`/solicitacoes/${id}`, {
        medico_id: Number(payload.medico_id),
        cid_ids: payload.cid_ids.map(Number),
        solicitado_em: payload.solicitado_em,
        observacoes: payload.observacoes || null,
      })

      return data.data
    },
    onSuccess: async (_data, { id }) => {
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes', id] })
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
    mutationFn: async (payload: { nome: string; convenio_id: number; carteirinha: string }) => {
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
    mutationFn: async (payload: { nome: string; crm?: string; crm_uf?: string; especialidade_medica?: string }) => {
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

export function useCriarCidRapido() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: { codigo: string; descricao: string }) => {
      const { data } = await apiClient.post<{ data: CidRef }>(
        '/solicitacoes/cids-rapido',
        payload,
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['cids'] })
    },
  })
}

export function useAnexarDocumento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      solicitacaoId,
      tipo,
      arquivo,
      solicitacaoItemId,
    }: {
      solicitacaoId: number
      tipo: SolicitacaoDocumentoTipo
      arquivo: File
      solicitacaoItemId?: number | null
    }) => {
      const formData = new FormData()
      formData.append('arquivo', arquivo)
      formData.append('tipo', tipo)
      if (solicitacaoItemId) {
        formData.append('solicitacao_item_id', String(solicitacaoItemId))
      }

      const { data } = await apiClient.post<{ data: Solicitacao }>(
        `/solicitacoes/${solicitacaoId}/documentos`,
        formData,
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
    },
  })
}

/** Anexa à solicitação um arquivo que já existe na pasta do paciente, sem upload. */
export function useVincularDocumento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      solicitacaoId,
      pacienteArquivoId,
      solicitacaoItemId,
    }: {
      solicitacaoId: number
      pacienteArquivoId: number
      solicitacaoItemId?: number | null
    }) => {
      const { data } = await apiClient.post<{ data: Solicitacao }>(
        `/solicitacoes/${solicitacaoId}/documentos/vincular`,
        {
          paciente_arquivo_id: pacienteArquivoId,
          solicitacao_item_id: solicitacaoItemId || undefined,
        },
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
      await queryClient.invalidateQueries({ queryKey: ['pacientes-arquivos'] })
    },
  })
}

export function useRemoverDocumento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      solicitacaoId,
      documentoId,
    }: {
      solicitacaoId: number
      documentoId: number
    }) => {
      const { data } = await apiClient.delete<{ data: Solicitacao }>(
        `/solicitacoes/${solicitacaoId}/documentos/${documentoId}`,
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
    },
  })
}

export async function abrirDocumento(
  solicitacaoId: number,
  documentoId: number,
  nomeOriginal?: string | null,
) {
  const { data } = await apiClient.get<Blob>(
    `/solicitacoes/${solicitacaoId}/documentos/${documentoId}`,
    { responseType: 'blob' },
  )
  const url = window.URL.createObjectURL(data)
  const opened = window.open(url, '_blank', 'noreferrer')

  if (opened) {
    window.setTimeout(() => window.URL.revokeObjectURL(url), 1000)
    return
  }

  const link = document.createElement('a')
  link.href = url
  link.download = nomeOriginal || `documento-${documentoId}`
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

export function useVerificarAndamentoItem() {
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
      }>(`/solicitacao-itens/${itemId}/verificar-andamento`)

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

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import { useGerarConciliacao as useGerarConciliacaoMutation } from '../conciliacao/useConciliacoes'
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

export function useGuia(id: number | null) {
  return useQuery({
    queryKey: ['guias', id],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Guia }>(`/guias/${id}`)
      return data.data
    },
    enabled: id !== null,
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
        numero_guia: payload.numero_guia.trim() || null,
        sessoes_solicitadas: payload.sessoes_solicitadas ? Number(payload.sessoes_solicitadas) : null,
        sessoes_autorizadas: payload.sessoes_autorizadas ? Number(payload.sessoes_autorizadas) : null,
        protocolo_operadora: payload.protocolo_operadora?.trim() || null,
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['guias'] })
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
    },
  })
}

export type GuiaEditForm = {
  profissional_id: string
  especialidade_id: string
  numero_guia: string
  tipo_terapia: string
  data_solicitacao: string
  data_finalizacao: string
  sessoes_solicitadas: string
  sessoes_autorizadas: string
  protocolo_operadora: string
  senha: string
  validade_senha: string
  observacoes: string
}

export function useAtualizarGuia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: GuiaEditForm }) => {
      const { data } = await apiClient.patch<{ data: Guia }>(`/guias/${id}`, {
        profissional_id: Number(payload.profissional_id),
        especialidade_id: Number(payload.especialidade_id),
        numero_guia: payload.numero_guia.trim() || null,
        tipo_terapia: payload.tipo_terapia,
        data_solicitacao: payload.data_solicitacao,
        data_finalizacao: payload.data_finalizacao || null,
        sessoes_solicitadas: payload.sessoes_solicitadas ? Number(payload.sessoes_solicitadas) : null,
        sessoes_autorizadas: payload.sessoes_autorizadas ? Number(payload.sessoes_autorizadas) : null,
        protocolo_operadora: payload.protocolo_operadora.trim() || null,
        senha: payload.senha.trim() || null,
        validade_senha: payload.validade_senha || null,
        observacoes: payload.observacoes || null,
      })
      return data.data
    },
    onSuccess: async (_data, { id }) => {
      await queryClient.invalidateQueries({ queryKey: ['guias'] })
      await queryClient.invalidateQueries({ queryKey: ['guias', id] })
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

/** Guias negadas com o alerta ainda visível (não ocultado) — Guias + Dashboard. */
export function useGuiasAlertaNegacao() {
  return useQuery({
    queryKey: ['guias', 'alerta-negacao'],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<Guia>>('/guias', {
        params: { alerta_negacao_pendente: 1, per_page: 50 },
      })

      return data.data
    },
  })
}

export function useOcultarAlertaNegacaoGuia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await apiClient.patch<{ data: Guia }>(`/guias/${id}/ocultar-alerta-negacao`)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['guias'] })
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

export function useConsultarGuiaUnimed() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await apiClient.post<{
        data: {
          id: number
          status: string
          operacao: string
          guia_id: number
          queued_at: string | null
        }
      }>(`/guias/${id}/consultar-unimed`)

      return data.data
    },
    onSuccess: async (_data, id) => {
      await queryClient.invalidateQueries({ queryKey: ['guias'] })
      await queryClient.invalidateQueries({ queryKey: ['guias', id] })
    },
  })
}

export function useBuscarSenhaValidadeGuiaUnimed() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await apiClient.post<{
        data: {
          id: number
          status: string
          operacao: string
          guia_id: number
          queued_at: string | null
        }
      }>(`/guias/${id}/buscar-senha-validade-unimed`)

      return data.data
    },
    onSuccess: async (_data, id) => {
      await queryClient.invalidateQueries({ queryKey: ['guias'] })
      await queryClient.invalidateQueries({ queryKey: ['guias', id] })
    },
  })
}

export function useGerarConciliacao() {
  return useGerarConciliacaoMutation()
}

export { getHttpErrorMessage }

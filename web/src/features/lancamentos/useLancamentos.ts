import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type {
  LancamentoConfirmImportForm,
  Lancamento,
  LancamentoFilters,
  LancamentoForm,
  LancamentoImportForm,
  LancamentoTranscricaoImportResult,
  PaginatedResponse,
} from './types'

export function useLancamentos(filters: LancamentoFilters, page: number) {
  return useQuery({
    queryKey: ['lancamentos', filters, page],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<Lancamento>>('/lancamentos', {
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

export function useCriarLancamento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: LancamentoForm) => {
      const { data } = await apiClient.post<{ data: Lancamento }>(
        `/antecipacoes/${Number(payload.antecipacao_id)}/lancamentos`,
        {
          profissional_id: Number(payload.profissional_id),
          data_sessao: payload.data_sessao,
          hora_inicio: payload.hora_inicio || null,
          hora_fim: payload.hora_fim || null,
          acompanhante: payload.acompanhante || null,
          resumo_atividades: payload.resumo_atividades || null,
          observacoes: payload.observacoes || null,
        },
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['lancamentos'] })
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
    },
  })
}

export function useImportarLancamentosTranscritos() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: LancamentoImportForm) => {
      const { data } = await apiClient.post<{
        data: LancamentoTranscricaoImportResult
      }>(`/antecipacoes/${Number(payload.antecipacao_id)}/lancamentos/importar-transcricao`, {
        profissional_id: Number(payload.profissional_id),
        transcricao: payload.transcricao,
        confirmar_envio: false,
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['lancamentos'] })
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
    },
  })
}

export function useConfirmarLancamentosTranscritos() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: LancamentoConfirmImportForm) => {
      const formData = new FormData()
      formData.append('profissional_id', payload.profissional_id)
      formData.append('transcricao', payload.transcricao)
      formData.append('confirmar_envio', 'true')

      payload.sessoes.forEach((sessao, index) => {
        formData.append(`sessoes[${index}][data_sessao]`, sessao.data_sessao ?? '')
        formData.append(`sessoes[${index}][hora_inicio]`, sessao.hora_inicio ?? '')
        formData.append(`sessoes[${index}][hora_fim]`, sessao.hora_fim ?? '')
        formData.append(`sessoes[${index}][acompanhante]`, sessao.acompanhante ?? '')
        formData.append(`sessoes[${index}][resumo_atividades]`, sessao.resumo_atividades ?? '')
      })

      if (payload.pdf_registro_sessoes) {
        formData.append('pdf_registro_sessoes', payload.pdf_registro_sessoes)
      }

      const { data } = await apiClient.post<{
        data: LancamentoTranscricaoImportResult
      }>(`/antecipacoes/${Number(payload.antecipacao_id)}/lancamentos/importar-transcricao`, formData)

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['lancamentos'] })
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
    },
  })
}

export function useLancamento(id: number | null) {
  return useQuery({
    queryKey: ['lancamentos', id],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Lancamento }>(`/lancamentos/${id}`)
      return data.data
    },
    enabled: id !== null,
  })
}

export { getHttpErrorMessage }

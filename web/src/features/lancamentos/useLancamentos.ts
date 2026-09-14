import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { Guia, PaginatedResponse as GuiaPaginatedResponse } from '../guias/types'
import type {
  AnaliticoUnimedPreview,
  LancamentoConfirmImportForm,
  Lancamento,
  LancamentoFilters,
  LancamentoGuiaGrupo,
  LancamentoImportForm,
  LancamentoPrintTemplate,
  LancamentoPrintTemplateForm,
  LancamentoTranscricaoImportResult,
  PaginatedResponse,
} from './types'

/**
 * Busca paginada de guias para o modal de seleção da tela de Sessões — por
 * ID, número da guia, nome do paciente ou do profissional executante (filtro
 * `busca` da API). Ao contrário de `usePacientesBusca`/`useMedicosBusca`, fica
 * habilitada mesmo com o termo vazio: sem busca, mostra a listagem default
 * (guias com sessão disponível), não uma tela em branco.
 */
export function useGuiasBusca(params: { busca: string; page: number; enabled?: boolean }) {
  return useQuery({
    queryKey: ['guias-busca', params.busca, params.page],
    queryFn: async () => {
      const { data } = await apiClient.get<GuiaPaginatedResponse<Guia>>('/guias', {
        params: {
          busca: params.busca.trim() || undefined,
          disponivel_para_lancamento: '1',
          page: params.page,
        },
      })
      return { itens: data.data, meta: data.meta ?? null }
    },
    enabled: params.enabled ?? true,
  })
}

/** Cada item é um grupo por Guia (`LancamentoGuiaGrupo`), não uma sessão solta. */
export function useLancamentos(filters: LancamentoFilters, page: number) {
  return useQuery({
    queryKey: ['lancamentos', filters, page],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<LancamentoGuiaGrupo>>('/lancamentos', {
        params: {
          ...filters,
          busca: filters.busca.trim() || undefined,
          page,
        },
      })

      return data
    },
  })
}

export function useImportarLancamentosTranscritos() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: LancamentoImportForm) => {
      const { data } = await apiClient.post<{
        data: LancamentoTranscricaoImportResult
      }>(`/guias/${Number(payload.guia_id)}/lancamentos/importar-transcricao`, {
        profissional_id: Number(payload.profissional_id),
        transcricao: payload.transcricao,
        confirmar_envio: false,
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['lancamentos'] })
      await queryClient.invalidateQueries({ queryKey: ['guias'] })
    },
  })
}

/**
 * Lê o registro de sessões escaneado por IA.
 *
 * Devolve o mesmo formato da transcrição colada, então a tela de revisão e a
 * confirmação seguem iguais — muda só de onde o dado veio.
 */
export function useLerRegistroSessoes() {
  return useMutation({
    mutationFn: async ({ guiaId, arquivo }: { guiaId: string; arquivo: File }) => {
      const body = new FormData()
      body.append('arquivo', arquivo)

      const { data } = await apiClient.post<{ data: LancamentoTranscricaoImportResult }>(
        `/guias/${Number(guiaId)}/lancamentos/ler-registro`,
        body,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )

      return data.data
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
      formData.append('numero_cartao', payload.numero_cartao ?? '')
      formData.append('confirmar_envio', '1')

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
      }>(`/guias/${Number(payload.guia_id)}/lancamentos/importar-transcricao`, formData)

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['lancamentos'] })
      await queryClient.invalidateQueries({ queryKey: ['guias'] })
    },
  })
}

export function useImportarAnaliticoUnimed() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (arquivo: File) => {
      const formData = new FormData()
      formData.append('arquivo', arquivo)

      const { data } = await apiClient.post<{
        data: AnaliticoUnimedPreview
      }>('/lancamentos/importar-analitico', formData)

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['analiticos'] })
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

export type LancamentoEditForm = {
  profissional_id: string
  data_sessao: string
  hora_inicio: string
  hora_fim: string
  acompanhante: string
  resumo_atividades: string
  observacoes: string
}

export function useAtualizarLancamento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: LancamentoEditForm }) => {
      const { data } = await apiClient.patch<{ data: Lancamento }>(`/lancamentos/${id}`, {
        profissional_id: Number(payload.profissional_id),
        data_sessao: payload.data_sessao,
        hora_inicio: payload.hora_inicio || null,
        hora_fim: payload.hora_fim || null,
        acompanhante: payload.acompanhante || null,
        resumo_atividades: payload.resumo_atividades || null,
        observacoes: payload.observacoes || null,
      })
      return data.data
    },
    onSuccess: async (_data, { id }) => {
      await queryClient.invalidateQueries({ queryKey: ['lancamentos'] })
      await queryClient.invalidateQueries({ queryKey: ['lancamentos', id] })
    },
  })
}

export function useLancamentoPrintTemplate() {
  return useQuery({
    queryKey: ['lancamentos', 'templates', 'registro-sessoes'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: LancamentoPrintTemplate }>(
        '/lancamentos/templates/registro-sessoes',
      )
      return data.data
    },
  })
}

export function useAtualizarLancamentoPrintTemplate() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: LancamentoPrintTemplateForm) => {
      const { data } = await apiClient.put<{ data: LancamentoPrintTemplate }>(
        '/lancamentos/templates/registro-sessoes',
        payload,
      )
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ['lancamentos', 'templates', 'registro-sessoes'],
      })
    },
  })
}

export { getHttpErrorMessage }

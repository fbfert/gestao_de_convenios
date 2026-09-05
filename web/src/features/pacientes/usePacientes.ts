import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { LeituraCarteirinha, Paciente, PacienteForm } from './types'
import type { ListMeta } from '../../lib/queries/useReferenceData'

type PaginatedListResponse<T> = {
  data: T[]
  meta?: ListMeta
}

export type PacientesConsulta = {
  busca: string
  convenio_id: string
  status: string
  carteirinha: string
  ordenar_por: string
  direcao: 'asc' | 'desc'
}

export function usePacientesCrud(consulta: PacientesConsulta, page?: number) {
  return useQuery({
    queryKey: ['pacientes', 'crud', consulta, page],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedListResponse<Paciente>>('/pacientes', {
        params: {
          busca: consulta.busca || undefined,
          convenio_id: consulta.convenio_id || undefined,
          status: consulta.status || undefined,
          carteirinha: consulta.carteirinha || undefined,
          ordenar_por: consulta.ordenar_por || undefined,
          direcao: consulta.direcao,
          page,
        },
      })

      return data
    },
  })
}

export function useCriarPaciente() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: PacienteForm) => {
      const { data } = await apiClient.post<{ data: Paciente }>('/pacientes', {
        ...payload,
        convenio_id: Number(payload.convenio_id),
        ativo: payload.ativo,
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['pacientes'] })
    },
  })
}

export function useAtualizarPaciente() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: Partial<PacienteForm> }) => {
      const body: Record<string, unknown> = {
        ...payload,
      }

      if (typeof payload.convenio_id !== 'undefined' && payload.convenio_id !== '') {
        body.convenio_id = Number(payload.convenio_id)
      }

      const { data } = await apiClient.patch<{ data: Paciente }>(`/pacientes/${id}`, body)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['pacientes'] })
    },
  })
}

/**
 * Envia a imagem da carteirinha para leitura por IA.
 *
 * Nada é gravado no cadastro aqui: a resposta preenche o formulário e o
 * operador confere antes de salvar.
 */
export function useLerCarteirinha() {
  return useMutation({
    mutationFn: async (arquivo: File) => {
      const body = new FormData()
      body.append('arquivo', arquivo)

      const { data } = await apiClient.post<{ data: LeituraCarteirinha }>(
        '/pacientes/ler-carteirinha',
        body,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )

      return data.data
    },
  })
}

export { getHttpErrorMessage }

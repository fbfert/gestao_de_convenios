import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { DocumentoTipo } from '../../lib/documentoTipos'

export type PacienteArquivoVinculo = {
  id: number
  solicitacao_id: number
  solicitacao_item_id: number | null
  travado: boolean
}

export type PacienteArquivo = {
  id: number
  tipo: DocumentoTipo | string
  nome_original: string
  mime: string | null
  url: string
  created_at: string | null
  vinculos: PacienteArquivoVinculo[]
}

export function usePacienteArquivos(pacienteId: number | null) {
  return useQuery({
    queryKey: ['pacientes-arquivos', pacienteId],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: PacienteArquivo[] }>(
        `/pacientes/${pacienteId}/arquivos`,
      )

      return data.data
    },
    enabled: pacienteId !== null,
  })
}

export function useUploadPacienteArquivo() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      pacienteId,
      tipo,
      arquivo,
    }: {
      pacienteId: number
      tipo: DocumentoTipo
      arquivo: File
    }) => {
      const formData = new FormData()
      formData.append('arquivo', arquivo)
      formData.append('tipo', tipo)

      const { data } = await apiClient.post<{ data: PacienteArquivo }>(
        `/pacientes/${pacienteId}/arquivos`,
        formData,
      )

      return data.data
    },
    onSuccess: async (_data, { pacienteId }) => {
      await queryClient.invalidateQueries({ queryKey: ['pacientes-arquivos', pacienteId] })
    },
  })
}

export function useRemoverPacienteArquivo() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ pacienteId, arquivoId }: { pacienteId: number; arquivoId: number }) => {
      await apiClient.delete(`/pacientes/${pacienteId}/arquivos/${arquivoId}`)
    },
    onSuccess: async (_data, { pacienteId }) => {
      await queryClient.invalidateQueries({ queryKey: ['pacientes-arquivos', pacienteId] })
    },
  })
}

export async function abrirPacienteArquivo(
  pacienteId: number,
  arquivoId: number,
  nomeOriginal?: string | null,
) {
  const { data } = await apiClient.get<Blob>(`/pacientes/${pacienteId}/arquivos/${arquivoId}`, {
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
  link.download = nomeOriginal || `documento-${arquivoId}`
  link.click()
  window.setTimeout(() => window.URL.revokeObjectURL(url), 1000)
}

export { getHttpErrorMessage }

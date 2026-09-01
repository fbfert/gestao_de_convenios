import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { ConciliacaoImportLinhaDados, ConciliacaoImportPreview } from './types'

export async function baixarTemplateConciliacoes() {
  const resposta = await apiClient.get('/conciliacoes/importar/template', { responseType: 'blob' })

  const url = URL.createObjectURL(resposta.data as Blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'modelo-importacao-conciliacoes.xlsx'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

export function usePrevisualizarImportConciliacoes() {
  return useMutation({
    mutationFn: async (arquivo: File) => {
      const body = new FormData()
      body.append('arquivo', arquivo)

      const { data } = await apiClient.post<{ data: ConciliacaoImportPreview }>(
        '/conciliacoes/importar',
        body,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )

      return data.data
    },
  })
}

export function useConfirmarImportConciliacoes() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      loteId,
      linhaIds,
      edicoes,
    }: {
      loteId: number
      linhaIds: number[]
      edicoes: Record<number, Partial<ConciliacaoImportLinhaDados>>
    }) => {
      const { data } = await apiClient.post<{ data: ConciliacaoImportPreview }>(
        `/conciliacoes/importar/${loteId}/confirmar`,
        { linha_ids: linhaIds, edicoes },
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['conciliacoes'] })
    },
  })
}

export { getHttpErrorMessage }

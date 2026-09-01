import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { LancamentoImportLinhaDados, LancamentoImportPreview } from './types'

export async function baixarTemplateLancamentos() {
  const resposta = await apiClient.get('/lancamentos/importar/template', { responseType: 'blob' })

  const url = URL.createObjectURL(resposta.data as Blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'modelo-importacao-sessoes.xlsx'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

export function usePrevisualizarImportLancamentos() {
  return useMutation({
    mutationFn: async (arquivo: File) => {
      const body = new FormData()
      body.append('arquivo', arquivo)

      const { data } = await apiClient.post<{ data: LancamentoImportPreview }>(
        '/lancamentos/importar',
        body,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )

      return data.data
    },
  })
}

export function useConfirmarImportLancamentos() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      loteId,
      linhaIds,
      edicoes,
    }: {
      loteId: number
      linhaIds: number[]
      edicoes: Record<number, Partial<LancamentoImportLinhaDados>>
    }) => {
      const { data } = await apiClient.post<{ data: LancamentoImportPreview }>(
        `/lancamentos/importar/${loteId}/confirmar`,
        { linha_ids: linhaIds, edicoes },
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['lancamentos'] })
      await queryClient.invalidateQueries({ queryKey: ['antecipacoes'] })
    },
  })
}

export { getHttpErrorMessage }

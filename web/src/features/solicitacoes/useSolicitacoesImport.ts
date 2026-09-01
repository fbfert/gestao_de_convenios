import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { SolicitacaoImportLinhaDados, SolicitacaoImportPreview } from './types'

export async function baixarTemplateSolicitacoes() {
  const resposta = await apiClient.get('/solicitacoes/importar/template', {
    responseType: 'blob',
  })

  const url = URL.createObjectURL(resposta.data as Blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'modelo-importacao-solicitacoes.xlsx'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

export function usePrevisualizarImportSolicitacoes() {
  return useMutation({
    mutationFn: async (arquivo: File) => {
      const body = new FormData()
      body.append('arquivo', arquivo)

      const { data } = await apiClient.post<{ data: SolicitacaoImportPreview }>(
        '/solicitacoes/importar',
        body,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )

      return data.data
    },
  })
}

export function useConfirmarImportSolicitacoes() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      loteId,
      linhaIds,
      edicoes,
    }: {
      loteId: number
      linhaIds: number[]
      edicoes: Record<number, Partial<SolicitacaoImportLinhaDados>>
    }) => {
      const { data } = await apiClient.post<{ data: SolicitacaoImportPreview }>(
        `/solicitacoes/importar/${loteId}/confirmar`,
        { linha_ids: linhaIds, edicoes },
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
    },
  })
}

export { getHttpErrorMessage }

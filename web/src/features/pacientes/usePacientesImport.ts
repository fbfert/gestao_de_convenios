import { useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { PacienteImportLinhaDados, PacienteImportPreview } from './types'

/**
 * Baixa o modelo .xlsx da importação de Pacientes.
 *
 * Passa pelo apiClient, e não por um link direto, porque a rota exige o token
 * do Sanctum no cabeçalho — ver `exportarAuditoria` para o mesmo padrão.
 */
export async function baixarTemplatePacientes() {
  const resposta = await apiClient.get('/pacientes/importar/template', {
    responseType: 'blob',
  })

  const url = URL.createObjectURL(resposta.data as Blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'modelo-importacao-pacientes.xlsx'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

export function usePrevisualizarImportPacientes() {
  return useMutation({
    mutationFn: async (arquivo: File) => {
      const body = new FormData()
      body.append('arquivo', arquivo)

      const { data } = await apiClient.post<{ data: PacienteImportPreview }>(
        '/pacientes/importar',
        body,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )

      return data.data
    },
  })
}

export function useConfirmarImportPacientes() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      loteId,
      linhaIds,
      edicoes,
    }: {
      loteId: number
      linhaIds: number[]
      edicoes: Record<number, Partial<PacienteImportLinhaDados>>
    }) => {
      const { data } = await apiClient.post<{ data: PacienteImportPreview }>(
        `/pacientes/importar/${loteId}/confirmar`,
        { linha_ids: linhaIds, edicoes },
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['pacientes'] })
    },
  })
}

export { getHttpErrorMessage }

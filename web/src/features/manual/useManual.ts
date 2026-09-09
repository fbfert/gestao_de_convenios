import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'

export type ManualTipo = 'manual' | 'mapa-mental'

export type Manual = {
  tipo: ManualTipo
  conteudo_html: string
  atualizado_em: string | null
}

/**
 * Somente leitura: o manual virou conteúdo do produto, servido de arquivo
 * versionado. O `useUpdateManual` saiu junto com o endpoint de edição.
 *
 * Sem `atualizado_por`: quem alterou o texto agora aparece no histórico do git,
 * e não numa coluna do banco.
 */
export function useManual(tipo: ManualTipo = 'manual') {
  return useQuery({
    queryKey: ['manual', tipo],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Manual }>(`/manual/${tipo}`)
      return data.data
    },
  })
}

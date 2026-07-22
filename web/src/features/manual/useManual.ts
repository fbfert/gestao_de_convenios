import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'

export type ManualTipo = 'manual' | 'mapa-mental'

export type Manual = {
  tipo: ManualTipo
  conteudo_html: string
  atualizado_em: string | null
  atualizado_por: string | null
}

export function useManual(tipo: ManualTipo = 'manual') {
  return useQuery({
    queryKey: ['manual', tipo],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Manual }>(`/manual/${tipo}`)
      return data.data
    },
  })
}

export function useUpdateManual(tipo: ManualTipo = 'manual') {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (conteudoHtml: string) => {
      const { data } = await apiClient.put<{ data: Manual }>(`/manual/${tipo}`, {
        conteudo_html: conteudoHtml,
      })
      return data.data
    },
    onSuccess: (manual) => {
      queryClient.setQueryData(['manual', tipo], manual)
    },
  })
}

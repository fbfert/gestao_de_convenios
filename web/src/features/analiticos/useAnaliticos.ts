import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import type { AnaliticoUnimedLote } from '../lancamentos/types'

type ListResponse<T> = {
  data: T[]
}

export function useAnaliticosLotes() {
  return useQuery({
    queryKey: ['analiticos'],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<AnaliticoUnimedLote>>('/analiticos')
      return data.data
    },
  })
}

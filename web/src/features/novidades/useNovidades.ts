import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'

export type TipoNovidade = 'manual' | 'melhoria' | 'correcao' | 'aviso'

export type Novidade = {
  slug: string
  titulo: string
  tipo: TipoNovidade
  data: string
  corpo: string
  lida: boolean
}

type Resposta = { data: Novidade[]; meta: { nao_lidas: number } }

export function useNovidades(limite?: number) {
  return useQuery({
    queryKey: ['novidades', limite ?? 'todas'],
    queryFn: async () => {
      const { data } = await apiClient.get<Resposta>('/novidades', {
        params: limite ? { limit: limite } : undefined,
      })

      return data
    },
  })
}

export function useMarcarNovidadeLida() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (slug: string) => {
      await apiClient.post(`/novidades/${slug}/lida`)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['novidades'] })
    },
  })
}

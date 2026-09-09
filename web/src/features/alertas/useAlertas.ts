import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import type { Alerta, AlertaRegra, NivelAlerta, SituacaoAlerta } from './types'

export type FiltrosAlertas = {
  nivel?: NivelAlerta | ''
  chave?: string
  situacao?: SituacaoAlerta
}

type Paginado<T> = { data: T[]; meta: { total: number; current_page: number; last_page: number } }

export function useAlertas(filtros: FiltrosAlertas) {
  return useQuery({
    queryKey: ['alertas', filtros],
    queryFn: async () => {
      const params: Record<string, string> = {}
      if (filtros.nivel) params.nivel = filtros.nivel
      if (filtros.chave) params.chave = filtros.chave
      if (filtros.situacao) params.situacao = filtros.situacao

      const { data } = await apiClient.get<Paginado<Alerta>>('/alertas', { params })

      return data
    },
    // O avaliador roda a cada 15 min no servidor; 60s aqui é o suficiente para
    // a tela não ficar velha sem virar polling agressivo.
    refetchInterval: 60000,
  })
}

export function useReconhecerAlerta() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await apiClient.post<{ data: Alerta }>(`/alertas/${id}/reconhecer`)

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['alertas'] })
      await queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
  })
}

export function useSilenciarAlerta() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, ate }: { id: number; ate: string }) => {
      const { data } = await apiClient.post<{ data: Alerta }>(`/alertas/${id}/silenciar`, { ate })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['alertas'] })
      await queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
  })
}

export type Destinatario = {
  id: number
  email: string
  nome: string | null
  niveis: NivelAlerta[]
  chaves: string[] | null
  canal: 'digest' | 'imediato' | 'ambos'
  horario_digest: number
  ativo: boolean
  verificado_em: string | null
  falhas_consecutivas: number
}

export function useDestinatarios() {
  return useQuery({
    queryKey: ['alertas', 'destinatarios'],
    queryFn: async () =>
      (await apiClient.get<{ data: Destinatario[] }>('/alertas/destinatarios')).data.data,
  })
}

export function useSalvarDestinatario() {
  const queryClient = useQueryClient()

  return useMutation({
    // `id` opcional: sem ele a mutação cria, com ele atualiza.
    mutationFn: async (
      destinatario: Omit<Destinatario, 'id' | 'verificado_em' | 'falhas_consecutivas'> & {
        id?: number
      },
    ) => {
      const corpo = {
        email: destinatario.email,
        nome: destinatario.nome,
        niveis: destinatario.niveis,
        chaves: destinatario.chaves,
        canal: destinatario.canal,
        horario_digest: destinatario.horario_digest,
        ativo: destinatario.ativo,
      }

      const { data } = destinatario.id
        ? await apiClient.put<{ data: Destinatario }>(`/alertas/destinatarios/${destinatario.id}`, corpo)
        : await apiClient.post<{ data: Destinatario }>('/alertas/destinatarios', corpo)

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['alertas', 'destinatarios'] })
    },
  })
}

export function useRemoverDestinatario() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/alertas/destinatarios/${id}`)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['alertas', 'destinatarios'] })
    },
  })
}

export function useAlertaRegras() {
  return useQuery({
    queryKey: ['alertas', 'regras'],
    queryFn: async () => (await apiClient.get<{ data: AlertaRegra[] }>('/alertas/regras')).data.data,
  })
}

export function useAtualizarAlertaRegra() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (regra: AlertaRegra) => {
      const { data } = await apiClient.put<{ data: AlertaRegra }>(`/alertas/regras/${regra.id}`, {
        ativo: regra.ativo,
        nivel_base: regra.nivel_base,
        limiar_amarelo: regra.limiar_amarelo,
        limiar_vermelho: regra.limiar_vermelho,
        critica: regra.critica,
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['alertas'] })
    },
  })
}

import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import type { AnaliticoUnimedLote } from '../lancamentos/types'

type ListResponse<T> = {
  data: T[]
}

export type AnaliticoUnimedLoteLinhaDetalhe = {
  id: number
  linha: number | null
  origem: 'analitico' | 'glosa'
  natureza: 'pago' | 'glosado'
  processavel: boolean
  numero_guia_operadora: string | null
  numero_guia_prestador: string | null
  codigo: string | null
  usuario: string | null
  data_autorizacao: string | null
  data_realizacao: string | null
  procedimento: string | null
  descricao_procedimento: string | null
  qtd: string | null
  qtd_normalizada: number
  tipo: string | null
  motivo: string | null
  valor: string | null
  valor_normalizado: string | null
  local_realizacao: string | null
  chave_conciliacao: string
}

export type AnaliticoUnimedLoteDetalhe = {
  lote: AnaliticoUnimedLote
  analitico: {
    linhas: AnaliticoUnimedLoteLinhaDetalhe[]
    totais: {
      linhas: number
      valor: string | null
    }
  }
  glosas: {
    linhas: AnaliticoUnimedLoteLinhaDetalhe[]
    totais: {
      linhas: number
      valor: string | null
    }
  }
  conciliacao: {
    linhas: AnaliticoUnimedLoteLinhaDetalhe[]
    totais: {
      pago: string
      glosado: string
      saldo: string
    }
  }
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

export function useAnaliticoLote(id: number | null) {
  return useQuery({
    queryKey: ['analiticos', id],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AnaliticoUnimedLoteDetalhe }>(`/analiticos/${id}`)
      return data.data
    },
    enabled: id !== null,
  })
}

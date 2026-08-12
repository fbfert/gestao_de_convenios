import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'

export type ConfiguracoesGlobais = {
  /** Minutos que um login vale, contados da emissão do token. 0 = sem expirar. */
  sessao_minutos: number
  senha_alerta_dias: number
  sessoes_padrao: number
  itens_por_pagina: number
}

export type ConfiguracoesGlobaisForm = {
  sessao_minutos: string
  senha_alerta_dias: string
  sessoes_padrao: string
  itens_por_pagina: string
}

const chaveQuery = ['configuracoes', 'globais']

export function paraFormulario(dados: ConfiguracoesGlobais): ConfiguracoesGlobaisForm {
  return {
    sessao_minutos: String(dados.sessao_minutos),
    senha_alerta_dias: String(dados.senha_alerta_dias),
    sessoes_padrao: String(dados.sessoes_padrao),
    itens_por_pagina: String(dados.itens_por_pagina),
  }
}

/** Ex.: 480 → "8 h". Ajuda a conferir um número em minutos sem fazer conta. */
export function descreverMinutos(minutos: number): string {
  if (!Number.isFinite(minutos) || minutos <= 0) {
    return 'sem expiração'
  }

  if (minutos < 60) {
    return `${minutos} min`
  }

  const horas = Math.floor(minutos / 60)
  const resto = minutos % 60

  return resto === 0 ? `${horas} h` : `${horas} h ${resto} min`
}

export function useConfiguracoesGlobais() {
  return useQuery({
    queryKey: chaveQuery,
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: ConfiguracoesGlobais }>('/configuracoes/globais')
      return data.data
    },
  })
}

export function useSalvarConfiguracoesGlobais() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (form: ConfiguracoesGlobaisForm) => {
      const { data } = await apiClient.put<{ data: ConfiguracoesGlobais }>('/configuracoes/globais', {
        sessao_minutos: Number(form.sessao_minutos),
        senha_alerta_dias: Number(form.senha_alerta_dias),
        sessoes_padrao: Number(form.sessoes_padrao),
        itens_por_pagina: Number(form.itens_por_pagina),
      })
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chaveQuery })
    },
  })
}

export { getHttpErrorMessage }

import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { AuditFiltros, AuditItem, AuditOpcoes, AuditPagina } from './types'

/** Só o que está preenchido vira query string, para a URL não encher de vazios. */
function paramsDe(filtros: AuditFiltros, pagina: number) {
  const params: Record<string, string> = { page: String(pagina) }

  Object.entries(filtros).forEach(([chave, valor]) => {
    if (valor) {
      params[chave] = valor
    }
  })

  return params
}

export function useAuditoria(filtros: AuditFiltros, pagina: number) {
  return useQuery({
    queryKey: ['auditoria', filtros, pagina],
    queryFn: async () => {
      const { data } = await apiClient.get<AuditPagina>('/auditoria', {
        params: paramsDe(filtros, pagina),
      })
      return data
    },
  })
}

export function useAuditoriaOpcoes() {
  return useQuery({
    queryKey: ['auditoria-opcoes'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AuditOpcoes }>('/auditoria/opcoes')
      return data.data
    },
  })
}

/**
 * Baixa o CSV do recorte filtrado.
 *
 * Passa pelo apiClient, e não por um link direto, porque a rota exige o token
 * do Sanctum no cabeçalho — um `<a href>` sairia sem ele e voltaria 401.
 */
export async function exportarAuditoria(filtros: AuditFiltros) {
  const resposta = await apiClient.get('/auditoria/exportar', {
    params: paramsDe(filtros, 1),
    responseType: 'blob',
  })

  const url = URL.createObjectURL(new Blob([resposta.data], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = `auditoria-${new Date().toISOString().slice(0, 10)}.csv`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

export { getHttpErrorMessage }
export type { AuditFiltros, AuditItem }

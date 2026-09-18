import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { paramsDaConsulta, type FiltrosRelatorio } from './filtros'
import type { Aba, Relatorio } from './tipos'

/**
 * Cinco minutos de `staleTime`, o mesmo TTL do cache da API.
 *
 * Alinhar os dois evita o pior dos mundos: um `staleTime` menor faria o
 * navegador refazer a requisição para receber de volta a MESMA resposta do
 * cache do servidor — tráfego e espera sem número novo.
 */
const CINCO_MINUTOS = 5 * 60 * 1000

export function chaveDoRelatorio(aba: Aba, filtros: FiltrosRelatorio) {
  return ['relatorios', aba, paramsDaConsulta(filtros)] as const
}

export function useRelatorio(aba: Aba | null, filtros: FiltrosRelatorio) {
  return useQuery({
    queryKey: aba ? chaveDoRelatorio(aba, filtros) : ['relatorios', 'sem-aba'],
    // Só busca a aba aberta. As outras três continuam sem requisição até
    // alguém clicar nelas — quatro agregações de uma vez seria o jeito mais
    // rápido de esta tela ficar pesada.
    enabled: aba !== null,
    staleTime: CINCO_MINUTOS,
    queryFn: async () => {
      const resposta = await apiClient.get<{ data: Relatorio }>(`/relatorios/${aba}`, {
        params: paramsDaConsulta(filtros),
      })

      return resposta.data.data
    },
  })
}

/**
 * Baixa uma tabela do relatório.
 *
 * Fora do TanStack Query de propósito: isto não é estado da tela, é um efeito —
 * o arquivo sai do navegador e não há nada para guardar em cache. Passa pelo
 * `apiClient` para herdar o token e o tratamento de 401.
 */
export async function baixarTabela(
  aba: Aba,
  tabela: string,
  formato: 'csv' | 'xlsx',
  filtros: FiltrosRelatorio,
): Promise<void> {
  const resposta = await apiClient.get(`/relatorios/${aba}/export`, {
    params: { ...paramsDaConsulta(filtros), tabela, formato },
    responseType: 'blob',
  })

  // O nome vem do `Content-Disposition` que a API monta; o fallback existe
  // porque o cabeçalho some quando a resposta atravessa um proxy que o remove.
  const disposicao = String(resposta.headers['content-disposition'] ?? '')
  const nome =
    disposicao.match(/filename="?([^";]+)"?/)?.[1] ??
    `relatorio-${aba}-${tabela}-${filtros.de}-${filtros.ate}.${formato}`

  const url = URL.createObjectURL(resposta.data as Blob)
  const link = document.createElement('a')

  link.href = url
  link.download = nome
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

/**
 * Formatação das datas da listagem de solicitações.
 *
 * Fica fora do componente porque o arquivo de componente que também exporta
 * função perde o fast refresh do Vite — é o aviso que o oxlint dá em
 * `react(only-export-components)`.
 */

export function formatarData(iso: string | null): string {
  if (!iso) return '—'

  // `solicitado_em` vem como data pura (AAAA-MM-DD); sem a hora fixa, o
  // construtor a interpreta como UTC e o fuso do Brasil a joga um dia para trás.
  const valor = iso.length === 10 ? `${iso}T00:00:00` : iso

  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(new Date(valor))
}

export function formatarDataHora(iso: string | null): string {
  if (!iso) return '—'

  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(
    new Date(iso),
  )
}

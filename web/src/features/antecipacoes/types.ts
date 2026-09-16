export type AntecipacaoStatus = 'gerada' | 'ignorada'

/** Item escolhido pra levar pro próximo ciclo — par especialidade+profissional. */
export type AntecipacaoItemSelecionado = {
  especialidade_id: number
  profissional_id: number
  /** Preenchidos pela API depois de gerado (ver AntecipacaoService::criar). */
  item_gerado_id?: number
  guia_gerada_id?: number | null
}

/**
 * Item gerado com a guia resolvida NO MOMENTO DA CONSULTA.
 *
 * Diferente do `guia_gerada_id` de `itens_selecionados`, que é o retrato de
 * quando se gerou: em convênio automatizado a guia chega depois do item, então
 * aquele campo nasce nulo e nunca mais muda. Aqui `guia` vem nula só enquanto a
 * operadora não respondeu de fato.
 */
export type AntecipacaoItemGerado = {
  especialidade: string | null
  item_gerado_id: number | null
  guia: { id: number; numero: string | null; status: string } | null
}

export type Antecipacao = {
  id: number
  status: AntecipacaoStatus
  data_alvo: string | null
  itens_selecionados: AntecipacaoItemSelecionado[] | null
  itens_gerados?: AntecipacaoItemGerado[]
  observacoes: string | null
  gerado_em: string | null
  ignorado_em: string | null
  created_at: string | null
  solicitacao_origem?: {
    id: number
    paciente: { id: number; nome: string } | null
    convenio: { id: number; nome: string } | null
  }
  criado_por?: { id: number; nome: string } | null
}

/**
 * Filtros do histórico. Todos `string` porque vivem na query string da URL
 * (ver `useListaNaUrl`), que é o que faz a busca sobreviver a abrir uma guia
 * e voltar.
 *
 * `data_de`/`data_ate` são sobre a data da AÇÃO (quando se gerou ou ignorou),
 * não sobre a data prevista da antecipação — ver ListarAntecipacoesRequest.
 */
export type AntecipacaoFilters = {
  status: '' | AntecipacaoStatus
  paciente_nome: string
  numero_guia: string
  convenio_id: string
  data_de: string
  data_ate: string
}

/** Uma linha da fila "Elegíveis" — GET /antecipacoes/elegiveis, agrupada por solicitação. */
export type AntecipacaoElegivel = {
  solicitacao_id: number
  data_alvo: string | null
  paciente: { id: number; nome: string } | null
  convenio: { id: number; nome: string } | null
  guias: Array<{
    guia_id: number
    numero_guia: string | null
    status: string
    especialidade_id: number
    especialidade_nome: string | null
    profissional_id: number
    profissional_nome: string | null
  }>
}

/** Gera de fato: cria item(ns) novo(s) por renovação na MESMA solicitação de origem. */
export type CriarAntecipacaoPayload = {
  solicitacao_origem_id: number
  itens_selecionados: Array<{ especialidade_id: number; profissional_id: number }>
  data_alvo?: string | null
  observacoes?: string | null
}

export type IgnorarAntecipacaoPayload = {
  solicitacao_origem_id: number
  data_alvo?: string | null
  observacoes?: string | null
}

export type PaginatedResponse<T> = {
  data: T[]
  links?: unknown
  meta?: {
    current_page: number
    from: number | null
    last_page: number
    links: Array<{
      url: string | null
      label: string
      active: boolean
    }>
    path: string
    per_page: number
    to: number | null
    total: number
  }
}

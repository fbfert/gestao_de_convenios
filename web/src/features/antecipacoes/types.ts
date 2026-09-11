export type AntecipacaoStatus = 'pendente' | 'gerada' | 'ignorada'

/** Item escolhido pra levar pro próximo ciclo — par especialidade+profissional. */
export type AntecipacaoItemSelecionado = {
  especialidade_id: number
  profissional_id: number
}

export type Antecipacao = {
  id: number
  status: AntecipacaoStatus
  data_alvo: string | null
  itens_selecionados: AntecipacaoItemSelecionado[] | null
  observacoes: string | null
  gerado_em: string | null
  ignorado_em: string | null
  created_at: string | null
  solicitacao_origem?: {
    id: number
    paciente: { id: number; nome: string } | null
    convenio: { id: number; nome: string } | null
  }
  solicitacao_gerada?: { id: number } | null
  criado_por?: { id: number; nome: string } | null
}

export type AntecipacaoFilters = {
  status: '' | AntecipacaoStatus
}

/** Uma linha da fila "Elegíveis" — GET /antecipacoes/elegiveis, agrupada por solicitação. */
export type AntecipacaoElegivel = {
  solicitacao_id: number
  data_alvo: string | null
  paciente: { id: number; nome: string } | null
  convenio: { id: number; nome: string } | null
  medico: { id: number; nome: string; crm: string; crm_uf: string | null } | null
  cid_ids: number[]
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

export type CriarAntecipacaoPayload = {
  solicitacao_origem_id: number
  itens_selecionados: AntecipacaoItemSelecionado[]
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

export type Lancamento = {
  id: number
  antecipacao_id: number
  profissional_id: number
  data_sessao: string
  status: string
  observacoes: string | null
}

export type PaginatedResponse<T> = {
  data: T[]
  meta?: {
    last_page: number
  }
}

export type LancamentoFilters = {
  profissional_id: string
  data_sessao: string
}

export type LancamentoForm = {
  antecipacao_id: string
  profissional_id: string
  data_sessao: string
}

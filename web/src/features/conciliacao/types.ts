export type Conciliacao = {
  id: number
  guia_id: number
  profissional_id: number
  especialidade_id: number
  convenio_id: number
  quantidade: number
  valor_unitario: string
  valor_total: string
  status: string
  conferido_em: string | null
}

export type PaginatedResponse<T> = {
  data: T[]
  meta?: {
    current_page: number
    last_page: number
    total: number
  }
}

export type ConciliacaoFilters = {
  convenio_id: string
  especialidade_id: string
  profissional_id: string
  status: string
}


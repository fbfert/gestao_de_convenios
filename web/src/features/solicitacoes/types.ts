export type Solicitacao = {
  id: number
  paciente_id: number
  profissional_id: number
  especialidade_id: number
  convenio_id: number
  medico_solicitante: string
  status: string
  solicitado_em: string
  observacoes: string | null
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

export type SolicitacaoFilters = {
  status: string
  convenio_id: string
}

export type SolicitacaoForm = {
  paciente_id: string
  profissional_id: string
  especialidade_id: string
  convenio_id: string
  medico_solicitante: string
  solicitado_em: string
  observacoes: string
}

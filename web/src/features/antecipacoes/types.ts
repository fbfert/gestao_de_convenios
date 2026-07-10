export type Antecipacao = {
  id: number
  guia_id: number
  paciente_id: number
  convenio_id: number
  ciclo_inicio: string
  ciclo_fim: string
  qtd_autorizada: number
  qtd_utilizada: number
  status: string
}

export type PaginatedResponse<T> = {
  data: T[]
  meta?: {
    last_page: number
  }
}

export type AntecipacaoFilters = {
  status: string
  paciente_id: string
  convenio_id: string
}

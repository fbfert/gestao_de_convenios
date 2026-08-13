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
  /** Nomes, para o seletor não mostrar id cru. */
  paciente?: { id: number; nome: string } | null
  convenio?: { id: number; nome: string } | null
  /** Vem da guia: é ela que amarra a antecipação a uma terapia. */
  especialidade?: { id: number; nome: string } | null
  lancamentos?: Array<{
    id: number
    data_sessao: string | null
    hora_inicio: string | null
    hora_fim: string | null
    status: string
  }>
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

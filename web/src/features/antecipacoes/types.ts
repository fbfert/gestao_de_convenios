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
  paciente?: { id: number; nome: string; carteirinha?: string | null } | null
  convenio?: { id: number; nome: string } | null
  /** Vem da guia: é ela que amarra a antecipação a uma terapia. */
  especialidade?: { id: number; nome: string } | null
  /** Numero da guia e tipo de terapia, para o modelo de impressão preenchido. */
  guia?: { numero_guia: string | null; tipo_terapia: string | null } | null
  lancamentos?: Array<{
    id: number
    data_sessao: string | null
    hora_inicio: string | null
    hora_fim: string | null
    acompanhante: string | null
    resumo_atividades: string | null
    status: string
    profissional?: { id: number; nome: string } | null
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

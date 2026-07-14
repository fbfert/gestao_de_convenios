export type Guia = {
  id: number
  solicitacao_id: number | null
  convenio_id: number
  paciente_id: number
  profissional_id: number
  especialidade_id: number
  numero_guia: string
  tipo_terapia: string
  status: string
  data_solicitacao: string
  data_finalizacao: string | null
  senha: string | null
  validade_senha: string | null
  observacoes: string | null
  paciente?: GuiaPaciente
  convenio?: GuiaReferencia
  profissional?: GuiaReferencia
  especialidade?: GuiaReferencia
  antecipacoes?: GuiaAntecipacao[]
  conciliacoes?: GuiaConciliacao[]
}

export type GuiaReferencia = {
  id: number
  nome: string
}

export type GuiaPaciente = GuiaReferencia & {
  carteirinha: string
}

export type GuiaAntecipacao = {
  id: number
  qtd_autorizada: number
  qtd_utilizada: number
  status: string
}

export type GuiaConciliacao = {
  id: number
  status: string
}

export type PaginatedResponse<T> = {
  data: T[]
  meta?: {
    current_page: number
    last_page: number
    total: number
  }
}

export type GuiaFilters = {
  status: string
  convenio_id: string
  paciente_id: string
  validade_senha_vencendo_em_dias: string
}

export type GuiaForm = {
  solicitacao_id: string
  convenio_id: string
  paciente_id: string
  profissional_id: string
  especialidade_id: string
  numero_guia: string
  tipo_terapia: string
  data_solicitacao: string
}

export type GuiaFinalizarForm = {
  senha: string
  validade_senha?: string
}

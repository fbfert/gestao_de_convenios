export type Medico = {
  id: number
  nome: string
  crm: string
  especialidade_medica: string
  telefone: string
  email: string | null
  ativo: boolean
}

export type MedicoForm = {
  nome: string
  crm: string
  especialidade_medica: string
  telefone: string
  email: string
  ativo: boolean
}

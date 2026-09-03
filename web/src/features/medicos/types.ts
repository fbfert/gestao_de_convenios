export type Medico = {
  id: number
  nome: string
  crm: string
  crm_uf: string | null
  especialidade_medica: string
  telefone: string
  email: string | null
  ativo: boolean
}

export type MedicoForm = {
  nome: string
  crm: string
  crm_uf: string
  especialidade_medica: string
  telefone: string
  email: string
  ativo: boolean
}

export type Paciente = {
  id: number
  nome: string
  cpf: string | null
  carteirinha: string
  convenio_id: number
  telefone: string | null
  clinica_agil_id: string | null
  ativo: boolean
  convenio?: {
    id: number
    nome: string
  }
}

export type PacienteForm = {
  nome: string
  cpf: string
  carteirinha: string
  convenio_id: string
  telefone: string
  clinica_agil_id: string
  ativo: boolean
}

export type Profissional = {
  id: number
  nome: string
  especialidade_id: number
  conselho_registro: string | null
  percentual_repasse: string | null
  ativo: boolean
  especialidade?: {
    id: number
    nome: string
  }
}

export type ProfissionalForm = {
  nome: string
  especialidade_id: string
  conselho_registro: string
  percentual_repasse: string
  ativo: boolean
}

export type ProfissionalPayload = {
  nome: string
  especialidade_id: number
  conselho_registro: string | null
  percentual_repasse: number | null
  ativo: boolean
}

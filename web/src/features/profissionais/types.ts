export type Profissional = {
  id: number
  nome: string
  /** Especialidade principal. */
  especialidade_id: number
  /** Todas em que atende, principal incluída. */
  especialidade_ids?: number[]
  conselho_registro: string | null
  percentual_repasse: string | null
  ativo: boolean
  especialidade?: {
    id: number
    nome: string
  }
  especialidades?: {
    id: number
    nome: string
  }[]
}

export type ProfissionalForm = {
  nome: string
  especialidade_id: string
  especialidade_ids: number[]
  conselho_registro: string
  percentual_repasse: string
  ativo: boolean
}

export type ProfissionalPayload = {
  nome: string
  especialidade_id: number
  especialidade_ids: number[]
  conselho_registro: string | null
  percentual_repasse: number | null
  ativo: boolean
}

export type CodigoPorConvenio = {
  convenio_id: number
  codigo: string
}

export type Especialidade = {
  id: number
  nome: string
  ativo: boolean
  /** Um código por convênio; vem só quando a listagem pede `com_codigos`. */
  codigos?: CodigoPorConvenio[]
}

export type EspecialidadeForm = {
  nome: string
  ativo: boolean
  /** Chaveado por convênio para o formulário não depender da ordem da lista. */
  codigos: Record<number, string>
}

export type EspecialidadePayload = {
  nome: string
  ativo: boolean
  codigos: CodigoPorConvenio[]
}

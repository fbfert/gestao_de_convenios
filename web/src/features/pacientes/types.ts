export type TelefoneForm = {
  id?: number
  /** Só dígitos; a máscara é aplicada na exibição. */
  numero: string
  rotulo: string
  contato_nome: string
  principal: boolean
}

export type Paciente = {
  id: number
  nome: string
  cpf: string | null
  data_nascimento: string | null
  carteirinha: string
  validade_carteirinha: string | null
  carteirinha_vencida: boolean
  convenio_id: number
  /** Coluna antiga; a fonte agora é `telefones`. */
  telefone: string | null
  telefones?: TelefoneForm[]
  clinica_agil_id: string | null
  ativo: boolean
  convenio?: {
    id: number
    nome: string
    connector_driver?: 'unimed_rda' | null
    carteirinha_blocos?: number[] | null
  }
}

export type PacienteForm = {
  nome: string
  cpf: string
  data_nascimento: string
  carteirinha: string
  validade_carteirinha: string
  convenio_id: string
  telefones: TelefoneForm[]
  ativo: boolean
  /** Imagem lida pela IA, adotada pelo paciente na gravação. */
  carteirinha_documento_id?: number | null
}

export type LeituraCarteirinha = {
  documento_id: number
  expira_em: string
  model: string
  dados: {
    carteirinha: string | null
    nome: string | null
    cpf: string | null
    data_nascimento: string | null
    validade_carteirinha: string | null
    observacoes: string | null
  }
  convenio: {
    lido: string | null
    id: number | null
    nome: string | null
  }
}

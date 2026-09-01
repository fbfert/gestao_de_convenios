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

export type PacienteImportLinhaDados = {
  linha: number
  nome: string
  cpf: string | null
  carteirinha: string
  convenio: string
  convenio_id: number | null
  data_nascimento: string | null
  validade_carteirinha: string | null
  telefone: string | null
  ativo: boolean
}

export type PacienteImportLinha = {
  id: number
  linha: number
  status: 'valida' | 'erro' | 'importado' | 'atualizado' | 'ignorado'
  matched_paciente_id: number | null
  dados: PacienteImportLinhaDados
  erros: Record<string, string>
}

export type PacienteImportLote = {
  id: number
  arquivo_nome_original: string
  status: 'previsualizado' | 'confirmado'
  confirmado_em: string | null
  total_linhas: number
  total_validas: number
  total_invalidas: number
  total_importados: number
  total_atualizados: number
  total_ignorados: number
}

export type PacienteImportPreview = {
  lote: PacienteImportLote
  linhas: PacienteImportLinha[]
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
    /** Nota do casamento aceito, em porcento. */
    similaridade: number | null
    /** Os mais próximos, com a nota de cada um. */
    candidatos: { id: number; nome: string; similaridade: number }[]
  }
}

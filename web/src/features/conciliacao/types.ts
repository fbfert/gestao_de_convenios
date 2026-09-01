export type Conciliacao = {
  id: number
  guia_id: number
  profissional_id: number
  especialidade_id: number
  convenio_id: number
  quantidade: number
  valor_unitario: string
  valor_total: string
  percentual_repasse_profissional: string
  percentual_retencao_clinica: string
  valor_repasse_unitario: string
  valor_repasse_total: string
  valor_retencao_unitario: string
  valor_retencao_total: string
  entrada_total: string
  saida_total: string
  saldo_total: string
  profissional_informado?: {
    id: number | null
    nome: string | null
  } | null
  movimentos_financeiros?: Array<{
    id: number
    tipo: 'entrada' | 'saida'
    origem: 'analitico' | 'repasse'
    quantidade: number
    valor_unitario: string | null
    valor_total: string
    referencia_analitico_convenio: string | null
    descricao: string | null
    profissional_informado: {
      id: number | null
      nome: string | null
    } | null
    profissional_executor: {
      id: number | null
      nome: string | null
    } | null
    created_at: string | null
    updated_at: string | null
  }>
  status: string
  conferido_em: string | null
}

export type PaginatedResponse<T> = {
  data: T[]
  meta?: {
    current_page: number
    last_page: number
    total: number
  }
}

export type ConciliacaoFilters = {
  convenio_id: string
  especialidade_id: string
  profissional_id: string
  status: string
}

export type ConciliacaoImportLinhaDados = {
  linha: number
  numero_guia: string
  convenio: string
  convenio_id: number | null
  guia_id: number | null
  profissional: string
  profissional_id: number | null
  quantidade: number | null
  valor_unitario: number | null
  valor_total: number | null
  referencia_analitico_convenio: string | null
  status: 'pending' | 'reviewed' | 'paid' | null
  conferido_em: string | null
}

export type ConciliacaoImportLinha = {
  id: number
  linha: number
  status: 'valida' | 'erro' | 'importado' | 'atualizado' | 'ignorado'
  matched_conciliacao_id: number | null
  dados: ConciliacaoImportLinhaDados
  erros: Record<string, string>
}

export type ConciliacaoImportLote = {
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

export type ConciliacaoImportPreview = {
  lote: ConciliacaoImportLote
  linhas: ConciliacaoImportLinha[]
}

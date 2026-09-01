export type Antecipacao = {
  id: number
  guia_id: number
  paciente_id: number
  convenio_id: number
  ciclo_inicio: string
  ciclo_fim: string
  qtd_autorizada: number
  qtd_utilizada: number
  status: string
  /** Nomes, para o seletor não mostrar id cru. */
  paciente?: { id: number; nome: string; carteirinha?: string | null } | null
  convenio?: { id: number; nome: string } | null
  /** Vem da guia: é ela que amarra a antecipação a uma terapia. */
  especialidade?: { id: number; nome: string } | null
  /** Numero da guia e tipo de terapia, para o modelo de impressão preenchido. */
  guia?: { numero_guia: string | null; tipo_terapia: string | null } | null
  lancamentos?: Array<{
    id: number
    data_sessao: string | null
    hora_inicio: string | null
    hora_fim: string | null
    acompanhante: string | null
    resumo_atividades: string | null
    status: string
    profissional?: { id: number; nome: string } | null
  }>
}

export type PaginatedResponse<T> = {
  data: T[]
  meta?: {
    last_page: number
  }
}

export type AntecipacaoFilters = {
  status: string
  paciente_id: string
  convenio_id: string
}

export type AntecipacaoImportLinhaDados = {
  linha: number
  numero_guia: string
  convenio: string
  convenio_id: number | null
  guia_id: number | null
  paciente_id: number | null
  ciclo_inicio: string | null
  ciclo_fim: string | null
  qtd_autorizada: number | null
  qtd_utilizada: number
  status: 'open' | 'closed' | null
}

export type AntecipacaoImportLinha = {
  id: number
  linha: number
  status: 'valida' | 'erro' | 'importado' | 'atualizado' | 'ignorado'
  matched_antecipacao_id: number | null
  dados: AntecipacaoImportLinhaDados
  erros: Record<string, string>
}

export type AntecipacaoImportLote = {
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

export type AntecipacaoImportPreview = {
  lote: AntecipacaoImportLote
  linhas: AntecipacaoImportLinha[]
}

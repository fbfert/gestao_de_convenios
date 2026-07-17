export type Lancamento = {
  id: number
  antecipacao_id: number
  profissional_id: number
  data_sessao: string
  hora_inicio: string | null
  hora_fim: string | null
  acompanhante: string | null
  resumo_atividades: string | null
  transcricao_bruta: string | null
  status: string
  observacoes: string | null
  antecipacao?: {
    id: number
    guia_id: number
  }
  profissional?: {
    id: number
    nome: string
  }
}

export type PaginatedResponse<T> = {
  data: T[]
  meta?: {
    last_page: number
  }
}

export type LancamentoFilters = {
  profissional_id: string
  data_sessao: string
}

export type LancamentoForm = {
  antecipacao_id: string
  profissional_id: string
  data_sessao: string
  hora_inicio: string
  hora_fim: string
  acompanhante: string
  resumo_atividades: string
  observacoes: string
}

export type LancamentoImportForm = {
  antecipacao_id: string
  profissional_id: string
  transcricao: string
}

export type LancamentoConfirmImportForm = LancamentoImportForm & {
  sessoes: LancamentoTranscricaoSessao[]
  pdf_registro_sessoes: File | null
}

export type LancamentoTranscricaoSessao = {
  data_sessao: string | null
  hora_inicio: string | null
  hora_fim: string | null
  acompanhante: string | null
  resumo_atividades: string | null
}

export type LancamentoTranscricaoPreview = {
  confirmacao_pendente?: boolean
  cabecalho: {
    guia_numero: string | null
    clinica: string | null
    paciente: string | null
    numero_cartao: string | null
    profissional_executante: string | null
    terapia_aplicada: string | null
  }
  sessoes: LancamentoTranscricaoSessao[]
}

export type LancamentoTranscricaoImportResult = LancamentoTranscricaoPreview & {
  registros: Lancamento[]
}

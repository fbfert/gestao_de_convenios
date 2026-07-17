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

export type AnaliticoUnimedLinha = {
  linha: string
  numero_guia_operadora: string | null
  numero_guia_prestador: string | null
  codigo: string | null
  usuario: string | null
  data_autorizacao: string | null
  data_realizacao: string | null
  procedimento: string | null
  tabela: string | null
  descricao_procedimento: string | null
  qtd: string | null
  filme: string | null
  custo: string | null
  hono: string | null
  valor: string | null
  local_realizacao: string | null
}

export type AnaliticoUnimedGlosaLinha = {
  linha: string
  numero_guia_operadora: string | null
  numero_guia_prestador: string | null
  codigo: string | null
  usuario: string | null
  data_autorizacao: string | null
  data_realizacao: string | null
  procedimento: string | null
  tabela: string | null
  descricao_procedimento: string | null
  qtd: string | null
  tipo: string | null
  motivo: string | null
  valor: string | null
  local_realizacao: string | null
}

export type AnaliticoUnimedConciliacaoLinha = {
  linha: string | null
  origem: 'analitico' | 'glosa'
  natureza: 'pago' | 'glosado'
  processavel: boolean
  numero_guia_operadora: string | null
  numero_guia_prestador: string | null
  codigo: string | null
  usuario: string | null
  data_autorizacao: string | null
  data_realizacao: string | null
  procedimento: string | null
  descricao_procedimento: string | null
  qtd: string | null
  qtd_normalizada: number
  tipo: string | null
  motivo: string | null
  valor: string | null
  valor_normalizado: string | null
  local_realizacao: string | null
  chave_conciliacao: string
}

export type AnaliticoUnimedConciliacaoResumoPorGuia = {
  numero_guia_operadora: string
  linhas_analitico: number
  linhas_glosa: number
  qtd_paga: number
  qtd_glosada: number
  valor_pago: string
  valor_glosado: string
}

export type AnaliticoUnimedConciliacaoPreview = {
  linhas: AnaliticoUnimedConciliacaoLinha[]
  resumo_por_guia: AnaliticoUnimedConciliacaoResumoPorGuia[]
  totais: {
    pago: string
    glosado: string
    saldo: string
  }
}

export type AnaliticoUnimedPreview = {
  arquivo: string
  planilhas: Array<{ nome: string; linhas: number }>
  analitico: {
    cabecalho: {
      unimed_executante: {
        raw: string | null
        codigo: string | null
        nome: string | null
        cnpj: string | null
      }
      prestador_executante: {
        raw: string | null
        codigo: string | null
        nome: string | null
        cnpj: string | null
      }
    }
    linhas: AnaliticoUnimedLinha[]
    totais: {
      prestador: string | null
      lote: string | null
    }
  }
  glosas: {
    linhas: AnaliticoUnimedGlosaLinha[]
    total: {
      valor: string | null
    }
  }
  conciliacao: AnaliticoUnimedConciliacaoPreview
}

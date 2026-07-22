import type { Guia } from '../guias/types'

export type SolicitacaoStatus = 'under_review' | 'approved' | 'denied'

export type Solicitacao = {
  id: number
  paciente_id: number
  profissional_id: number
  especialidade_id: number
  convenio_id: number
  medico_id: number
  medico?: {
    id: number
    nome: string
    crm: string
    especialidade_medica: string
  }
  paciente?: {
    id: number
    nome: string
    carteirinha: string
  }
  status: string
  solicitado_em: string
  observacoes: string | null
  pedido_medico?: {
    nome_original: string | null
    mime: string | null
    url: string
  } | null
  guia?: Guia | null
}

export type PaginatedResponse<T> = {
  data: T[]
  links?: unknown
  meta?: {
    current_page: number
    from: number | null
    last_page: number
    links: Array<{
      url: string | null
      label: string
      active: boolean
    }>
    path: string
    per_page: number
    to: number | null
    total: number
  }
}

export type SolicitacaoFilters = {
  status: string
  convenio_id: string
}

export type SolicitacaoForm = {
  paciente_id: string
  profissional_id: string
  especialidade_id: string
  convenio_id: string
  medico_id: string
  solicitado_em: string
  observacoes: string
  pedido_medico_upload_id?: string
  pedido_medico_nome_original?: string
  pedido_medico_mime?: string
  pedido_medico_ai_result?: PedidoMedicoAiResult
}

export type PedidoMedicoSuggestion = {
  id: number
  nome: string
  similaridade: number
  carteirinha?: string
  crm?: string
}

export type PedidoMedicoAiDados = {
  paciente_nome?: string | null
  medico_nome?: string | null
  especialidade_nome?: string | null
  solicitado_em?: string | null
  observacoes?: string | null
}

export type PedidoMedicoAiResult = {
  upload_id: string
  arquivo: {
    nome_original: string
    mime: string
  }
  model: string
  raw_text: string
  dados: PedidoMedicoAiDados
  sugestoes: {
    pacientes: PedidoMedicoSuggestion[]
    medicos: PedidoMedicoSuggestion[]
    especialidades: PedidoMedicoSuggestion[]
  }
}

export type AuditPayload = {
  antes?: Record<string, unknown>
  depois?: Record<string, unknown>
  /** Campos sensíveis: a trilha registra que mudaram, nunca o valor. */
  campos_ocultos?: string[]
  [chave: string]: unknown
}

export type AuditItem = {
  id: number
  acao: string
  /** Rótulo legível, vindo da API. */
  acao_label: string
  tipo: string
  entidade: string
  entidade_label: string
  entidade_id: number
  usuario: string | null
  usuario_id: number | null
  payload: AuditPayload | null
  /** Só eventos de acesso trazem origem. */
  ip: string | null
  user_agent: string | null
  created_at: string | null
}

export type AuditPagina = {
  data: AuditItem[]
  meta?: {
    current_page: number
    last_page: number
    total: number
  }
}

export type AuditOpcao = { valor: string; rotulo: string }

export type AuditOpcoes = {
  entidades: AuditOpcao[]
  /** Ação carrega o tipo, para o seletor de ação seguir o de tipo. */
  acoes: (AuditOpcao & { tipo: string })[]
  tipos: AuditOpcao[]
}

export type AuditFiltros = {
  de: string
  ate: string
  /** Nome da pessoa, busca parcial. */
  usuario: string
  /** '' todos, 'pessoas' ou 'sistema'. */
  autor: string
  tipo: string
  entidade: string
  acao: string
}

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
  entidade: string
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

export type AuditOpcoes = {
  entidades: string[]
  acoes: string[]
  /** Autores que aparecem na trilha, vindos dela mesma. */
  usuarios: { id: number; nome: string }[]
}

export type AuditFiltros = {
  de: string
  ate: string
  usuario_id: string
  entidade: string
  acao: string
}

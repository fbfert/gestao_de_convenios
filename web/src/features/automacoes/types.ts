export type AutomacaoExecucao = {
  id: number
  operacao: string
  status: string
  /**
   * Falso quando a execução mais recente da mesma guia já teve sucesso — a
   * guia se recuperou e esta falha antiga vira ruído histórico, não algo
   * pendente de ação. Use este campo (não `status` diretamente) para decidir
   * o que conta como "Atenção".
   */
  precisa_atencao: boolean
  solicitacao_item_id: number | null
  guia_id: number | null
  parent_id: number | null
  erro_codigo: string | null
  erro_mensagem: string | null
  payload: Record<string, unknown> | null
  resultado: Record<string, unknown> | null
  queued_at: string | null
  started_at: string | null
  finished_at: string | null
  created_at: string | null
  guia?: {
    id: number
    numero_guia: string
    status: string
  } | null
  solicitacao_item?: {
    id: number
    status_operacional: string
    quantidade: number
  } | null
  eventos?: AutomacaoEvento[]
}

export type AutomacaoEvento = {
  id: number
  tipo: string
  status: string | null
  payload: Record<string, unknown> | null
  evidencias: Record<string, unknown> | null
  registrado_em: string | null
}

export type AutomacaoFilters = {
  status: string
  operacao: string
  needs_attention: string
  numero_guia: string
}

export type PaginatedResponse<T> = {
  data: T[]
  meta?: {
    current_page: number
    last_page: number
    total: number
  }
}

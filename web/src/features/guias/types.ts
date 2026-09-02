export type Guia = {
  id: number
  solicitacao_id: number | null
  solicitacao_item_id: number | null
  automacao_execucao_id: number | null
  convenio_id: number
  paciente_id: number
  profissional_id: number
  especialidade_id: number
  numero_guia: string | null
  tipo_terapia: string
  status: string
  unimed_status: string | null
  unimed_last_checked_at: string | null
  unimed_next_check_at: string | null
  sessoes_solicitadas: number | null
  sessoes_autorizadas: number | null
  protocolo_operadora: string | null
  data_solicitacao: string
  data_finalizacao: string | null
  senha: string | null
  validade_senha: string | null
  observacoes: string | null
  alerta_negacao_ocultado_em: string | null
  paciente?: GuiaPaciente
  convenio?: GuiaReferencia
  profissional?: GuiaReferencia
  especialidade?: GuiaReferencia
  solicitacao_item?: GuiaSolicitacaoItem | null
  automacao_execucao?: GuiaAutomacaoExecucao | null
  ultima_automacao_unimed?: GuiaAutomacaoExecucao | null
  antecipacoes?: GuiaAntecipacao[]
  conciliacoes?: GuiaConciliacao[]
  created_at: string
  updated_at: string
}

export type GuiaReferencia = {
  id: number
  nome: string
  connector_driver?: string | null
}

export type GuiaPaciente = GuiaReferencia & {
  carteirinha: string
}

export type GuiaSolicitacaoItem = {
  id: number
  especialidade_id: number
  profissional_id: number
  quantidade: number
  status_operacional: string
  especialidade?: GuiaReferencia | null
  profissional?: GuiaReferencia | null
}

export type GuiaAutomacaoExecucao = {
  id: number
  operacao: string
  status: string
  erro_codigo: string | null
  erro_mensagem: string | null
  queued_at: string | null
  started_at: string | null
  finished_at: string | null
  eventos: Array<{
    id: number
    tipo: string
    status: string | null
    registrado_em: string | null
  }>
}

export type GuiaAntecipacao = {
  id: number
  qtd_autorizada: number
  qtd_utilizada: number
  status: string
}

export type GuiaConciliacao = {
  id: number
  status: string
}

export type PaginatedResponse<T> = {
  data: T[]
  meta?: {
    current_page: number
    last_page: number
    total: number
  }
}

export type GuiaFilters = {
  status: string
  convenio_id: string
  profissional_id: string
  paciente_nome: string
  validade_senha_vencendo_em_dias: string
  mostrar_a_definir: string
}

export type GuiaForm = {
  solicitacao_id: string
  convenio_id: string
  paciente_id: string
  profissional_id: string
  especialidade_id: string
  numero_guia: string
  tipo_terapia: string
  data_solicitacao: string
  sessoes_solicitadas?: string
  sessoes_autorizadas?: string
  protocolo_operadora?: string
}

export type GuiaFinalizarForm = {
  senha: string
  validade_senha?: string
}

export type GuiaImportLinhaDados = {
  linha: number
  numero_guia: string
  convenio: string
  convenio_id: number | null
  paciente_cpf: string | null
  paciente_carteirinha: string | null
  paciente_id: number | null
  profissional: string
  profissional_id: number | null
  especialidade: string
  especialidade_id: number | null
  tipo_terapia: string
  data_solicitacao: string | null
  status: string | null
  senha: string | null
  validade_senha: string | null
  data_finalizacao: string | null
  sessoes_solicitadas: number | null
  sessoes_autorizadas: number | null
  protocolo_operadora: string | null
  solicitacao_protocolo: string | null
  observacoes: string | null
}

export type GuiaImportLinha = {
  id: number
  linha: number
  status: 'valida' | 'erro' | 'importado' | 'atualizado' | 'ignorado'
  matched_guia_id: number | null
  dados: GuiaImportLinhaDados
  erros: Record<string, string>
}

export type GuiaImportLote = {
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

export type GuiaImportPreview = {
  lote: GuiaImportLote
  linhas: GuiaImportLinha[]
}

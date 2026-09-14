export type SolicitacaoStatus =
  | 'under_review'
  | 'ready_for_automation'
  | 'guia_gerada'
  | 'approved'
  | 'denied'
  | 'historico'

/**
 * Situações em que nenhum item vai para a operadora.
 *
 * Espelha `App\Support\SolicitacaoStatus::BLOQUEIAM_ENVIO`, que é a fonte de
 * verdade: a API recusa mesmo que esta lista deixe passar. Aqui só decide se o
 * botão nasce habilitado — interface não é controle de acesso.
 *
 * `approved` e `guia_gerada` ficam de fora de propósito. O gate é do ITEM, e
 * uma solicitação já aprovada pode receber item novo.
 */
export const STATUS_QUE_BLOQUEIAM_ENVIO: readonly SolicitacaoStatus[] = [
  'under_review',
  'denied',
  'historico',
]

/**
 * Situações em que não se acrescenta item.
 *
 * Espelha `App\Support\SolicitacaoStatus::BLOQUEIAM_ADICAO`. Deliberadamente
 * DIFERENTE da lista acima: `under_review` barra o envio mas permite
 * acrescentar — acrescentar antes da análise é o fluxo normal de quem está
 * montando o pedido. O que não se mexe é no que já foi encerrado.
 */
export const STATUS_QUE_BLOQUEIAM_ADICAO: readonly SolicitacaoStatus[] = ['denied', 'historico']

export type Solicitacao = {
  id: number
  paciente_id: number
  profissional_id: number
  especialidade_id: number
  convenio_id: number
  medico_id: number
  /** N-pra-N desde 25/08/2026 — uma solicitação pode citar mais de um CID. */
  cids?: Array<{
    id: number
    codigo: string
    descricao: string
  }>
  medico?: {
    id: number
    nome: string
    crm: string
    crm_uf: string | null
    especialidade_medica: string
  }
  paciente?: {
    id: number
    nome: string
    carteirinha: string
  }
  convenio?: {
    id: number
    nome: string
    connector_type: string | null
    connector_driver: 'unimed_rda' | null
  }
  status: string
  solicitado_em: string
  observacoes: string | null
  pedido_medico?: {
    nome_original: string | null
    mime: string | null
    url: string
  } | null
  itens?: SolicitacaoItem[]
  documentos?: SolicitacaoDocumento[]
  /**
   * Quando o registro foi CADASTRADO no sistema — diferente de `solicitado_em`,
   * que é a data do pedido médico. A diferença entre as duas é o que diz há
   * quanto tempo o pedido está parado dentro do sistema.
   */
  created_at?: string | null
  updated_at?: string | null
}

export type SolicitacaoItem = {
  id: number
  especialidade_id: number
  profissional_id: number
  quantidade: number
  status_operacional: string
  observacoes: string | null
  /**
   * A guia DESTE item.
   *
   * Única fonte de guia no payload. Havia um `Solicitacao.guia`, vindo de um
   * `hasOne` sem ordenação que devolvia uma guia arbitrária da solicitação;
   * saiu, porque desde a multi-especialidade cada item tem a sua.
   *
   * `numero_operadora` vem separado de `numero_guia` de propósito: o segundo
   * pode conter o valor de preenchimento do convênio manual, e a tela não deve
   * exibi-lo como se fosse o número que a operadora conhece. Quem decide isso é
   * o backend, uma vez — o front consome `numero_operadora` e pronto.
   */
  guia?: {
    id: number
    numero_guia: string | null
    numero_operadora: string | null
    status: string
  } | null
  /**
   * O item de ORIGEM da cadeia de renovação — nulo quando este é a origem.
   * A cadeia é plana: todo item aponta para o primeiro, nunca para o anterior.
   */
  renovacao_de_item_id?: number | null
  /** 1 para a origem, 2 para a primeira renovação, e assim por diante. */
  posicao_na_cadeia?: number
  total_na_cadeia?: number
  automacao_execucao_ativa?: {
    id: number
    operacao: string
    status: string
    queued_at: string | null
  } | null
  especialidade?: {
    id: number
    nome: string
    mapeamento_convenio?: {
      codigo_procedimento: string
      descricao_operadora: string | null
    } | null
  } | null
  profissional?: {
    id: number
    nome: string
  } | null
  documentos?: SolicitacaoDocumento[]
}

export type { DocumentoTipo as SolicitacaoDocumentoTipo } from '../../lib/documentoTipos'
export { DOCUMENTO_LABELS, DOCUMENTOS_DA_SOLICITACAO, DOCUMENTOS_POR_ITEM } from '../../lib/documentoTipos'
import type { DocumentoTipo } from '../../lib/documentoTipos'

export type SolicitacaoDocumento = {
  id: number
  solicitacao_item_id?: number | null
  tipo: DocumentoTipo | string
  nome_original: string
  mime: string | null
  url?: string
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
  id: string
  status: string
  convenio_id: string
  paciente: string
  profissional: string
  medico: string
  mostrar_historico: string
}

export type SolicitacaoForm = {
  paciente_id: string
  convenio_id: string
  medico_id: string
  cid_ids: string[]
  solicitado_em: string
  observacoes: string
  pedido_medico_upload_id?: string
  pedido_medico_nome_original?: string
  pedido_medico_mime?: string
  pedido_medico_ai_result?: PedidoMedicoAiResult
  itens: SolicitacaoFormItem[]
}

export type SolicitacaoFormItem = {
  especialidade_id: string
  profissional_id: string
  quantidade: string
  observacoes?: string
}

/** Corpo de `POST /solicitacoes/{id}/itens`. */
export type AdicionarItemForm = {
  especialidade_id: string
  profissional_id: string
  quantidade: string
  observacoes?: string
  renovacao_de_item_id?: number | null
}

/**
 * Dados dos avisos, vindos de `GET /solicitacoes/{id}/contexto-adicao`.
 *
 * Calculados no servidor de propósito: `sessoes_por_guia` é regra de convênio,
 * e regra de convênio reimplementada em TypeScript diverge do backend em seis
 * meses (`openspec/config.yaml`). Nenhum destes valores bloqueia o confirmar.
 */
export type ContextoAdicao = {
  /** Idade do pedido médico em dias; nulo quando não há data. */
  pedido_medico_dias: number | null
  /** Teto por guia da regra vigente; nulo = convênio sem a regra cadastrada. */
  sessoes_por_guia: number | null
  quantidade_padrao: number | null
  ja_existe_item_igual: boolean
  /** Soma da CADEIA de renovação, não do par especialidade+profissional. */
  sessoes_na_cadeia: number | null
}

export type PedidoMedicoSuggestion = {
  id: number
  nome: string
  similaridade: number
  carteirinha?: string
  crm?: string
  crm_uf?: string | null
}

export type PedidoMedicoAiDados = {
  paciente_nome?: string | null
  medico_nome?: string | null
  /** CRM (somente dígitos) do médico solicitante lido no documento, se identificável. */
  medico_crm?: string | null
  /** UF do CRM do médico solicitante lido no documento, se identificável. */
  medico_crm_uf?: string | null
  /** Especialidade médica do profissional solicitante (ex. "Pediatria") — não
   *  confundir com `especialidades`, que é a terapia pedida para o paciente. */
  medico_especialidade?: string | null
  /** Uma entrada por especialidade citada no pedido. */
  especialidades?: string[] | null
  /** Chave antiga, no singular. Mantida para leituras já gravadas. */
  especialidade_nome?: string | null
  /** Um item por CID citado no pedido, como "F84.0" ou "F84.0 - descrição". */
  cids?: string[] | null
  solicitado_em?: string | null
  observacoes?: string | null
}

/** Especialidade lida do documento e os cadastros parecidos com ela. */
export type PedidoMedicoEspecialidadeLida = {
  termo: string
  matches: PedidoMedicoSuggestion[]
  /** Nenhum cadastro parecido o bastante: a tela oferece criar o termo lido. */
  sugere_cadastro: boolean
}

export type PedidoMedicoCidSuggestion = {
  id: number
  codigo: string
  descricao: string
  similaridade: number
}

/** CID lido do documento e os cadastros do catálogo parecidos com ele. */
export type PedidoMedicoCidLido = {
  termo: string
  matches: PedidoMedicoCidSuggestion[]
  /** Nenhum cadastro parecido o bastante: a tela oferece criar o CID lido. */
  sugere_cadastro: boolean
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
    especialidades: PedidoMedicoEspecialidadeLida[]
    cids: PedidoMedicoCidLido[]
  }
}

export type SolicitacaoImportLinhaDados = {
  linha: number
  grupo: string
  protocolo: string | null
  paciente_cpf: string | null
  paciente_carteirinha: string | null
  paciente_id: number | null
  convenio: string
  convenio_id: number | null
  medico: string
  medico_id: number | null
  cids: string
  cid_ids: number[]
  solicitado_em: string | null
  status: 'under_review' | 'denied' | 'canceled' | 'expired' | null
  observacoes: string | null
  especialidade: string
  especialidade_id: number | null
  profissional: string
  profissional_id: number | null
  quantidade: number
  item_observacoes: string | null
  matched_solicitacao_id: number | null
}

export type SolicitacaoImportLinha = {
  id: number
  linha: number
  grupo: string
  status: 'valida' | 'erro' | 'importado' | 'atualizado' | 'ignorado'
  matched_solicitacao_id: number | null
  dados: SolicitacaoImportLinhaDados
  erros: Record<string, string>
}

export type SolicitacaoImportLote = {
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

export type SolicitacaoImportPreview = {
  lote: SolicitacaoImportLote
  linhas: SolicitacaoImportLinha[]
}

/**
 * Tipos de documento compartilhados entre a pasta do paciente e os anexos de
 * uma solicitação — o mesmo arquivo pode nascer solto na pasta ou ser
 * vinculado a uma solicitação, e em ambos os lugares é um destes tipos.
 */
export type DocumentoTipo =
  | 'pedido_medico'
  | 'laudo_medico'
  | 'plano_individualizado'
  | 'relatorio_evolucao'
  | 'registro_sessoes'

export const DOCUMENTO_LABELS: Record<DocumentoTipo, string> = {
  pedido_medico: 'Pedido Médico',
  laudo_medico: 'Laudo Médico',
  plano_individualizado: 'Plano Individualizado',
  relatorio_evolucao: 'Relatório de Evolução',
  registro_sessoes: 'Registro de Sessões',
}

/** Anexos que valem para a solicitação inteira. */
export const DOCUMENTOS_DA_SOLICITACAO: DocumentoTipo[] = ['pedido_medico', 'laudo_medico']

/** Anexos que existem por especialidade, ou seja, por item. */
export const DOCUMENTOS_POR_ITEM: DocumentoTipo[] = ['plano_individualizado', 'relatorio_evolucao']

/**
 * Folha de registro anexada ao confirmar o lançamento das sessões. Não
 * pertence à solicitação nem ao item — é o comprovante daquela remessa —,
 * por isso fica num grupo próprio.
 */
export const DOCUMENTOS_DA_SESSAO: DocumentoTipo[] = ['registro_sessoes']

export const TODOS_DOCUMENTOS: DocumentoTipo[] = [
  ...DOCUMENTOS_DA_SOLICITACAO,
  ...DOCUMENTOS_POR_ITEM,
  ...DOCUMENTOS_DA_SESSAO,
]

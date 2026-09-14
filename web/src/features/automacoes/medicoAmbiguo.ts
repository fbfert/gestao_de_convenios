import type { AutomacaoExecucao } from './types'

export type MedicoAmbiguoInfo = {
  medicoId: number
  nomeLido: string
  sugestaoPortal: string
  similaridade: number
}

/**
 * O worker nunca decide sozinho um médico que não bateu com confiança — só
 * devolve a melhor sugestão encontrada na Unimed (`buscarPrestadorPorNome`
 * em worker-unimed/src/operations/gerarGuia.js) pro operador confirmar ou
 * corrigir aqui.
 *
 * Compartilhado entre AutomacoesPage.tsx (tela de detalhe) e
 * AutomacaoProgressoModal.tsx (mesmo fluxo, acessível direto de Solicitações
 * e Guias sem precisar navegar até Automações).
 */
export function medicoAmbiguoInfo(execucao: AutomacaoExecucao): MedicoAmbiguoInfo | null {
  if (execucao.erro_codigo !== 'PRESTADOR_NOME_AMBIGUO') {
    return null
  }

  const resultado = execucao.resultado ?? {}
  const medicoPayload = (execucao.payload?.medico ?? null) as { id?: number; nome?: string } | null

  if (!medicoPayload?.id) {
    return null
  }

  return {
    medicoId: medicoPayload.id,
    nomeLido: String(resultado.medico_nome_lido ?? medicoPayload.nome ?? ''),
    sugestaoPortal: String(resultado.medico_sugestao_portal ?? ''),
    similaridade: Number(resultado.medico_similaridade ?? 0),
  }
}

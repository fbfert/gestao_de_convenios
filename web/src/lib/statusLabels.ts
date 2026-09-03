export type StatusEntidade =
  | 'solicitacoes'
  | 'guias'
  | 'antecipacoes'
  | 'lancamentos'
  | 'conciliacoes'

const statusLabels: Record<StatusEntidade, Record<string, string>> = {
  solicitacoes: {
    registered: 'Cadastrado',
    under_review: 'Análise Interna',
    ready_for_automation: 'Pronto para Automatização',
    guia_gerada: 'Guia Gerada',
    approved: 'Aprovado',
    canceled: 'Cancelado',
    denied: 'Negado',
    expired: 'Vencido',
    historico: 'Histórico',
  },
  guias: {
    registered: 'Cadastrado',
    under_review: 'Em análise',
    // 'approved' e 'finalized' eram o mesmo rótulo ("Aprovado") pra dois
    // estados diferentes — approved é a operadora ter autorizado (a
    // automação Unimed já captura senha/validade sozinha), finalized é
    // depois de alguém confirmar isso no gescon clicando Finalizar (abre o
    // ciclo de Antecipação). "Autorizado" usa a mesma palavra que a própria
    // Unimed usa no portal pra esse estado — ver worker-unimed/src/portal.js.
    // "Finalizado" nomeia o clique em si, pra não ficar igual a "Autorizado".
    approved: 'Autorizado',
    finalized: 'Finalizado',
    canceled: 'Cancelado',
    denied: 'Negado',
    needs_verification: 'Verificar Restrição',
    expired: 'Vencido',
    // Guia migrada de planilha antiga (nunca entra em automação) — o
    // resultado real fica guardado no próprio status, só prefixado, pra não
    // virar um "Histórico" genérico que esconde o que de fato aconteceu.
    historico_under_review: 'Histórico · Em análise',
    historico_approved: 'Histórico · Autorizado',
    historico_finalized: 'Histórico · Finalizado',
    historico_canceled: 'Histórico · Cancelado',
    historico_denied: 'Histórico · Negado',
    historico_needs_verification: 'Histórico · Verificar Restrição',
  },
  antecipacoes: {
    open: 'Aberta',
    closed: 'Fechada',
  },
  lancamentos: {
    completed: 'Concluído',
    missed: 'Perdido',
    canceled: 'Cancelado',
  },
  conciliacoes: {
    pending: 'Pendente',
    reviewed: 'Conferida',
    paid: 'Paga',
  },
}

export function translateStatus(entidade: StatusEntidade, status: string): string {
  return statusLabels[entidade][status] ?? status
}

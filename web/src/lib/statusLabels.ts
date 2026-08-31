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
    approved: 'Autorizado',
    finalized: 'Aprovado',
    canceled: 'Cancelado',
    denied: 'Negado',
    needs_verification: 'Verificar Restrição',
    expired: 'Vencido',
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

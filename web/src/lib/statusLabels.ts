export type StatusEntidade =
  | 'solicitacoes'
  | 'guias'
  | 'antecipacoes'
  | 'lancamentos'
  | 'conciliacoes'

const statusLabels: Record<StatusEntidade, Record<string, string>> = {
  solicitacoes: {
    registered: 'Cadastrado',
    under_review: 'Em análise',
    approved: 'Aprovado',
    canceled: 'Cancelado',
    denied: 'Negado',
    expired: 'Vencido',
  },
  guias: {
    registered: 'Cadastrado',
    under_review: 'Em análise',
    approved: 'Aprovado',
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

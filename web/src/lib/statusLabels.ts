export type StatusEntidade =
  | 'solicitacoes'
  | 'guias'
  | 'antecipacoes'
  | 'lancamentos'
  | 'conciliacoes'

const statusLabels: Record<StatusEntidade, Record<string, string>> = {
  solicitacoes: {
    under_review: 'Em análise',
    approved: 'Aprovada',
    denied: 'Negada',
  },
  guias: {
    under_review: 'Em análise',
    finalized: 'Finalizada',
    denied: 'Negada',
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

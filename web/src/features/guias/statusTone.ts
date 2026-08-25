import type { BadgeProps } from '../../components/ui/Badge'

export function statusTone(status: string): NonNullable<BadgeProps['tone']> {
  switch (status) {
    case 'registered':
      return 'acento'
    case 'approved':
    case 'finalized':
      return 'sucesso'
    case 'needs_verification':
      return 'alerta'
    case 'canceled':
    case 'denied':
      return 'perigo'
    case 'expired':
      return 'alerta'
    case 'under_review':
      // A operadora ainda não decidiu — fica em vermelho de propósito pra
      // chamar atenção, já que é o estado que mais precisa de acompanhamento.
      return 'perigo'
    default:
      return 'acento'
  }
}

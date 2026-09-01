import type { BadgeProps } from '../../components/ui/Badge'

export function statusTone(status: string): NonNullable<BadgeProps['tone']> {
  switch (status) {
    case 'registered':
      return 'info'
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
      return 'alerta'
    default:
      return 'info'
  }
}

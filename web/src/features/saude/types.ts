/**
 * Estados possíveis de um componente de saúde.
 *
 * Em inglês porque valor de status fica em inglês no banco e na API (ADR-06); a
 * tradução para a tela mora no `rotuloEstado` do SaudeCard, como o resto do app
 * faz no mapa central de labels.
 */
export type EstadoSaude = 'healthy' | 'warning' | 'down'

export type ComponenteSaude = {
  id: number
  chave: string
  nome: string
  tipo: string
  estado: EstadoSaude
  ultimo_heartbeat_em: string | null
  ultimo_status: string | null
  ultima_mensagem: string | null
  intervalo_esperado_segundos: number
}

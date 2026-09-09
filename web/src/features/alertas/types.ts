export type NivelAlerta = 'verde' | 'amarelo' | 'vermelho'

export type Alerta = {
  id: number
  chave: string
  nivel: NivelAlerta
  titulo: string
  descricao: string | null
  entidade: string | null
  entidade_id: number | null
  dados: Record<string, unknown> | null
  aberto_em: string | null
  resolvido_em: string | null
  reconhecido_por: string | null
  reconhecido_em: string | null
  silenciado_ate: string | null
}

export type AlertaRegra = {
  id: number
  chave: string
  ativo: boolean
  nivel_base: NivelAlerta
  limiar_amarelo: number | null
  limiar_vermelho: number | null
  critica: boolean
  /** Regra listada mas sem avaliador registrado — a tela avisa. */
  implementada: boolean
}

export type SituacaoAlerta = 'aberto' | 'resolvido' | 'silenciado'

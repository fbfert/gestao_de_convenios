import type { TipoNovidade } from './useNovidades'

/**
 * Tipo é comunicado por cor E por rótulo, como o nível do alerta (ADR-23).
 * Tokens do design system — nada de hex, que o `ds:check` reprova.
 */
export const ROTULO_TIPO: Record<TipoNovidade, string> = {
  manual: 'Manual',
  melhoria: 'Melhoria',
  correcao: 'Correção',
  aviso: 'Aviso',
}

export const ETIQUETA_TIPO: Record<TipoNovidade, string> = {
  manual: 'bg-info-suave text-info-texto',
  melhoria: 'bg-sucesso-suave text-sucesso-texto',
  correcao: 'bg-alerta-suave text-alerta-texto',
  aviso: 'bg-perigo-suave text-perigo-texto',
}

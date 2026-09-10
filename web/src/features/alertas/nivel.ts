import type { NivelAlerta } from './types'

/**
 * Nível é comunicado por COR E POR TEXTO.
 *
 * O tema de alto contraste existe por requisito de acessibilidade de um
 * profissional com deficiência de visão de cor (ADR-23): sob deuteranopia, um
 * alerta amarelo e um vermelho seriam a mesma mancha. O rótulo é o que sobrevive.
 *
 * Os pares `*-suave` ainda ganham borda automática naquele tema (index.css), o
 * que dá contorno à etiqueta de graça.
 */
export const NIVEIS: Record<NivelAlerta, { rotulo: string; glifo: string; etiqueta: string }> = {
  vermelho: {
    rotulo: 'Crítico',
    glifo: '×',
    etiqueta: 'bg-perigo-suave text-perigo-texto',
  },
  amarelo: {
    rotulo: 'Atenção',
    glifo: '!',
    etiqueta: 'bg-alerta-suave text-alerta-texto',
  },
  verde: {
    rotulo: 'Informativo',
    glifo: 'i',
    etiqueta: 'bg-info-suave text-info-texto',
  },
}

/** Rótulos em português das chaves de regra; a chave em si fica em inglês/técnica. */
export const ROTULO_DA_CHAVE: Record<string, string> = {
  'senha.vencendo': 'Senha vencendo',
  'guia.negada': 'Guia negada',
  'automacao.falhas_em_serie': 'Automação falhando em série',
  'componente.fora': 'Componente fora do ar',
  'antecipacao.devida': 'Antecipação devida',
}

export function rotuloDaChave(chave: string) {
  return ROTULO_DA_CHAVE[chave] ?? chave
}

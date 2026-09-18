import { useEffect, useState } from 'react'

/**
 * A paleta dos gráficos, lida dos tokens do design system.
 *
 * Recharts pinta por `prop`, não por classe: `<Line stroke={...}>` precisa de
 * uma cor de verdade, e `stroke="var(--grafico-1)"` funciona no SVG mas quebra
 * onde a biblioteca calcula sobre a cor (opacidade de área, cor do ponto ativo).
 * Então a cor é resolvida aqui, uma vez, com `getComputedStyle`.
 *
 * Nenhum literal neste arquivo — é a regra §11.2 do design system, e ela vale
 * especialmente aqui: gráfico não tem teste de contraste, então uma paleta
 * própria em hex quebraria o tema de alto contraste sem que nada avisasse.
 * As cores vivem em `index.css`, que é o único lugar onde cor pode nascer.
 *
 * A leitura acontece no navegador, depois da montagem, e refaz a cada troca de
 * tema: `data-theme` redefine os mesmos tokens, e uma paleta capturada uma vez
 * ficaria com as cores do tema anterior até o próximo F5.
 */

const TOKENS_CATEGORICOS = [
  '--grafico-1',
  '--grafico-2',
  '--grafico-3',
  '--grafico-4',
  '--grafico-5',
  '--grafico-6',
] as const

export type Paleta = {
  /** Uma cor por série, na ordem em que as séries aparecem. */
  categorica: string[]
  /** Linha de grade e eixos. */
  grade: string
  eixo: string
}

/**
 * Paleta de emergência para quando não há DOM (SSR, teste de unidade) ou o
 * token não resolve. Strings vazias, e não cores inventadas: cor inventada
 * passaria despercebida no tema de alto contraste, que é exatamente o caso em
 * que o erro importa. Com string vazia o Recharts desenha no padrão dele e o
 * problema fica visível.
 */
const VAZIA: Paleta = { categorica: [], grade: '', eixo: '' }

function lerPaleta(): Paleta {
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return VAZIA
  }

  const estilo = getComputedStyle(document.documentElement)
  const ler = (token: string) => estilo.getPropertyValue(token).trim()

  return {
    categorica: TOKENS_CATEGORICOS.map(ler).filter((cor) => cor !== ''),
    grade: ler('--grafico-grade'),
    eixo: ler('--grafico-eixo'),
  }
}

/**
 * A paleta do tema atual, re-lida quando o tema muda.
 *
 * Observa o atributo `data-theme` do `<html>`, que é onde o BotaoTema escreve.
 * Sem isso, trocar para o tema de alto contraste deixaria os gráficos com as
 * cores do tema claro — legíveis o bastante para ninguém reclamar, e erradas o
 * bastante para o tema não cumprir o que promete.
 */
export function usePaleta(): Paleta {
  const [paleta, setPaleta] = useState<Paleta>(lerPaleta)

  useEffect(() => {
    setPaleta(lerPaleta())

    const observador = new MutationObserver(() => setPaleta(lerPaleta()))

    observador.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['data-theme'],
    })

    return () => observador.disconnect()
  }, [])

  return paleta
}

/**
 * A cor da série de índice `i`.
 *
 * Cicla depois da sexta em vez de devolver nada: uma série sem cor some do
 * gráfico, e sumir é pior do que repetir. Se alguma aba chegar a precisar disso,
 * o problema é o desenho da aba, não a paleta.
 */
export function corDaSerie(paleta: Paleta, indice: number): string {
  if (paleta.categorica.length === 0) {
    return ''
  }

  return paleta.categorica[indice % paleta.categorica.length]
}

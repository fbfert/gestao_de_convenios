import { expect, test } from '@playwright/test'
import {
  codigoDoErro,
  limparEstadoDoRelator,
  reportClientError,
} from '../../src/lib/reportClientError'

/**
 * O relator de erros, sem navegador.
 *
 * Vive aqui, e não num runner de unidade, porque o projeto não tem um — o
 * Playwright roda um teste que não toca em `page` como qualquer outro, e
 * `conferencia-da-folha.spec.ts` já usa esse caminho.
 *
 * O que estas regras protegem é o comportamento sob estresse: este código roda
 * quando algo já quebrou, então falhar aqui é falhar duas vezes. Um erro em
 * laço de renderização não pode virar enxurrada de requisições, e um `fetch`
 * que rejeita não pode propagar.
 */

type Chamada = { url: string; corpo: Record<string, unknown> }

/** Troca `fetch`, `window` e `navigator` por dublês, e devolve o que foi enviado. */
function comAmbienteFalso(
  opcoes: { fetchRejeita?: boolean } = {},
): { chamadas: Chamada[]; restaurar: () => void } {
  const chamadas: Chamada[] = []
  const originais = {
    fetch: globalThis.fetch,
    window: (globalThis as Record<string, unknown>).window,
    navigator: (globalThis as Record<string, unknown>).navigator,
  }

  globalThis.fetch = ((url: string, init?: RequestInit) => {
    chamadas.push({ url, corpo: JSON.parse(String(init?.body ?? '{}')) })

    return opcoes.fetchRejeita
      ? Promise.reject(new Error('rede fora'))
      : Promise.resolve(new Response(null, { status: 204 }))
  }) as typeof globalThis.fetch

  /*
   * `defineProperty` e não atribuição simples: no Node moderno `navigator` é um
   * global só de leitura, e `globalThis.navigator = …` lança
   * "Cannot set property navigator of #<Object> which has only a getter".
   */
  Object.defineProperty(globalThis, 'window', {
    value: { location: { href: 'https://gescon.test/lancamentos' } },
    configurable: true,
    writable: true,
  })
  Object.defineProperty(globalThis, 'navigator', {
    value: { userAgent: 'Teste/1.0' },
    configurable: true,
    writable: true,
  })

  return {
    chamadas,
    restaurar: () => {
      globalThis.fetch = originais.fetch
      Object.defineProperty(globalThis, 'window', {
        value: originais.window,
        configurable: true,
        writable: true,
      })
      Object.defineProperty(globalThis, 'navigator', {
        value: originais.navigator,
        configurable: true,
        writable: true,
      })
    },
  }
}

test.describe('o relator de erros', () => {
  test.beforeEach(() => limparEstadoDoRelator())

  test('envia o erro com o que o servidor precisa', () => {
    const { chamadas, restaurar } = comAmbienteFalso()

    try {
      reportClientError({ message: 'boom', stack: 'at X' })

      expect(chamadas).toHaveLength(1)
      expect(chamadas[0].url).toContain('/erros-cliente')
      expect(chamadas[0].corpo.message).toBe('boom')
      expect(chamadas[0].corpo.stack).toBe('at X')
      expect(chamadas[0].corpo.url).toBe('https://gescon.test/lancamentos')
      expect(chamadas[0].corpo.userAgent).toBe('Teste/1.0')
      expect(chamadas[0].corpo.occurredAt).toBeTruthy()
    } finally {
      restaurar()
    }
  })

  /**
   * O caso que motiva a deduplicação: um erro dentro do ciclo de renderização
   * se repete a cada tentativa, e sem isto cada repetição seria uma requisição.
   */
  test('o mesmo erro só vai uma vez na sessão', () => {
    const { chamadas, restaurar } = comAmbienteFalso()

    try {
      reportClientError({ message: 'boom', stack: 'at X' })
      reportClientError({ message: 'boom', stack: 'at X' })
      reportClientError({ message: 'boom', stack: 'at X' })

      expect(chamadas).toHaveLength(1)
    } finally {
      restaurar()
    }
  })

  test('erros diferentes vão, até o teto da sessão', () => {
    const { chamadas, restaurar } = comAmbienteFalso()

    try {
      for (let i = 0; i < 12; i += 1) {
        reportClientError({ message: `erro ${i}`, stack: `at ${i}` })
      }

      expect(chamadas).toHaveLength(10)
    } finally {
      restaurar()
    }
  })

  /** Falhar ao relatar a falha não pode virar a segunda falha. */
  test('fetch que rejeita não propaga exceção', async () => {
    const { restaurar } = comAmbienteFalso({ fetchRejeita: true })

    try {
      expect(() => reportClientError({ message: 'boom', stack: 'at X' })).not.toThrow()
      // Dá uma volta no event loop: uma rejeição não tratada apareceria aqui.
      await new Promise((resolve) => setTimeout(resolve, 0))
    } finally {
      restaurar()
    }
  })

  test('ambiente sem fetch não quebra', () => {
    const originalFetch = globalThis.fetch
    // @ts-expect-error — exatamente o cenário degradado que se quer cobrir
    delete globalThis.fetch

    try {
      expect(() => reportClientError({ message: 'boom' })).not.toThrow()
    } finally {
      globalThis.fetch = originalFetch
    }
  })

  test('erro sem mensagem não é enviado', () => {
    const { chamadas, restaurar } = comAmbienteFalso()

    try {
      reportClientError({ message: '' })

      expect(chamadas).toHaveLength(0)
    } finally {
      restaurar()
    }
  })

  test('a pilha é truncada antes de sair', () => {
    const { chamadas, restaurar } = comAmbienteFalso()

    try {
      reportClientError({ message: 'boom', stack: 'x'.repeat(5000) })

      expect(String(chamadas[0].corpo.stack)).toHaveLength(4000)
    } finally {
      restaurar()
    }
  })
})

test.describe('o código do erro', () => {
  test('é estável: o mesmo erro dá o mesmo código', () => {
    expect(codigoDoErro('boom', 'at X')).toBe(codigoDoErro('boom', 'at X'))
  })

  test('distingue erros diferentes', () => {
    expect(codigoDoErro('boom', 'at X')).not.toBe(codigoDoErro('boom', 'at Y'))
    expect(codigoDoErro('boom', 'at X')).not.toBe(codigoDoErro('bam', 'at X'))
  })

  test('tem seis caracteres, para caber num telefonema', () => {
    expect(codigoDoErro('boom', 'at X')).toMatch(/^[0-9A-F]{6}$/)
  })
})

import { expect, test } from '@playwright/test'
import {
  codigoDoErro,
  codigoDoRelato,
  limparEstadoDoRelator,
  reportClientError,
} from '../../src/lib/reportClientError'
import { authStorageKey } from '../../src/stores/authStorageKey'

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

type Chamada = {
  url: string
  corpo: Record<string, unknown>
  cabecalhos: Record<string, string>
}

/**
 * Troca `fetch`, `window` e `navigator` por dublês, e devolve o que foi enviado.
 *
 * `sessao` põe no localStorage falso o que o `authStore` gravaria; `localStorageQuebrado`
 * faz a leitura lançar, para provar que credencial ilegível não impede o relato.
 */
function comAmbienteFalso(
  opcoes: { fetchRejeita?: boolean; sessao?: unknown; localStorageQuebrado?: boolean } = {},
): { chamadas: Chamada[]; restaurar: () => void } {
  const chamadas: Chamada[] = []
  const originais = {
    fetch: globalThis.fetch,
    window: (globalThis as Record<string, unknown>).window,
    navigator: (globalThis as Record<string, unknown>).navigator,
  }

  globalThis.fetch = ((url: string, init?: RequestInit) => {
    chamadas.push({
      url,
      corpo: JSON.parse(String(init?.body ?? '{}')),
      cabecalhos: (init?.headers ?? {}) as Record<string, string>,
    })

    return opcoes.fetchRejeita
      ? Promise.reject(new Error('rede fora'))
      : Promise.resolve(new Response(null, { status: 204 }))
  }) as typeof globalThis.fetch

  const localStorageFalso = {
    getItem: (chave: string) => {
      if (opcoes.localStorageQuebrado) {
        throw new Error('localStorage indisponível')
      }

      return chave === authStorageKey && opcoes.sessao !== undefined
        ? JSON.stringify(opcoes.sessao)
        : null
    },
  }

  /*
   * `defineProperty` e não atribuição simples: no Node moderno `navigator` é um
   * global só de leitura, e `globalThis.navigator = …` lança
   * "Cannot set property navigator of #<Object> which has only a getter".
   */
  Object.defineProperty(globalThis, 'window', {
    value: {
      location: { href: 'https://gescon.test/lancamentos' },
      localStorage: localStorageFalso,
    },
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

  /**
   * Os valores que o PHP também tem de produzir.
   *
   * Duplicados de propósito em `ErroClienteApiTest::test_o_codigo_bate_com_o_do_navegador`.
   * Um teste de estabilidade em cada lado passava verde enquanto os dois lados
   * usavam algoritmos diferentes — era o defeito de 24/09/2026: a clínica leu
   * `836920` na tela e o log tinha `5F6052`.
   *
   * Mudar esta lista sem mudar a do PHP é o erro que ela existe para pegar.
   */
  test('produz os valores de referência que o servidor também produz', () => {
    expect(codigoDoErro('insertBefore', 'at Botao')).toBe('D35CEE')
    expect(codigoDoErro('', '')).toBe('F0C6CD')
    expect(codigoDoErro('Algo deu errado', '')).toBe('43BE78')
    expect(
      codigoDoErro('Não foi possível carregar a guia nº 12 — ação inválida', 'em SolicitacoesPage'),
    ).toBe('E65C26')
    expect(codigoDoErro('a', '')).toBe('2524C6')
    expect(codigoDoErro('😀 emoji fora do BMP', 'pilha')).toBe('FCF556')
  })
})

test.describe('o código que a tela mostra', () => {
  test.beforeEach(() => limparEstadoDoRelator())

  /**
   * O caso que a tela de erro vivia: pilha de React passa fácil de 4000
   * caracteres, e o envio a corta. Calculando o código sobre a pilha inteira, a
   * tela mostraria um número que o log não contém.
   */
  test('é o mesmo do relato enviado, mesmo com pilha acima do limite', () => {
    const { chamadas, restaurar } = comAmbienteFalso()

    try {
      const pilhaLonga = 'at Componente\n'.repeat(1000)
      const erro = { message: 'boom', stack: pilhaLonga }

      const mostrado = codigoDoRelato(erro)
      const enviado = reportClientError(erro)

      expect(mostrado).toBe(enviado)
      expect(mostrado).toBe(codigoDoErro('boom', pilhaLonga.slice(0, 4000)))
      expect(mostrado).not.toBe(codigoDoErro('boom', pilhaLonga))
      expect(chamadas).toHaveLength(1)
    } finally {
      restaurar()
    }
  })

  test('erro sem mensagem não tem código', () => {
    expect(codigoDoRelato({ message: '' })).toBe('')
  })
})

test.describe('a credencial da sessão', () => {
  test.beforeEach(() => limparEstadoDoRelator())

  /**
   * O defeito que produção mostrou: os três erros de 24/09/2026 vinham de telas
   * com usuário logado e chegaram ao servidor com `tenant_id: null`, porque o
   * token nunca era anexado.
   */
  test('vai como Bearer quando há sessão gravada', () => {
    const { chamadas, restaurar } = comAmbienteFalso({
      sessao: { state: { token: 'token-de-teste-123' } },
    })

    try {
      reportClientError({ message: 'erro com sessao' })

      expect(chamadas[0].cabecalhos.Authorization).toBe('Bearer token-de-teste-123')
    } finally {
      restaurar()
    }
  })

  test('não vai quando não há sessão', () => {
    const { chamadas, restaurar } = comAmbienteFalso()

    try {
      reportClientError({ message: 'erro sem sessao' })

      expect(chamadas[0].cabecalhos.Authorization).toBeUndefined()
    } finally {
      restaurar()
    }
  })

  test('sessão sem token não inventa cabeçalho', () => {
    const { chamadas, restaurar } = comAmbienteFalso({ sessao: { state: { user: { id: 1 } } } })

    try {
      reportClientError({ message: 'sessao sem token' })

      expect(chamadas[0].cabecalhos.Authorization).toBeUndefined()
    } finally {
      restaurar()
    }
  })

  /** Relato sem token é muito melhor do que relato nenhum. */
  test('localStorage inacessível não impede o relato', () => {
    const { chamadas, restaurar } = comAmbienteFalso({ localStorageQuebrado: true })

    try {
      reportClientError({ message: 'erro com storage quebrado' })

      expect(chamadas).toHaveLength(1)
      expect(chamadas[0].cabecalhos.Authorization).toBeUndefined()
    } finally {
      restaurar()
    }
  })
})

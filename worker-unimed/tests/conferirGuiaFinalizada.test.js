import assert from 'node:assert/strict'
import { test } from 'node:test'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { dirname, resolve } from 'node:path'
import { chromium } from 'playwright'
import { executarConferirGuiaFinalizada } from '../src/operations/conferirGuiaFinalizada.js'

// As fixtures sao servidas do disco via file://, que `loginUrlFromCredential`
// so aceita sob esta flag — producao nunca a define.
process.env.UNIMED_PERMITIR_FIXTURES_LOCAIS = '1'

const __dirname = dirname(fileURLToPath(import.meta.url))
const fixturePath = resolve(__dirname, 'fixtures/portal-exames-finalizados.html')

/** Números que a fixture considera finalizados. */
const FINALIZADA = '50144618222'
const OUTRA_FINALIZADA = '50144618333'
const NAO_FINALIZADA = '50100000001'

function fixtureUrl(scenario) {
  return `${pathToFileURL(fixturePath).href}?scenario=${scenario}`
}

function requestForScenario(scenario, overrides = {}) {
  return {
    executionId: 77,
    idempotencyKey: `conferir-${scenario}`,
    payload: {
      credential: {
        login: 'operador',
        password: 'secret',
        base_url: fixtureUrl(scenario),
      },
      guias: [{ guia_id: 1, numero_guia: FINALIZADA }],
      ...overrides,
    },
  }
}

async function runScenario(scenario, overrides = {}) {
  const browser = await chromium.launch({ headless: true })
  const page = await browser.newPage()

  try {
    return await executarConferirGuiaFinalizada(requestForScenario(scenario, overrides), { page })
  } finally {
    await browser.close()
  }
}

test('guia que está entre os exames finalizados volta como finalizada', async () => {
  const resultado = await runScenario('success')

  assert.equal(resultado.status, 'succeeded', resultado.message)
  assert.equal(resultado.results.length, 1)
  assert.equal(resultado.results[0].desfecho, 'finalizada')
  assert.equal(resultado.results[0].guia_id, 1)
  assert.equal(resultado.results[0].numero_guia, FINALIZADA)
  assert.deepEqual(resultado.resumo, { finalizadas: 1, nao_finalizadas: 0, falhas: 0 })
})

test('guia que não aparece volta como não finalizada, sem erro', async () => {
  const resultado = await runScenario('success', {
    guias: [{ guia_id: 2, numero_guia: NAO_FINALIZADA }],
  })

  assert.equal(resultado.status, 'succeeded', resultado.message)
  assert.equal(resultado.results[0].desfecho, 'nao_finalizada')
  assert.equal(resultado.results[0].error_code, undefined, 'não achar não é falha')
  assert.deepEqual(resultado.resumo, { finalizadas: 0, nao_finalizadas: 1, falhas: 0 })
})

/**
 * O teste que protege o passo crítico.
 *
 * Com a data reposta pelo portal, o filtro perguntaria "foi finalizada nos
 * últimos dias?" em vez de "foi finalizada alguma vez?" — e devolveria
 * "não finalizada" para uma guia antiga que ESTÁ finalizada. Num lote, isso
 * marcaria dezenas de guias erradas de uma vez, sem nada acusando.
 */
test('portal que repõe a data inicial vira falha, e NÃO um falso negativo', async () => {
  const resultado = await runScenario('data-reposta')

  assert.equal(resultado.results[0].desfecho, 'falhou')
  assert.equal(resultado.results[0].error_code, 'FILTRO_DATA_NAO_LIMPO')
  assert.notEqual(
    resultado.results[0].desfecho,
    'nao_finalizada',
    'a guia ESTÁ finalizada; concluir o contrário é o dano que este código previne',
  )
})

test('tela de exames finalizados que não abre vira falha nomeada', async () => {
  const resultado = await runScenario('tela-nao-abre')

  assert.equal(resultado.results[0].desfecho, 'falhou')
  assert.equal(resultado.results[0].error_code, 'TELA_EXAMES_FINALIZADOS_NAO_ABRIU')
  assert.ok(resultado.results[0].diagnostico?.pagina, 'esperava o diagnóstico da tela')
})

test('tela sem campo de data inicial não é falha', async () => {
  const resultado = await runScenario('sem-campo-data')

  assert.equal(resultado.status, 'succeeded', resultado.message)
  assert.equal(resultado.results[0].desfecho, 'finalizada')
})

test('lote percorre todas as guias num login só e resume os desfechos', async () => {
  const resultado = await runScenario('success', {
    guias: [
      { guia_id: 1, numero_guia: FINALIZADA },
      { guia_id: 2, numero_guia: NAO_FINALIZADA },
      { guia_id: 3, numero_guia: OUTRA_FINALIZADA },
    ],
  })

  assert.equal(resultado.status, 'succeeded', resultado.message)
  assert.deepEqual(
    resultado.results.map((item) => item.desfecho),
    ['finalizada', 'nao_finalizada', 'finalizada'],
  )
  assert.deepEqual(resultado.resumo, { finalizadas: 2, nao_finalizadas: 1, falhas: 0 })
})

test('guia sem número falha sozinha e o lote segue pelas outras', async () => {
  const resultado = await runScenario('success', {
    guias: [
      { guia_id: 1, numero_guia: null },
      { guia_id: 2, numero_guia: FINALIZADA },
    ],
  })

  assert.equal(resultado.status, 'succeeded', resultado.message)
  assert.equal(resultado.results[0].desfecho, 'falhou')
  assert.equal(resultado.results[0].error_code, 'NUMERO_GUIA_AUSENTE')
  assert.equal(resultado.results[1].desfecho, 'finalizada', 'a falha de uma não pode calar as outras')
  assert.deepEqual(resultado.resumo, { finalizadas: 1, nao_finalizadas: 0, falhas: 1 })
})

test('operação sem guia nenhuma não quebra', async () => {
  const resultado = await runScenario('success', { guias: [] })

  assert.equal(resultado.status, 'succeeded')
  assert.deepEqual(resultado.results, [])
  assert.deepEqual(resultado.resumo, { finalizadas: 0, nao_finalizadas: 0, falhas: 0 })
})

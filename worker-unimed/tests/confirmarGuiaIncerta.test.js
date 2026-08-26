import assert from 'node:assert/strict'
import { test } from 'node:test'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { dirname, resolve } from 'node:path'
import { chromium } from 'playwright'
import { executarConfirmarGuiaIncerta } from '../src/operations/confirmarGuiaIncerta.js'

const __dirname = dirname(fileURLToPath(import.meta.url))
const fixturePath = resolve(__dirname, 'fixtures/portal-status-senha.html')

function fixtureUrl(scenario) {
  return `${pathToFileURL(fixturePath).href}?scenario=${scenario}`
}

function requestForScenario(scenario, overrides = {}) {
  return {
    executionId: 88,
    idempotencyKey: `confirmar-${scenario}`,
    payload: {
      credential: {
        login: 'operador',
        password: 'secret',
        base_url: fixtureUrl(scenario),
      },
      paciente: {
        nome: 'Laura De Faveri',
        carteirinha: '10000010000654321',
      },
      ...overrides,
    },
  }
}

async function run(scenario, overrides = {}) {
  const browser = await chromium.launch({ headless: true })
  const page = await browser.newPage()
  try {
    return await executarConfirmarGuiaIncerta(requestForScenario(scenario, overrides), { page })
  } finally {
    await browser.close()
  }
}

test('confirmar guia incerta: acha o paciente numa pagina seguinte e le os dados da guia', async () => {
  const result = await run('confirmar-encontrada')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.encontrada, true)
  assert.equal(result.numero_guia, 'UNI-999')
  assert.equal(result.guia_status, 'approved')
  assert.equal(result.senha, '8811223')
  assert.equal(result.sessoes_solicitadas, 10)
  assert.equal(result.sessoes_autorizadas, 10)
})

test('confirmar guia incerta: nao encontra o paciente em nenhuma pagina', async () => {
  const result = await run('confirmar-nao-encontrada')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.encontrada, false)
  assert.equal(result.numero_guia, undefined)
})

test('confirmar guia incerta: carteirinha ausente no payload falha antes de abrir o portal', async () => {
  const result = await run('confirmar-encontrada', { paciente: { nome: 'Sem Carteirinha', carteirinha: '' } })

  assert.equal(result.status, 'failed')
  assert.equal(result.error_code, 'CONFIGURATION_INVALID_CARD')
})

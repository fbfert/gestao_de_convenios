import assert from 'node:assert/strict'
import { test } from 'node:test'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { dirname, resolve } from 'node:path'
import { chromium } from 'playwright'
import { executarCapturarAutorizacaoBatch, executarConsultarStatusBatch } from '../src/operations/statusSenha.js'

const __dirname = dirname(fileURLToPath(import.meta.url))
const fixturePath = resolve(__dirname, 'fixtures/portal-status-senha.html')

function fixtureUrl(scenario) {
  return `${pathToFileURL(fixturePath).href}?scenario=${scenario}`
}

function requestForScenario(scenario, overrides = {}) {
  return {
    executionId: 77,
    idempotencyKey: `status-${scenario}`,
    payload: {
      credential: {
        login: 'operador',
        password: 'secret',
        base_url: fixtureUrl(scenario),
      },
      guia_id: 10,
      numero_guia: 'UNI-123',
      paciente: {
        nome: 'Paciente Teste',
        carteirinha: '12345678123456012',
      },
      ...overrides,
    },
  }
}

async function run(operation, scenario, overrides = {}) {
  const browser = await chromium.launch({ headless: true })
  const page = await browser.newPage()
  try {
    return await operation(requestForScenario(scenario, overrides), { page })
  } finally {
    await browser.close()
  }
}

test('consulta status aprovado usando fixture local', async () => {
  const result = await run(executarConsultarStatusBatch, 'status-approved')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.guia_status, 'approved')
  assert.equal(result.unimed_status, 'Autorizado')
  assert.equal(result.conclusivo, true)
})

test('consulta status negado e cancelado mapeia status interno', async () => {
  const denied = await run(executarConsultarStatusBatch, 'status-denied')
  const canceled = await run(executarConsultarStatusBatch, 'status-canceled')

  assert.equal(denied.guia_status, 'denied')
  assert.equal(canceled.guia_status, 'canceled')
})

test('restricao individual nao marca consulta como conclusiva', async () => {
  const result = await run(executarConsultarStatusBatch, 'status-restriction')

  assert.equal(result.status, 'failed')
  assert.equal(result.error_code, 'BENEFICIARY_RESTRICTION')
  assert.equal(result.conclusivo, false)
})

test('captura senha e validade em pagina posterior', async () => {
  const result = await run(executarCapturarAutorizacaoBatch, 'capture-page-2')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.senha, 'SENHA-321')
  assert.equal(result.validade_senha, '2026-09-15')
})

test('captura retorna NOT_FOUND_IN_OPEN_EXAMS quando guia nao aparece', async () => {
  const result = await run(executarCapturarAutorizacaoBatch, 'capture-not-found')

  assert.equal(result.status, 'failed')
  assert.equal(result.error_code, 'NOT_FOUND_IN_OPEN_EXAMS')
})

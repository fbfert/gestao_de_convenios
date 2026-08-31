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

test('consulta status: guia autorizada via busca por numero (s_nr_guia + Button_FIltro)', async () => {
  const result = await run(executarConsultarStatusBatch, 'status-approved')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.guia_status, 'approved')
  assert.equal(result.unimed_status, 'Autorizado')
  assert.equal(result.conclusivo, true)
  // Formulario de execucao sem quantidades preenchidas: nao inventa numeros.
  assert.equal(result.sessoes_autorizadas, undefined)
  assert.equal(result.sessoes_solicitadas, undefined)
})

test('consulta status captura quantidades quando o formulario de execucao traz', async () => {
  const result = await run(executarConsultarStatusBatch, 'status-approved-com-quantidades')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.sessoes_solicitadas, 10)
  assert.equal(result.sessoes_autorizadas, 6)
})

test('consulta status: guia ainda sem autorizacao/senha fica nao conclusiva', async () => {
  const result = await run(executarConsultarStatusBatch, 'status-em-analise')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.conclusivo, false)
})

test('consulta status: guia nao aparece em Exames em aberto', async () => {
  const result = await run(executarConsultarStatusBatch, 'status-nao-encontrada')

  assert.equal(result.status, 'failed')
  assert.equal(result.error_code, 'GUIA_NOT_FOUND')
  assert.equal(result.conclusivo, false)
})

test('consulta status: guia negada some de Exames em aberto mas e achada via cadastro de beneficiario (icone ico16negado.gif)', async () => {
  const result = await run(executarConsultarStatusBatch, 'status-negada-via-cadastro')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.guia_status, 'denied')
  assert.equal(result.unimed_status, 'Negado')
  assert.equal(result.conclusivo, true)
})

test('captura senha e validade pela mesma tela de execucao', async () => {
  const result = await run(executarCapturarAutorizacaoBatch, 'capture-sucesso')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.senha, '9248082')
  assert.equal(result.validade_senha, '2026-10-24')
})

test('captura retorna SENHA_NAO_DISPONIVEL quando a guia ainda nao tem senha', async () => {
  const result = await run(executarCapturarAutorizacaoBatch, 'capture-sem-senha')

  assert.equal(result.status, 'failed')
  assert.equal(result.error_code, 'SENHA_NAO_DISPONIVEL')
})

test('captura retorna NOT_FOUND_IN_OPEN_EXAMS quando guia nao aparece', async () => {
  const result = await run(executarCapturarAutorizacaoBatch, 'capture-nao-encontrada')

  assert.equal(result.status, 'failed')
  assert.equal(result.error_code, 'NOT_FOUND_IN_OPEN_EXAMS')
})

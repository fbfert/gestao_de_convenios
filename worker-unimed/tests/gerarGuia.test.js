import assert from 'node:assert/strict'
import { test } from 'node:test'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { dirname, resolve } from 'node:path'
import { chromium } from 'playwright'
import { executarGerarGuia } from '../src/operations/gerarGuia.js'

const __dirname = dirname(fileURLToPath(import.meta.url))
const fixturePath = resolve(__dirname, 'fixtures/portal-gerar-guia.html')

function fixtureUrl(scenario) {
  return `${pathToFileURL(fixturePath).href}?scenario=${scenario}`
}

function requestForScenario(scenario, overrides = {}) {
  return {
    executionId: 42,
    idempotencyKey: `test-${scenario}`,
    payload: {
      credential: {
        login: 'operador',
        password: 'secret',
        base_url: fixtureUrl(scenario),
      },
      paciente: {
        nome: 'Paciente Teste',
        carteirinha: '12345678123456012',
      },
      medico: {
        nome: 'Dr. Carlos Almeida',
        crm: '12345',
      },
      cid: 'F84',
      codigo_procedimento: '50000470',
      descricao_operadora: 'Terapia especializada',
      quantidade: 10,
      codigo_profissional_operadora: '1234',
      pedido_medico: {
        tipo: 'pedido_medico',
        nome_original: 'pedido.pdf',
        path: 'fixture:pedido.pdf',
        size: 1024,
      },
      ...overrides,
    },
  }
}

async function runScenario(scenario, overrides = {}) {
  const browser = await chromium.launch({ headless: true })
  const page = await browser.newPage()
  try {
    return await executarGerarGuia(requestForScenario(scenario, overrides), { page })
  } finally {
    await browser.close()
  }
}

test('gera guia com sucesso usando fixture local', async () => {
  const result = await runScenario('success')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.numero_guia, 'GUIA-8899')
  assert.equal(result.protocolo_operadora, 'PROTO-77')
  assert.equal(result.sessoes_solicitadas, 10)
  assert.equal(result.sessoes_autorizadas, 8)
  assert.equal(result.senha, 'SENHA-ABC')
  assert.equal(result.guia_status, 'approved')
  assert.equal(result.medico_strategy, 'crm')
})

test('restricao administrativa retorna needs_verification sem numero', async () => {
  const result = await runScenario('restriction')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.guia_status, 'needs_verification')
  assert.equal(result.numero_guia, null)
  assert.match(result.unimed_status, /Restrição Administrativa/)
})

test('atualizacao cadastral segue o fluxo', async () => {
  const result = await runScenario('update')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.numero_guia, 'GUIA-8899')
})

test('fallback de medico por nome', async () => {
  const result = await runScenario('provider-name')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.medico_strategy, 'nome')
})

test('fallback de medico nao cooperado', async () => {
  const result = await runScenario('provider-fallback')

  assert.equal(result.status, 'succeeded')
  assert.equal(result.medico_strategy, 'nao_cooperado')
})

test('campos genericos continuam o fluxo', async () => {
  const result = await runScenario('generic', {
    usa_descricao_generica: true,
    valor_generico: 'Descricao generica',
  })

  assert.equal(result.status, 'succeeded')
  assert.equal(result.guia_status, 'approved')
})

test('falha no upload obrigatorio interrompe o item', async () => {
  const result = await runScenario('upload-fail')

  assert.equal(result.status, 'failed')
  assert.equal(result.error_code, 'PEDIDO_MEDICO_UPLOAD_FAILED')
})

test('timeout apos submit retorna uncertain sem retry', async () => {
  const result = await runScenario('uncertain')

  assert.equal(result.status, 'uncertain')
  assert.equal(result.error_code, 'UNCERTAIN_AFTER_SUBMIT')
})

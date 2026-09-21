import assert from 'node:assert/strict'
import { test } from 'node:test'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { dirname, resolve } from 'node:path'
import { chromium } from 'playwright'
import {
  executarFinalizarGuia,
  formatarDataSerie,
  valorFoiAceito,
} from '../src/operations/finalizarGuia.js'

// As fixtures sao servidas do disco via file://, que `loginUrlFromCredential`
// so aceita sob esta flag — producao nunca a define.
process.env.UNIMED_PERMITIR_FIXTURES_LOCAIS = '1'

const __dirname = dirname(fileURLToPath(import.meta.url))
const fixturePath = resolve(__dirname, 'fixtures/portal-finalizar-guia.html')
const folhaPath = resolve(__dirname, 'fixtures/portal-finalizar-guia.html')

function fixtureUrl(scenario) {
  return `${pathToFileURL(fixturePath).href}?scenario=${scenario}`
}

function requestForScenario(scenario, overrides = {}) {
  return {
    executionId: 99,
    idempotencyKey: `finalizar-${scenario}`,
    payload: {
      credential: {
        login: 'operador',
        password: 'secret',
        base_url: fixtureUrl(scenario),
      },
      numero_guia: '50144616272',
      sessoes_autorizadas: 10,
      sessoes: [
        { data: '2026-09-21', hora: '08:00' },
        { data: '2026-09-21', hora: '09:00' },
      ],
      // Qualquer arquivo serve: o que se prova é o caminho, não o conteúdo.
      anexos: [{ nome_original: 'folha-1.pdf', local_path: folhaPath }],
      simular: false,
      ...overrides,
    },
  }
}

async function runScenario(scenario, overrides = {}) {
  const browser = await chromium.launch({ headless: true })
  const page = await browser.newPage()

  try {
    const resultado = await executarFinalizarGuia(requestForScenario(scenario, overrides), { page })
    const estado = await page
      .evaluate(() => ({
        finalizado: Boolean(window.__finalizado),
        anexos: window.__anexos ?? [],
        regime: document.querySelector('#DM_REGIME_ATEND')?.value ?? null,
        tipo: document.querySelector('#DM_TP_ATEND_SADT')?.value ?? null,
        series: Array.from(document.querySelectorAll('[id^="dt_serie_"]'))
          .map((campo) => campo.value)
          .filter((valor) => valor !== ''),
      }))
      .catch(() => null)

    return { resultado, estado }
  } finally {
    await browser.close()
  }
}

test('finaliza a guia preenchendo regime, tipo, datas e anexo', async () => {
  const { resultado, estado } = await runScenario('success')

  assert.equal(resultado.status, 'succeeded', resultado.message)
  assert.equal(resultado.simulado, false)
  assert.equal(resultado.sessoes_preenchidas, 2)
  assert.equal(resultado.anexos_enviados, 1)

  assert.equal(estado.finalizado, true)
  assert.equal(estado.regime, '01')
  assert.equal(estado.tipo, '03')
  assert.deepEqual(estado.series, ['21/09/2026 08:00', '21/09/2026 09:00'])
})

test('sobrescreve regime e tipo quando a tela abre com outros valores', async () => {
  const { resultado, estado } = await runScenario('outro-regime')

  assert.equal(resultado.status, 'succeeded', resultado.message)
  assert.equal(estado.regime, '01')
  assert.equal(estado.tipo, '03')
})

test('preenche as datas em ordem cronológica, mesmo recebendo fora de ordem', async () => {
  const { estado } = await runScenario('success', {
    sessoes: [
      { data: '2026-09-22', hora: '10:00' },
      { data: '2026-09-21', hora: '08:00' },
      { data: '2026-09-21', hora: '09:00' },
    ],
  })

  assert.deepEqual(estado.series, [
    '21/09/2026 08:00',
    '21/09/2026 09:00',
    '22/09/2026 10:00',
  ])
})

test('envia duas folhas, uma por vez', async () => {
  const { resultado, estado } = await runScenario('duas-folhas', {
    anexos: [
      { nome_original: 'folha-1.pdf', local_path: folhaPath },
      { nome_original: 'folha-2.pdf', local_path: folhaPath },
    ],
  })

  assert.equal(resultado.status, 'succeeded', resultado.message)
  assert.equal(resultado.anexos_enviados, 2)
  assert.equal(estado.anexos.length, 2)
})

test('falha quando a busca não devolve a guia', async () => {
  const { resultado } = await runScenario('guia-nao-encontrada')

  assert.equal(resultado.status, 'failed')
  assert.equal(resultado.error_code, 'GUIA_NAO_ENCONTRADA_NO_PORTAL')
  assert.ok(resultado.diagnostico?.pagina, 'esperava o diagnóstico da tela')
})

test('falha quando o portal autoriza quantidade diferente da que o Gescon mandou', async () => {
  const { resultado, estado } = await runScenario('qt-divergente')

  assert.equal(resultado.status, 'failed')
  assert.equal(resultado.error_code, 'QT_AUTORIZADA_DIVERGENTE')
  assert.equal(resultado.qt_autorizada_portal, 4)
  assert.equal(resultado.qt_autorizada_gescon, 10)
  assert.equal(estado.finalizado, false, 'não pode finalizar com divergência')
})

test('falha, sem finalizar, quando há mais sessões que o autorizado no portal', async () => {
  const { resultado, estado } = await runScenario('qt-divergente', {
    sessoes_autorizadas: 4,
    sessoes: [
      { data: '2026-09-21', hora: '08:00' },
      { data: '2026-09-21', hora: '09:00' },
      { data: '2026-09-21', hora: '10:00' },
      { data: '2026-09-21', hora: '11:00' },
      { data: '2026-09-21', hora: '12:00' },
    ],
  })

  assert.equal(resultado.status, 'failed')
  assert.equal(resultado.error_code, 'SESSOES_ACIMA_DO_AUTORIZADO')
  assert.equal(estado.finalizado, false)
})

test('falha nomeando o formato quando o campo de data recusa o valor', async () => {
  const { resultado, estado } = await runScenario('data-recusada')

  assert.equal(resultado.status, 'failed')
  assert.equal(resultado.error_code, 'DT_SERIE_FORMATO_RECUSADO')
  assert.equal(resultado.campo, '#dt_serie_1')
  assert.equal(resultado.enviado, '21/09/2026 08:00')
  assert.equal(resultado.aceito, '')
  assert.equal(estado.finalizado, false)
})

test('falha quando um campo da série está desabilitado', async () => {
  const { resultado, estado } = await runScenario('campo-desabilitado')

  assert.equal(resultado.status, 'failed')
  assert.equal(resultado.error_code, 'DT_SERIE_CAMPO_INDISPONIVEL')
  assert.equal(estado.finalizado, false)
})

test('falha quando o portal não confirma o anexo', async () => {
  const { resultado, estado } = await runScenario('anexo-falha')

  assert.equal(resultado.status, 'failed')
  assert.equal(resultado.error_code, 'ANEXO_NAO_CONFIRMADO')
  assert.equal(resultado.anexo, 'folha-1.pdf')
  assert.equal(estado.finalizado, false)
})

test('falha quando o portal recusa a finalização', async () => {
  const { resultado, estado } = await runScenario('erro-ao-finalizar')

  assert.equal(resultado.status, 'failed')
  assert.equal(resultado.error_code, 'GRAVAR_FINALIZAR_RECUSADO')
  assert.match(resultado.message, /validade da senha/)
  assert.equal(estado.finalizado, false)
})

test('modo simulação preenche e anexa, mas NÃO grava e finaliza', async () => {
  const { resultado, estado } = await runScenario('success', { simular: true })

  assert.equal(resultado.status, 'succeeded', resultado.message)
  assert.equal(resultado.simulado, true)
  assert.equal(estado.finalizado, false, 'simulação não pode acionar Gravar e Finalizar')

  // O que foi preenchido continua na tela, e a evidência prova isso.
  assert.equal(estado.regime, '01')
  assert.equal(estado.anexos.length, 1)
  assert.equal(resultado.evidencia.regime_atendimento, '01')
  assert.equal(resultado.evidencia.tipo_atendimento, '03')
  assert.deepEqual(resultado.evidencia.datas_preenchidas, ['21/09/2026 08:00', '21/09/2026 09:00'])
  assert.ok(resultado.evidencia.screenshot_base64, 'esperava screenshot da simulação')
})

test('recusa a operação sem número de guia', async () => {
  const { resultado } = await runScenario('success', { numero_guia: '' })

  assert.equal(resultado.status, 'failed')
  assert.equal(resultado.error_code, 'NUMERO_GUIA_AUSENTE')
})

test('recusa a operação sem sessões para preencher', async () => {
  const { resultado } = await runScenario('success', { sessoes: [] })

  assert.equal(resultado.status, 'failed')
  assert.equal(resultado.error_code, 'SEM_SESSOES_PARA_ENVIAR')
})

test('formata data e hora no formato presumido do portal', () => {
  assert.equal(formatarDataSerie({ data: '2026-09-21', hora: '08:00' }), '21/09/2026 08:00')
  assert.equal(formatarDataSerie({ data: '2026-09-21', hora: '08:00:00' }), '21/09/2026 08:00')
  assert.equal(formatarDataSerie({ data: '2026-09-21', hora: null }), '21/09/2026')
})

/**
 * O campo pode devolver só a data onde mandamos data e hora — isso é o portal
 * dizendo que a hora vai em outro lugar, não uma recusa. Recusa é campo vazio
 * ou com data diferente.
 */
test('aceita o valor que o campo normalizou, recusa o que ele descartou', () => {
  assert.equal(valorFoiAceito('21/09/2026 08:00', '21/09/2026 08:00'), true)
  assert.equal(valorFoiAceito('21/09/2026 08:00', '21/09/2026'), true)
  assert.equal(valorFoiAceito('21/09/2026 08:00', ''), false)
  assert.equal(valorFoiAceito('21/09/2026 08:00', '22/09/2026'), false)
})

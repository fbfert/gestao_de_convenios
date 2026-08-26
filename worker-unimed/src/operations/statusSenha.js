import { chromium } from 'playwright'
import {
  DEFAULT_TIMEOUT,
  WorkerResultError,
  fillIfVisible,
  login,
  mapPortalStatus,
  parseNumber,
  waitProcessing,
} from '../portal.js'

export async function executarConsultarStatusBatch(request, options = {}) {
  return withPage(options, (page) => consultarStatusBatch(page, request))
}

export async function executarCapturarAutorizacaoBatch(request, options = {}) {
  return withPage(options, (page) => capturarAutorizacaoBatch(page, request))
}

async function withPage(options, callback) {
  const browser = options.page ? null : await chromium.launch({ headless: true })
  const page = options.page ?? await browser.newPage()

  try {
    return await callback(page)
  } catch (error) {
    if (error instanceof WorkerResultError) {
      return error.result
    }

    return {
      status: 'failed',
      error_code: 'WORKER_INTERNAL_FATAL',
      message: error instanceof Error ? error.message : 'Falha interna no worker.',
    }
  } finally {
    if (browser) {
      await browser.close()
    }
  }
}

async function consultarStatusBatch(page, request) {
  const payload = request.payload ?? {}
  const guias = guiasFromPayload(payload)

  await login(page, payload.credential ?? {})

  const results = []
  for (const guia of guias) {
    results.push(await consultarStatusGuia(page, guia))
  }

  return {
    status: 'succeeded',
    execution_id: request.executionId ?? null,
    operation: 'consult_status_batch',
    results,
    ...singleResultCompat(results),
  }
}

/**
 * Fluxo real confirmado em produção (25/08/2026, guia 50143966538): a tela
 * que já aparece logo depois do login ("Exames em aberto") tem um formulário
 * de busca de verdade — campo `s_nr_guia` + botão `Button_FIltro` (esse nome
 * mal escrito é do próprio portal, não é typo nosso). Clicando na guia
 * encontrada, abre a tela de execução do SP/SADT, que já traz preenchidos
 * `DT_AUTORIZACAO`, `NR_SENHA`, `DT_VALIDADE_SENHA`, `QT_SOLIC_1` e
 * `QT_AUTORIZADA_1` — dá pra ler os quatro sem nunca submeter o formulário.
 *
 * A versão anterior deste arquivo reusava o fluxo de "+ Novo Exame"
 * (cadastro de beneficiário, pensado para CRIAR um exame) para consultar
 * status de uma guia já existente — por isso travava sempre no mesmo lugar
 * (não existe busca por guia nessa tela). Ver AutomacaoExecucao #16/#19 em
 * produção para o timeout original.
 */
async function consultarStatusGuia(page, guia) {
  try {
    const encontrada = await abrirGuiaPorFiltro(page, guia.numero_guia)
    if (!encontrada) {
      return itemResult(guia, {
        status: 'failed',
        error_code: 'GUIA_NOT_FOUND',
        message: 'Guia não encontrada em Exames em aberto.',
        conclusivo: false,
      })
    }

    const dados = await lerDadosExecucaoGuia(page)
    // Presença de data de autorização + senha é o sinal de autorizado — a
    // tela de execução não traz um rótulo "Situação" separado.
    const autorizada = Boolean(dados.dtAutorizacao && dados.nrSenha)
    const situacao = autorizada ? 'Autorizado' : 'Em análise'

    return itemResult(guia, {
      status: 'succeeded',
      portal_status: situacao,
      guia_status: mapPortalStatus(situacao),
      unimed_status: situacao,
      status_operadora: situacao,
      conclusivo: autorizada,
      ...(dados.qtSolicitadas !== null ? { sessoes_solicitadas: dados.qtSolicitadas } : {}),
      ...(dados.qtAutorizadas !== null ? { sessoes_autorizadas: dados.qtAutorizadas } : {}),
    })
  } catch (error) {
    if (error instanceof WorkerResultError) {
      throw error
    }

    return itemResult(guia, {
      status: 'failed',
      error_code: 'ITEM_STATUS_FAILED',
      message: error instanceof Error ? error.message : 'Falha ao consultar guia.',
      conclusivo: false,
    })
  }
}

async function capturarAutorizacaoBatch(page, request) {
  const payload = request.payload ?? {}
  const guias = guiasFromPayload(payload)

  await login(page, payload.credential ?? {})

  const results = []
  for (const guia of guias) {
    results.push(await capturarAutorizacaoGuia(page, guia))
  }

  return {
    status: 'succeeded',
    execution_id: request.executionId ?? null,
    operation: 'capture_authorization_data_batch',
    results,
    ...singleResultCompat(results),
  }
}

async function capturarAutorizacaoGuia(page, guia) {
  try {
    const encontrada = await abrirGuiaPorFiltro(page, guia.numero_guia)
    if (!encontrada) {
      return itemResult(guia, {
        status: 'failed',
        error_code: 'NOT_FOUND_IN_OPEN_EXAMS',
        message: 'Guia não encontrada em Exames em aberto.',
      })
    }

    const dados = await lerDadosExecucaoGuia(page)
    if (!dados.nrSenha) {
      return itemResult(guia, {
        status: 'failed',
        error_code: 'SENHA_NAO_DISPONIVEL',
        message: 'Guia encontrada em Exames em aberto, mas o portal ainda não mostra senha de autorização.',
      })
    }

    return itemResult(guia, {
      status: 'succeeded',
      senha: dados.nrSenha,
      validade_senha: dados.dtValidadeSenha,
    })
  } catch (error) {
    if (error instanceof WorkerResultError) {
      throw error
    }

    return itemResult(guia, {
      status: 'failed',
      error_code: 'ITEM_CAPTURE_FAILED',
      message: error instanceof Error ? error.message : 'Falha ao capturar autorização.',
    })
  }
}

/**
 * Busca a guia em "Exames em aberto" pelo número exato (campo `s_nr_guia` +
 * `Button_FIltro`) e, se achar, clica nela — deixando `page` na tela de
 * execução do SP/SADT. Devolve false sem lançar quando a guia não aparece
 * (não é erro de automação, é a guia genuinamente não estar nessa lista).
 */
async function abrirGuiaPorFiltro(page, numeroGuia) {
  await abrirExamesAbertos(page)
  await fillIfVisible(page, '[name="s_nr_guia"]', numeroGuia)
  await page.locator('[name="Button_FIltro"]').first().click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)

  const row = await rowByGuia(page, numeroGuia)
  if (!row) {
    return false
  }

  await row.locator('a').first().click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
  await page.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT }).catch(() => {})

  return true
}

export async function lerDadosExecucaoGuia(page) {
  const dtAutorizacao = await valorCampo(page, 'DT_AUTORIZACAO')
  const nrSenha = await valorCampo(page, 'NR_SENHA')
  const dtValidadeSenha = normalizeDate(await valorCampo(page, 'DT_VALIDADE_SENHA'))
  const qtSolicitadas = parseNumber(await valorCampo(page, 'QT_SOLIC_1'))
  const qtAutorizadas = parseNumber(await valorCampo(page, 'QT_AUTORIZADA_1'))

  return { dtAutorizacao, nrSenha, dtValidadeSenha, qtSolicitadas, qtAutorizadas }
}

export async function valorCampo(page, name) {
  const locator = page.locator(`[name="${name}"]`).first()
  if ((await locator.count()) === 0) {
    return null
  }

  const value = await locator.inputValue({ timeout: DEFAULT_TIMEOUT }).catch(() => null)
  return value && value.trim() !== '' ? value.trim() : null
}

export async function abrirExamesAbertos(page) {
  const link = page.locator('#exames-abertos, a:has-text("Exames em aberto")').first()
  await link.click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
}

async function rowByGuia(page, numeroGuia) {
  const rows = page.locator('[data-guia-row], table tr')
  const count = await rows.count()
  for (let index = 0; index < count; index += 1) {
    const row = rows.nth(index)
    const text = await row.innerText().catch(() => '')
    if (text.includes(String(numeroGuia))) {
      return row
    }
  }

  return null
}

function guiasFromPayload(payload) {
  if (Array.isArray(payload.guias)) {
    return payload.guias
  }

  return [payload]
}

function itemResult(guia, result) {
  return {
    guia_id: guia.guia_id ?? guia.id ?? null,
    numero_guia: guia.numero_guia ?? null,
    ...result,
  }
}

function singleResultCompat(results) {
  return results.length === 1 ? results[0] : {}
}

function normalizeDate(value) {
  const raw = String(value ?? '').trim()
  const match = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/)
  if (match) {
    return `${match[3]}-${match[2]}-${match[1]}`
  }

  return raw || null
}

import { chromium } from 'playwright'
import {
  DEFAULT_TIMEOUT,
  WorkerResultError,
  abrirBeneficiario,
  atualizarCadastroSeNecessario,
  fillIfVisible,
  login,
  mapPortalStatus,
  parseNumber,
  pickValue,
  preencherCarteirinha,
  splitCarteirinha,
  textoRestricao,
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
    await voltarInicioSePossivel(page)
  }

  return {
    status: 'succeeded',
    execution_id: request.executionId ?? null,
    operation: 'consult_status_batch',
    results,
    ...singleResultCompat(results),
  }
}

async function consultarStatusGuia(page, guia) {
  try {
    await abrirBeneficiario(page)
    await preencherCarteirinha(page, splitCarteirinha(guia.paciente?.carteirinha))

    const restriction = await textoRestricao(page)
    if (restriction) {
      return itemResult(guia, {
        status: 'failed',
        error_code: 'BENEFICIARY_RESTRICTION',
        message: restriction,
        conclusivo: false,
      })
    }

    await atualizarCadastroSeNecessario(page)
    await abrirLocalizarGuia(page)
    await fillIfVisible(page, '[name="s_NR_GUIA"], #s_NR_GUIA', guia.numero_guia)
    await page.locator('[name="Button_DoSearch"], #localizar-guia').click({ timeout: DEFAULT_TIMEOUT })
    await waitProcessing(page)

    const statusText = await lerSituacaoGuia(page, guia.numero_guia)
    if (!statusText) {
      return itemResult(guia, {
        status: 'failed',
        error_code: 'GUIA_NOT_FOUND',
        message: 'Guia não encontrada no portal.',
        conclusivo: false,
      })
    }

    const guiaStatus = mapPortalStatus(statusText)

    return itemResult(guia, {
      status: 'succeeded',
      portal_status: guiaStatus,
      guia_status: guiaStatus,
      unimed_status: statusText,
      status_operadora: statusText,
      conclusivo: true,
      ...(await lerQuantidades(page, guia.numero_guia)),
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
  const pending = new Map(guias.map((guia) => [String(guia.numero_guia), guia]))
  const results = []

  await login(page, payload.credential ?? {})
  await abrirExamesAbertos(page)

  const visited = new Set()
  while (true) {
    const pageKey = await page.locator('body').innerText({ timeout: DEFAULT_TIMEOUT }).catch(() => String(visited.size))
    if (visited.has(pageKey)) {
      break
    }
    visited.add(pageKey)

    for (const [numeroGuia, guia] of [...pending]) {
      const found = await abrirGuiaEmExamesAbertos(page, guia)
      if (!found) {
        continue
      }

      const senha = await readInputOrText(page, '[name="NR_SENHA"], #NR_SENHA, [data-field="NR_SENHA"]')
      const validadeSenha = normalizeDate(await readInputOrText(page, '[name="DT_VALIDADE_SENHA"], #DT_VALIDADE_SENHA, [data-field="DT_VALIDADE_SENHA"]'))

      results.push(itemResult(guia, {
        status: 'succeeded',
        senha,
        validade_senha: validadeSenha,
      }))
      pending.delete(numeroGuia)
      await voltarExamesAbertos(page)
    }

    if (pending.size === 0 || !await irProximaPagina(page)) {
      break
    }
  }

  for (const guia of pending.values()) {
    results.push(itemResult(guia, {
      status: 'failed',
      error_code: 'NOT_FOUND_IN_OPEN_EXAMS',
      message: 'Guia não encontrada em Exames em aberto.',
    }))
  }

  return {
    status: 'succeeded',
    execution_id: request.executionId ?? null,
    operation: 'capture_authorization_data_batch',
    results,
    ...singleResultCompat(results),
  }
}

/**
 * A operadora pode revisar a quantidade autorizada depois da guia gerada. Usamos os
 * mesmos rótulos que o gerarGuia já lê do HTML real ("Qtd:" e "Qtd Aut:"). Quando a
 * tela não traz essas informações, devolvemos objeto vazio e nada é sobrescrito.
 */
async function lerQuantidades(page, numeroGuia) {
  const row = await rowByGuia(page, numeroGuia)
  const texto = row
    ? await row.innerText().catch(() => '')
    : await page.locator('body').innerText({ timeout: DEFAULT_TIMEOUT }).catch(() => '')

  const solicitadas = parseNumber(pickValue(texto, /Qtd:\s*(\d+)/i))
  const autorizadas = parseNumber(pickValue(texto, /Qtd\s*Aut\.?:\s*(\d+)/i))
  const quantidades = {}

  if (solicitadas !== null) {
    quantidades.sessoes_solicitadas = solicitadas
  }
  if (autorizadas !== null) {
    quantidades.sessoes_autorizadas = autorizadas
  }

  return quantidades
}

async function abrirLocalizarGuia(page) {
  const link = page.locator('#localizar-guia-link, a:has-text("Localizar Guia"), a:has-text("Localizar guia")').first()
  if (await link.isVisible({ timeout: 500 }).catch(() => false)) {
    await link.click({ timeout: DEFAULT_TIMEOUT })
    await waitProcessing(page)
  }
}

async function abrirExamesAbertos(page) {
  const link = page.locator('#exames-abertos, a:has-text("Exames em aberto")').first()
  await link.click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
}

async function lerSituacaoGuia(page, numeroGuia) {
  const row = await rowByGuia(page, numeroGuia)
  if (row) {
    const text = await row.innerText()
    return pickValue(text, /Situa[cç][aã]o:\s*([^\n\t]+)/i)
      ?? pickValue(text, /Status:\s*([^\n\t]+)/i)
      ?? text.split(/\r?\n/).map((line) => line.trim()).find((line) => /Autorizado|Em execução|Em estudo|Em Análise|Negado|Cancelado/i.test(line))
      ?? null
  }

  const body = await page.locator('body').innerText({ timeout: DEFAULT_TIMEOUT }).catch(() => '')
  if (!body.includes(String(numeroGuia))) {
    return null
  }

  return pickValue(body, /Situa[cç][aã]o:\s*([^\n\t]+)/i)
    ?? pickValue(body, /Status:\s*([^\n\t]+)/i)
}

async function abrirGuiaEmExamesAbertos(page, guia) {
  const row = await rowByGuia(page, guia.numero_guia)
  if (!row) {
    return false
  }

  const text = await row.innerText()
  const card = String(guia.paciente?.carteirinha ?? '').replace(/\D/g, '')
  if (card && !text.replace(/\D/g, '').includes(card.slice(-6))) {
    return false
  }

  await row.locator('a, button').filter({ hasText: String(guia.numero_guia) }).first().click({ timeout: DEFAULT_TIMEOUT })
    .catch(async () => row.locator('a, button').first().click({ timeout: DEFAULT_TIMEOUT }))
  await waitProcessing(page)
  return true
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

async function irProximaPagina(page) {
  const next = page.locator('a:has-text("Próxima"), a:has-text("Proxima"), [data-next-page]').first()
  if (!await next.isVisible({ timeout: 500 }).catch(() => false)) {
    return false
  }

  await next.click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
  return true
}

async function voltarInicioSePossivel(page) {
  const link = page.locator('#home, a:has-text("Início"), a:has-text("Inicio")').first()
  if (await link.isVisible({ timeout: 500 }).catch(() => false)) {
    await link.click({ timeout: DEFAULT_TIMEOUT })
    await waitProcessing(page)
  }
}

async function voltarExamesAbertos(page) {
  const link = page.locator('#voltar-exames-abertos, a:has-text("Exames em aberto")').first()
  if (await link.isVisible({ timeout: 500 }).catch(() => false)) {
    await link.click({ timeout: DEFAULT_TIMEOUT })
    await waitProcessing(page)
  }
}

async function readInputOrText(page, selector) {
  const locator = page.locator(selector).first()
  if (!await locator.isVisible({ timeout: 500 }).catch(() => false)) {
    return null
  }

  return await locator.inputValue({ timeout: 500 }).catch(() => locator.innerText({ timeout: DEFAULT_TIMEOUT }))
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

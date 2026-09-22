import { chromium } from 'playwright'
import {
  DEFAULT_TIMEOUT,
  WorkerResultError,
  abrirBeneficiario,
  atualizarCadastroSeNecessario,
  login,
  mapPortalStatus,
  normalize,
  parseNumber,
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
      const viaCadastro = await localizarGuiaPorCadastro(page, guia)
      if (!viaCadastro) {
        return itemResult(guia, {
          status: 'failed',
          error_code: 'GUIA_NOT_FOUND',
          message: 'Guia não encontrada em Exames em aberto nem via cadastro de beneficiário.',
          conclusivo: false,
        })
      }

      return itemResult(guia, {
        status: 'succeeded',
        portal_status: viaCadastro.situacao,
        guia_status: viaCadastro.guia_status,
        unimed_status: viaCadastro.situacao,
        status_operadora: viaCadastro.situacao,
        conclusivo: viaCadastro.guia_status !== 'under_review',
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
      // Mesmo fallback que consultarStatusGuia ja usa pra achar status —
      // achado ao vivo em 22/09/2026, guia 50144774682: guia Autorizada que
      // saiu de "Exames em aberto" (mesma classe do caso Negado/Cancelado
      // documentado em localizarGuiaPorCadastro) continuava tendo
      // senha/validade/sessoes legiveis via Localizar Guia -> clicar na
      // guia, so que numa tela so-leitura diferente (ver
      // tentarLerDetalheGuiaPorCadastro).
      const viaCadastro = await localizarGuiaPorCadastro(page, guia)
      if (!viaCadastro?.senha) {
        return itemResult(guia, {
          status: 'failed',
          error_code: 'NOT_FOUND_IN_OPEN_EXAMS',
          message: 'Guia não encontrada em Exames em aberto.',
        })
      }

      return itemResult(guia, {
        status: 'succeeded',
        senha: viaCadastro.senha,
        ...(viaCadastro.validade_senha ? { validade_senha: viaCadastro.validade_senha } : {}),
        ...(viaCadastro.sessoes_solicitadas !== undefined ? { sessoes_solicitadas: viaCadastro.sessoes_solicitadas } : {}),
        ...(viaCadastro.sessoes_autorizadas !== undefined ? { sessoes_autorizadas: viaCadastro.sessoes_autorizadas } : {}),
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
      // A mesma tela de execucao ja traz QT_SOLIC_1/QT_AUTORIZADA_1 — nao ha
      // motivo pra deixar de repassar so porque esta operacao foi pensada
      // originalmente so pra senha/validade (achado ao vivo 22/09/2026, guia
      // 50144652656: leu os dois campos e descartou, guia ficou com
      // sessoes=0 mesmo autorizada e sem outra automacao elegivel pra
      // corrigir depois).
      ...(dados.qtSolicitadas !== null ? { sessoes_solicitadas: dados.qtSolicitadas } : {}),
      ...(dados.qtAutorizadas !== null ? { sessoes_autorizadas: dados.qtAutorizadas } : {}),
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
  // fillIfVisible so tenta se o campo ja estiver visivel dentro de 500ms —
  // curto demais quando esta funcao roda em sequencia (consultarStatusBatch
  // chama isto uma vez por guia, na MESMA page) e a tela ainda esta
  // terminando de voltar da guia anterior. Achado ao vivo em 10/09/2026: a
  // 2a guia de uma consulta de 3 veio GUIA_NOT_FOUND porque o preenchimento
  // foi pulado em silencio e o filtro rodou vazio/com valor antigo. .fill()
  // direto espera ate DEFAULT_TIMEOUT pelo campo ficar acionavel e lanca se
  // nao conseguir — melhor um erro visivel do que um "nao encontrada" falso.
  await page.locator('[name="s_nr_guia"]').fill(String(numeroGuia ?? ''), { timeout: DEFAULT_TIMEOUT })
  await page.locator('[name="Button_FIltro"]').first().click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
  // Achado ao vivo em 10/09/2026: waitProcessing() so espera o texto
  // "Processando..." sumir, mas o postback do filtro pode reescrever a
  // tabela DEPOIS disso (o indicador some antes do DOM terminar de
  // atualizar). rowByGuia lia a tabela stale (ainda com os 179 registros
  // nao filtrados) e so achava a guia se ela por acaso estivesse na
  // primeira pagina — 2 de 3 guias reais de uma mesma solicitacao vieram
  // GUIA_NOT_FOUND por isso (usuario confirmou ao vivo no portal que
  // estavam la, autorizadas). Esperar o rotulo "exame(s) encontrado(s)"
  // aparecer e o sinal real de que o filtro processou.
  await page
    .getByText(/exame\(s\) encontrado\(s\)/)
    .first()
    .waitFor({ state: 'visible', timeout: DEFAULT_TIMEOUT })
    .catch(() => {})

  const row = await rowByGuia(page, numeroGuia)
  if (!row) {
    return false
  }

  await row.locator('a').first().click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
  await page.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT }).catch(() => {})

  return true
}

/**
 * Fallback quando a guia não aparece em "Exames em aberto" (achado ao vivo
 * 31/08/2026: guias Negadas de Laura de Faveri e Miguel Ribeiro Machado
 * apareciam como "Em análise" no gescon mas "Guia não encontrada" na
 * consulta normal). Guias que mudam para Negado/Cancelado saem dessa lista,
 * mas continuam localizáveis pelo mesmo caminho de cadastro de beneficiário
 * usado por gerarGuia (abrirBeneficiario -> preencherCarteirinha ->
 * atualizarCadastroSeNecessario) — só que a tela seguinte ("Localizar
 * Guia": campo `s_NR_GUIA` + `Button_Filtro`, diferente do
 * `s_nr_guia`/`Button_FIltro` de Exames em aberto) mostra o status real por
 * ícone (ex.: `ico16negado.gif` = Negado) em vez de seguir para a
 * Digitação de guia SP/SADT. Devolve null (sem lançar) em qualquer
 * impossibilidade — carteirinha ausente, restrição administrativa, guia
 * também não encontrada aqui — para que o chamador caia de volta no
 * GUIA_NOT_FOUND normal.
 */
async function localizarGuiaPorCadastro(mainPage, guia) {
  const carteirinha = guia.paciente?.carteirinha
  if (!carteirinha) {
    return null
  }

  let card
  try {
    card = splitCarteirinha(carteirinha)
  } catch {
    return null
  }

  let popup
  try {
    popup = await abrirBeneficiario(mainPage)
  } catch {
    return null
  }

  try {
    await preencherCarteirinha(popup, card)

    if (await textoRestricao(popup)) {
      return null
    }

    await atualizarCadastroSeNecessario(popup)

    await popup.locator('#s_NR_GUIA, [name="s_NR_GUIA"]').fill(String(guia.numero_guia), { timeout: DEFAULT_TIMEOUT })
    await popup.locator('[name="Button_Filtro"]').first().click({ timeout: DEFAULT_TIMEOUT })
    await waitProcessing(popup)
    await popup.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT }).catch(() => {})
    // Mesma race do abrirGuiaPorFiltro acima — o postback pode terminar
    // depois do indicador "Processando..." sumir.
    await popup
      .getByText(/exame\(s\) encontrado\(s\)/)
      .first()
      .waitFor({ state: 'visible', timeout: DEFAULT_TIMEOUT })
      .catch(() => {})

    const row = await rowByGuia(popup, guia.numero_guia)
    if (!row) {
      return null
    }

    const texto = await row.innerText().catch(() => '')
    const iconSrc = await row.locator('img').first().getAttribute('src').catch(() => null)
    const situacao = situacaoDaLinha(texto, iconSrc)
    const detalhe = await tentarLerDetalheGuiaPorCadastro(popup, row)

    return { situacao, guia_status: mapPortalStatus(situacao), ...detalhe }
  } catch {
    return null
  } finally {
    await popup.close().catch(() => {})
  }
}

/**
 * Depois de achar a linha em "Localizar Guia", clica pra dentro pra tentar
 * ler senha/validade/sessões — achado ao vivo em 22/09/2026, guia
 * 50144774682: essa tela de destino (nova.do) é só leitura, sem os
 * `<input name="NR_SENHA">` de "Exames em aberto". Os mesmos dados aparecem
 * como texto solto ao lado do rótulo ("Senha de autorização:", "Validade da
 * senha:" via `#CampoValidadeSenha`, "Qt. Solic."/"Qt. Autoriz." na tabela
 * de procedimentos `#tlinhas`). Melhor-esforço: qualquer falha aqui (sem
 * link clicável — caso de guias Negadas, por exemplo — ou tela diferente do
 * esperado) devolve `{}` e quem chama continua só com o status, que é o
 * comportamento de sempre.
 */
async function tentarLerDetalheGuiaPorCadastro(popup, row) {
  try {
    const link = row.locator('a').first()
    if ((await link.count()) === 0) {
      return {}
    }

    await link.click({ timeout: DEFAULT_TIMEOUT })
    await waitProcessing(popup)
    await popup.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT }).catch(() => {})

    const nrSenha = await valorAoLadoDoRotulo(popup, 'Senha de autorização:')
    const dtValidadeSenha = normalizeDate(await valorAoLadoDoRotulo(popup, 'Validade da senha:'))
    const linhaItem = popup.locator('#tlinhas tr.it').first()
    const qtSolicitadas = parseNumber(await linhaItem.locator('td').nth(4).textContent().catch(() => null))
    const qtAutorizadas = parseNumber(await linhaItem.locator('td').nth(5).textContent().catch(() => null))

    return {
      ...(nrSenha ? { senha: nrSenha } : {}),
      ...(dtValidadeSenha ? { validade_senha: dtValidadeSenha } : {}),
      ...(qtSolicitadas !== null ? { sessoes_solicitadas: qtSolicitadas } : {}),
      ...(qtAutorizadas !== null ? { sessoes_autorizadas: qtAutorizadas } : {}),
    }
  } catch {
    return {}
  }
}

async function valorAoLadoDoRotulo(popup, rotulo) {
  const valor = await popup
    .locator(`td.MagnetoFieldCaptionTD:has-text("${rotulo}") + td`)
    .first()
    .textContent()
    .catch(() => null)

  return valor && valor.trim() !== '' ? valor.trim().replace(/\s+/g, ' ') : null
}

const STATUS_CONHECIDOS = ['Negado', 'Autorizado', 'Em execução', 'Cancelado', 'Em análise', 'Pendente']

function situacaoDaLinha(texto, iconSrc) {
  const encontrado = STATUS_CONHECIDOS.find((status) => normalize(texto).includes(normalize(status)))
  if (encontrado) {
    return encontrado
  }

  const rotuloIcone = rotuloDoIcone(iconSrc)
  if (rotuloIcone) {
    return rotuloIcone.charAt(0).toUpperCase() + rotuloIcone.slice(1)
  }

  return texto.trim() || 'Desconhecido'
}

// Sem rótulo de texto confiável ao lado do ícone (o `title`/`alt` do
// `<img>` vêm vazios no portal real) — deriva um rótulo a partir do nome do
// arquivo (ex.: "ico16negado.gif" -> "negado"), que alimenta o mesmo
// mapPortalStatus() usado em todo o resto do worker.
function rotuloDoIcone(src) {
  const arquivo = String(src ?? '').split('/').pop() ?? ''
  return arquivo
    .replace(/\.[a-z]+$/i, '')
    .replace(/^ico\d*/i, '')
    .replace(/[_-]+/g, ' ')
    .trim()
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

import { chromium } from 'playwright'
import { DEFAULT_TIMEOUT, WorkerResultError, login, mapPortalStatus } from '../portal.js'
import { abrirExamesAbertos, lerDadosExecucaoGuia, valorCampo } from './statusSenha.js'

const MAX_PAGINAS = 15

export async function executarConfirmarGuiaIncerta(request, options = {}) {
  const browser = options.page ? null : await chromium.launch({ headless: true })
  const page = options.page ?? await browser.newPage()

  try {
    return await confirmarGuiaIncerta(page, request)
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

/**
 * Confirma se uma guia marcada 'uncertain' (UNCERTAIN_AFTER_SUBMIT, sem
 * numero_guia conhecido — o Finalizar rodou mas o worker nao conseguiu ler a
 * confirmacao de volta) foi de fato criada no portal, buscando por paciente
 * em "Exames em aberto" em vez de por numero de guia. Abrir a tela sem
 * preencher s_nr_guia ja lista os exames em aberto reais, paginados —
 * confirmado ao vivo em producao em 26/08/2026 (11 paginas varridas contra
 * rda.unimedsc.com.br). So entra numa guia quando o texto da linha bate com
 * os ultimos 6 digitos da carteirinha do paciente (mesma tecnica do script
 * de diagnostico scripts/checar-guia-paciente.js, promovida aqui pra ler os
 * dados da guia, nao so confirmar a existencia).
 */
async function confirmarGuiaIncerta(page, request) {
  const payload = request.payload ?? {}
  const cardLast6 = String(payload.paciente?.carteirinha ?? '').replace(/\D/g, '').slice(-6)

  if (!cardLast6) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'CONFIGURATION_INVALID_CARD',
      message: 'Carteirinha do paciente ausente ou inválida no payload.',
    })
  }

  await login(page, payload.credential ?? {})
  await abrirExamesAbertos(page)

  const linhaEncontrada = await buscarLinhaPorPaciente(page, cardLast6)

  if (!linhaEncontrada) {
    return {
      status: 'succeeded',
      execution_id: request.executionId ?? null,
      encontrada: false,
    }
  }

  await linhaEncontrada.locator('a').first().click({ timeout: DEFAULT_TIMEOUT })
  await page.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT }).catch(() => {})

  const numeroGuia = await valorCampo(page, 'NR_GUIA')
  const dados = await lerDadosExecucaoGuia(page)
  const autorizada = Boolean(dados.dtAutorizacao && dados.nrSenha)
  const situacao = autorizada ? 'Autorizado' : 'Em análise'

  return {
    status: 'succeeded',
    execution_id: request.executionId ?? null,
    encontrada: true,
    numero_guia: numeroGuia,
    situacao_portal: situacao,
    unimed_status: situacao,
    guia_status: mapPortalStatus(situacao),
    senha: dados.nrSenha,
    validade_senha: dados.dtValidadeSenha,
    ...(dados.qtSolicitadas !== null ? { sessoes_solicitadas: dados.qtSolicitadas } : {}),
    ...(dados.qtAutorizadas !== null ? { sessoes_autorizadas: dados.qtAutorizadas } : {}),
  }
}

async function buscarLinhaPorPaciente(page, cardLast6) {
  let paginaAtual = 1

  while (paginaAtual <= MAX_PAGINAS) {
    const rows = page.locator('table tr')
    const count = await rows.count()

    for (let index = 0; index < count; index += 1) {
      const row = rows.nth(index)
      const text = await row.innerText().catch(() => '')
      const digits = text.replace(/\D/g, '')
      if (digits.includes(cardLast6)) {
        return row
      }
    }

    const proxima = page.locator('a:has-text("Próxima"), a:has-text("Proxima"), [data-next-page]').first()
    const temProxima = await proxima.isVisible({ timeout: 500 }).catch(() => false)
    if (!temProxima) {
      break
    }

    await proxima.click({ timeout: DEFAULT_TIMEOUT })
    await page.waitForTimeout(1000)
    paginaAtual += 1
  }

  return null
}

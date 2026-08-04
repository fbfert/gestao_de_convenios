import { chromium } from 'playwright'
import {
  DEFAULT_TIMEOUT,
  WorkerResultError,
  abrirBeneficiario,
  atualizarCadastroSeNecessario,
  fillIfVisible,
  hasText,
  login,
  mapPortalStatus,
  parseNumber,
  pickValue,
  preencherCarteirinha,
  selectIfVisible,
  splitCarteirinha,
  textoRestricao,
  waitProcessing,
  normalize,
} from '../portal.js'

const ACCEPTED_UPLOAD_EXTENSIONS = new Set(['jpg', 'gif', 'doc', 'jpeg', 'xls', 'png', 'zip', 'pdf'])
const MAX_UPLOAD_SIZE = 5 * 1024 * 1024

export async function executarGerarGuia(request, options = {}) {
  const browser = options.page ? null : await chromium.launch({ headless: true })
  const page = options.page ?? await browser.newPage()

  try {
    return await gerarGuia(page, request)
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

export async function gerarGuia(page, request) {
  const payload = request.payload ?? {}
  const credential = payload.credential ?? {}
  const medico = payload.medico ?? {}
  const card = splitCarteirinha(payload.paciente?.carteirinha)

  await login(page, credential)
  await abrirBeneficiario(page)
  await preencherCarteirinha(page, card)

  const restriction = await textoRestricao(page)
  if (restriction) {
    return {
      status: 'succeeded',
      execution_id: request.executionId ?? null,
      guia_status: 'needs_verification',
      status_guia: 'needs_verification',
      numero_guia: null,
      unimed_status: restriction,
      status_operadora: restriction,
      message: 'Beneficiario com restricao administrativa.',
    }
  }

  await atualizarCadastroSeNecessario(page)
  await abrirSpSadt(page)
  await preencherFormularioPrincipal(page, payload)
  const estrategiaMedico = await selecionarPrestador(page, medico)
  await preencherProcedimento(page, payload)
  await enviarAnexos(page, payload)
  await selecionarProfissionalExecutante(page, payload)

  return await finalizar(page, request, estrategiaMedico)
}

async function abrirSpSadt(page) {
  await page.locator('#sp-sadt').click({ timeout: DEFAULT_TIMEOUT })
  await page.locator('#solicitacao-manual').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
}

async function preencherFormularioPrincipal(page, payload) {
  await fillIfVisible(page, '[name="DT_EMISSAO_GUIA"], #DT_EMISSAO_GUIA', formatPortalDate(new Date()))
  await selectIfVisible(page, '[name="FG_ATENDIMENTO_RN"], #FG_ATENDIMENTO_RN', 'N')
  await selectIfVisible(page, '[name="DM_CARATER_SOLIC"], #DM_CARATER_SOLIC', '1')
  await selectIfVisible(page, '[name="DM_TP_ATEND_SADT"], #DM_TP_ATEND_SADT', '03')
  await selectIfVisible(page, '[name="DM_TP_ACIDENTE"], #DM_TP_ACIDENTE', '9')
  await fillIfVisible(page, '[name="DS_INDIC_CLINICA"], #DS_INDIC_CLINICA', String(payload.cid ?? ''))
}

async function selecionarPrestador(page, medico) {
  await page.locator('#link_busca_contrt').click({ timeout: DEFAULT_TIMEOUT })

  const crm = String(medico.crm ?? '').trim()
  if (crm) {
    await fillIfVisible(page, '[name="s_nr_crm"], #s_nr_crm', crm)
    await page.locator('[name="Button_DoSearch"]').click({ timeout: DEFAULT_TIMEOUT })
    await waitProcessing(page)
    if (await escolherPrestadorAtivo(page, medico.nome)) {
      return 'crm'
    }
  }

  await refazerPesquisa(page)
  await fillIfVisible(page, '[name="s_nr_crm"], #s_nr_crm', '')
  await fillIfVisible(page, '[name="s_nm_prestador"], #s_nm_prestador', String(medico.nome ?? ''))
  await page.locator('[name="Button_DoSearch"]').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
  if (await escolherPrestadorAtivo(page, medico.nome)) {
    return 'nome'
  }

  await refazerPesquisa(page)
  await fillIfVisible(page, '[name="s_nr_crm"], #s_nr_crm', '')
  await fillIfVisible(page, '[name="s_nm_prestador"], #s_nm_prestador', 'nao cooperado')
  await page.locator('[name="Button_DoSearch"]').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
  if (await escolherPrestadorAtivo(page, 'MEDICO NAO COOPERADO')) {
    return 'nao_cooperado'
  }

  throw new WorkerResultError({
    status: 'failed',
    error_code: 'PRESTADOR_SOLICITANTE_NOT_FOUND',
    message: 'Prestador solicitante ativo não encontrado.',
  })
}

async function preencherProcedimento(page, payload) {
  const codigo = String(payload.codigo_procedimento ?? '').trim()
  if (!codigo) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'CONFIGURATION_INVALID_ITEM',
      message: 'Código de procedimento ausente no payload.',
    })
  }

  await page.locator('[name="CD_ITEM_1"], #CD_ITEM_1').fill(codigo, { timeout: DEFAULT_TIMEOUT })
  await page.keyboard.press('Tab').catch(() => {})
  await waitProcessing(page)

  const quantidade = String(payload.quantidade ?? payload.quantidade_padrao ?? 1)
  await fillIfVisible(page, '[name="NR_QTD_1"], #NR_QTD_1', quantidade)

  const genericDescription = page.locator('[name="DS_ITEM_GENERICO_1"], #DS_ITEM_GENERICO_1')
  if (await genericDescription.isVisible({ timeout: 500 }).catch(() => false)) {
    const descricao = payload.valor_generico || payload.descricao_operadora || codigo
    await genericDescription.fill(String(descricao), { timeout: DEFAULT_TIMEOUT })
    await fillIfVisible(page, '[name="VL_ITEM_GENERICO_1"], #VL_ITEM_GENERICO_1', '0,01')
  }
}

async function enviarAnexos(page, payload) {
  const anexos = normalizarAnexos(payload)
  const pedido = anexos.find((anexo) => anexo.tipo === 'pedido_medico')
  if (!pedido) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'PEDIDO_MEDICO_REQUIRED',
      message: 'Pedido Médico obrigatório não informado.',
    })
  }

  for (const anexo of anexos) {
    validarAnexo(anexo)
    await uploadAnexo(page, anexo)
  }
}

async function selecionarProfissionalExecutante(page, payload) {
  const codigo = String(payload.codigo_profissional_operadora ?? '').trim()
  if (!codigo) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'CONFIGURATION_INVALID_EXECUTOR',
      message: 'Código do profissional executante ausente no payload.',
    })
  }

  const select = page.locator('[name="CD_PROFISSIONAL"], #CD_PROFISSIONAL')
  await select.selectOption(codigo, { timeout: DEFAULT_TIMEOUT }).catch(() => {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'PROFISSIONAL_EXECUTANTE_NOT_FOUND',
      message: 'Profissional executante não encontrado no portal.',
    })
  })
}

async function finalizar(page, request, estrategiaMedico) {
  const finalize = page.locator('[name="Button_Finalizar"]')
  await finalize.click({ timeout: DEFAULT_TIMEOUT })

  try {
    await waitProcessing(page)
    await page.locator('#resultado-guia, [data-result="guia"]').waitFor({ timeout: DEFAULT_TIMEOUT })
  } catch {
    return {
      status: 'uncertain',
      execution_id: request.executionId ?? null,
      error_code: 'UNCERTAIN_AFTER_SUBMIT',
      message: 'Resultado ambíguo após finalizar guia. Não houve retry automático.',
    }
  }

  const result = await parseResultado(page)

  return {
    status: 'succeeded',
    execution_id: request.executionId ?? null,
    ...result,
    medico_strategy: estrategiaMedico,
  }
}

async function parseResultado(page) {
  const text = await page.locator('#resultado-guia, [data-result="guia"]').innerText({ timeout: DEFAULT_TIMEOUT })
  const statusOperadora = pickValue(text, /Situa[cç][aã]o:\s*([^\n]+)/i) ?? ''

  return {
    numero_guia: pickValue(text, /Guia:\s*([A-Za-z0-9.-]+)/i),
    protocolo_operadora: pickValue(text, /Protocolo:\s*([A-Za-z0-9.-]+)/i),
    sessoes_solicitadas: parseNumber(pickValue(text, /Qtd:\s*(\d+)/i)),
    sessoes_autorizadas: parseNumber(pickValue(text, /Qtd Aut:\s*(\d+)/i)),
    senha: pickValue(text, /Senha:\s*([A-Za-z0-9.-]+)/i),
    unimed_status: statusOperadora,
    status_operadora: statusOperadora,
    guia_status: mapPortalStatus(statusOperadora),
  }
}

async function escolherPrestadorAtivo(page, nomeEsperado) {
  const rows = page.locator('[data-prestador-row], table tr')
  const count = await rows.count()
  const expected = normalize(String(nomeEsperado ?? ''))

  for (let index = 0; index < count; index += 1) {
    const row = rows.nth(index)
    const text = await row.innerText().catch(() => '')
    const normalized = normalize(text)
    const active = normalized.includes('OK - ATIVO') || normalized.includes('OK ATIVO')
    const nameMatches = !expected || normalized.includes(expected)

    if (active && nameMatches) {
      await row.locator('button, a, input[type="button"]').first().click({ timeout: DEFAULT_TIMEOUT })
      await waitProcessing(page)
      return true
    }
  }

  return false
}

async function refazerPesquisa(page) {
  const refazer = page.locator('#refazer-pesquisa').first()
  if (await refazer.isVisible({ timeout: 500 }).catch(() => false)) {
    await refazer.click({ timeout: DEFAULT_TIMEOUT })
  }
}

async function uploadAnexo(page, anexo) {
  const input = page.locator(`input[type="file"][data-tipo="${anexo.tipo}"], input[type="file"]`).first()
  const path = anexo.path ?? anexo.local_path

  if (path && !path.startsWith('fixture:')) {
    await input.setInputFiles(path, { timeout: DEFAULT_TIMEOUT })
  } else {
    await page.evaluate((item) => {
      if (window.__forceUploadFailure) {
        return
      }
      window.__uploadedFiles = window.__uploadedFiles || []
      window.__uploadedFiles.push(item)
      const list = document.querySelector('#uploaded-files')
      if (list) {
        const li = document.createElement('li')
        li.textContent = item.nome_original || item.tipo
        li.dataset.tipo = item.tipo
        list.appendChild(li)
      }
    }, anexo)
  }

  await waitProcessing(page)
  const confirmedByType = await page.locator(`#uploaded-files [data-tipo="${anexo.tipo}"]`)
    .first()
    .isVisible({ timeout: DEFAULT_TIMEOUT })
    .catch(() => false)
  const confirmedByName = await page.getByText(anexo.nome_original ?? anexo.tipo)
    .first()
    .isVisible({ timeout: 500 })
    .catch(() => false)
  const confirmed = confirmedByType || confirmedByName

  if (!confirmed) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: anexo.tipo === 'pedido_medico' ? 'PEDIDO_MEDICO_UPLOAD_FAILED' : 'UPLOAD_FAILED',
      message: `Upload não confirmado para ${anexo.tipo}.`,
    })
  }
}

function normalizarAnexos(payload) {
  const anexos = []
  if (payload.pedido_medico) anexos.push({ ...payload.pedido_medico, tipo: 'pedido_medico' })
  for (const anexo of payload.anexos ?? []) {
    anexos.push(anexo)
  }
  return anexos.filter(Boolean)
}

function validarAnexo(anexo) {
  const name = String(anexo.nome_original ?? anexo.path ?? '')
  const extension = name.includes('.') ? name.split('.').pop().toLowerCase() : ''
  if (extension && !ACCEPTED_UPLOAD_EXTENSIONS.has(extension)) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'UPLOAD_TYPE_NOT_ALLOWED',
      message: `Tipo de arquivo não aceito para ${anexo.tipo}.`,
    })
  }

  if (Number(anexo.size ?? 0) > MAX_UPLOAD_SIZE) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'UPLOAD_TOO_LARGE',
      message: `Arquivo maior que 5 MB para ${anexo.tipo}.`,
    })
  }
}

function formatPortalDate(date) {
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  return `${day}/${month}/${date.getFullYear()}`
}

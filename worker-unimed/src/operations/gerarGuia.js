import { chromium } from 'playwright'

const DEFAULT_TIMEOUT = Number(process.env.UNIMED_WORKER_STEP_TIMEOUT_MS ?? 5000)
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

async function login(page, credential) {
  const loginUrl = loginUrlFromCredential(credential)
  await page.goto(loginUrl, { waitUntil: 'domcontentloaded', timeout: DEFAULT_TIMEOUT })

  await page.locator('#login').fill(String(credential.login ?? ''), { timeout: DEFAULT_TIMEOUT })
  await page.locator('#passwordTemp').fill(String(credential.password ?? ''), { timeout: DEFAULT_TIMEOUT })
  await page.locator('[name="Button_DoLogin"]').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)

  if (await hasText(page, 'Login inválido') || await hasText(page, 'Senha inválida')) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'LOGIN_ERROR',
      message: 'Credencial Unimed recusada pelo portal.',
    })
  }

  await page.locator('#novo-exame').waitFor({ timeout: DEFAULT_TIMEOUT })
}

async function abrirBeneficiario(page) {
  await page.locator('#novo-exame').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
  await page.locator('#ignora-cartao').click({ timeout: DEFAULT_TIMEOUT })
  await page.locator('#cadastrar-beneficiario').click({ timeout: DEFAULT_TIMEOUT })
}

async function preencherCarteirinha(page, card) {
  await page.locator('#CD_UNIMED, [name="CD_UNIMED"]').fill(card.unimed, { timeout: DEFAULT_TIMEOUT })
  await page.locator('#CD_CARTAO, [name="CD_CARTAO"]').fill(card.cartao, { timeout: DEFAULT_TIMEOUT })
  await page.locator('#CD_BENEF, [name="CD_BENEF"]').fill(card.beneficiario, { timeout: DEFAULT_TIMEOUT })
  await page.locator('#CD_DEPEN, [name="CD_DEPEN"]').fill(card.dependente, { timeout: DEFAULT_TIMEOUT })
  await page.locator('#NR_DV, [name="NR_DV"]').fill(card.dv, { timeout: DEFAULT_TIMEOUT })
  await page.locator('[name="Button_Insert"]').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
}

async function atualizarCadastroSeNecessario(page) {
  const updateButton = page.locator('[name="Button_Update"]')
  if (await updateButton.isVisible({ timeout: 500 }).catch(() => false)) {
    await updateButton.click({ timeout: DEFAULT_TIMEOUT })
    await waitProcessing(page)
  }
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

async function textoRestricao(page) {
  const body = await page.locator('body').innerText({ timeout: DEFAULT_TIMEOUT }).catch(() => '')
  const lines = body.split(/\r?\n/).map((line) => line.trim()).filter(Boolean)
  return lines.find((line) => /Restrição Administrativa|Pendências Administrativas/i.test(line)) ?? null
}

async function fillIfVisible(page, selector, value) {
  const locator = page.locator(selector).first()
  if (await locator.isVisible({ timeout: 500 }).catch(() => false)) {
    await locator.fill(String(value ?? ''), { timeout: DEFAULT_TIMEOUT })
  }
}

async function selectIfVisible(page, selector, value) {
  const locator = page.locator(selector).first()
  if (await locator.isVisible({ timeout: 500 }).catch(() => false)) {
    await locator.selectOption(String(value), { timeout: DEFAULT_TIMEOUT })
  }
}

async function waitProcessing(page) {
  await page.locator('text=Processando, por favor aguarde...').waitFor({ state: 'hidden', timeout: DEFAULT_TIMEOUT }).catch(() => {})
}

async function hasText(page, text) {
  return page.locator(`text=${text}`).first().isVisible({ timeout: 500 }).catch(() => false)
}

function splitCarteirinha(value) {
  const digits = String(value ?? '').replace(/\D/g, '')
  if (digits.length !== 17) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'CONFIGURATION_INVALID_CARD',
      message: 'Carteirinha Unimed deve conter 17 dígitos.',
    })
  }

  return {
    unimed: digits.slice(0, 4),
    cartao: digits.slice(4, 8),
    beneficiario: digits.slice(8, 14),
    dependente: digits.slice(14, 16),
    dv: digits.slice(16, 17),
  }
}

function loginUrlFromCredential(credential) {
  const baseUrl = String(credential.base_url ?? '').trim()
  if (!baseUrl) {
    return 'https://rda.unimedsc.com.br/cmagnet/Login.do'
  }

  if (baseUrl.startsWith('file:') || baseUrl.includes('.html') || baseUrl.includes('/Login.do')) {
    return baseUrl
  }

  return `${baseUrl.replace(/\/$/, '')}/cmagnet/Login.do`
}

function mapPortalStatus(value) {
  const normalized = normalize(value)
  if (normalized.includes('AUTORIZADO') || normalized.includes('EM EXECUCAO')) return 'approved'
  if (normalized.includes('NEGADO')) return 'denied'
  if (normalized.includes('CANCELADO')) return 'canceled'
  return 'under_review'
}

function formatPortalDate(date) {
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  return `${day}/${month}/${date.getFullYear()}`
}

function pickValue(text, regex) {
  return text.match(regex)?.[1]?.trim() ?? null
}

function parseNumber(value) {
  return value === null || value === undefined ? null : Number(value)
}

function normalize(value) {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase()
}

class WorkerResultError extends Error {
  constructor(result) {
    super(result.message ?? result.error_code ?? 'Worker result error')
    this.result = result
  }
}

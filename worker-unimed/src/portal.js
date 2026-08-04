export const DEFAULT_TIMEOUT = Number(process.env.UNIMED_WORKER_STEP_TIMEOUT_MS ?? 5000)

export async function login(page, credential) {
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

export async function abrirBeneficiario(page) {
  await page.locator('#novo-exame').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
  await page.locator('#ignora-cartao').click({ timeout: DEFAULT_TIMEOUT })
  await page.locator('#cadastrar-beneficiario').click({ timeout: DEFAULT_TIMEOUT })
}

export async function preencherCarteirinha(page, card) {
  await page.locator('#CD_UNIMED, [name="CD_UNIMED"]').fill(card.unimed, { timeout: DEFAULT_TIMEOUT })
  await page.locator('#CD_CARTAO, [name="CD_CARTAO"]').fill(card.cartao, { timeout: DEFAULT_TIMEOUT })
  await page.locator('#CD_BENEF, [name="CD_BENEF"]').fill(card.beneficiario, { timeout: DEFAULT_TIMEOUT })
  await page.locator('#CD_DEPEN, [name="CD_DEPEN"]').fill(card.dependente, { timeout: DEFAULT_TIMEOUT })
  await page.locator('#NR_DV, [name="NR_DV"]').fill(card.dv, { timeout: DEFAULT_TIMEOUT })
  await page.locator('[name="Button_Insert"]').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
}

export async function atualizarCadastroSeNecessario(page) {
  const updateButton = page.locator('[name="Button_Update"]')
  if (await updateButton.isVisible({ timeout: 500 }).catch(() => false)) {
    await updateButton.click({ timeout: DEFAULT_TIMEOUT })
    await waitProcessing(page)
  }
}

export async function textoRestricao(page) {
  const body = await page.locator('body').innerText({ timeout: DEFAULT_TIMEOUT }).catch(() => '')
  const lines = body.split(/\r?\n/).map((line) => line.trim()).filter(Boolean)
  return lines.find((line) => /Restrição Administrativa|Pendências Administrativas/i.test(line)) ?? null
}

export async function fillIfVisible(page, selector, value) {
  const locator = page.locator(selector).first()
  if (await locator.isVisible({ timeout: 500 }).catch(() => false)) {
    await locator.fill(String(value ?? ''), { timeout: DEFAULT_TIMEOUT })
  }
}

export async function selectIfVisible(page, selector, value) {
  const locator = page.locator(selector).first()
  if (await locator.isVisible({ timeout: 500 }).catch(() => false)) {
    await locator.selectOption(String(value), { timeout: DEFAULT_TIMEOUT })
  }
}

export async function waitProcessing(page) {
  await page.locator('text=Processando, por favor aguarde...').waitFor({ state: 'hidden', timeout: DEFAULT_TIMEOUT }).catch(() => {})
}

export async function hasText(page, text) {
  return page.locator(`text=${text}`).first().isVisible({ timeout: 500 }).catch(() => false)
}

export function splitCarteirinha(value) {
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

export function loginUrlFromCredential(credential) {
  const baseUrl = String(credential.base_url ?? '').trim()
  if (!baseUrl) {
    return 'https://rda.unimedsc.com.br/cmagnet/Login.do'
  }

  if (baseUrl.startsWith('file:') || baseUrl.includes('.html') || baseUrl.includes('/Login.do')) {
    return baseUrl
  }

  return `${baseUrl.replace(/\/$/, '')}/cmagnet/Login.do`
}

export function mapPortalStatus(value) {
  const normalized = normalize(value)
  if (normalized.includes('AUTORIZADO') || normalized.includes('EM EXECUCAO')) return 'approved'
  if (normalized.includes('NEGADO')) return 'denied'
  if (normalized.includes('CANCELADO')) return 'canceled'
  return 'under_review'
}

export function pickValue(text, regex) {
  return text.match(regex)?.[1]?.trim() ?? null
}

export function parseNumber(value) {
  return value === null || value === undefined ? null : Number(value)
}

export function normalize(value) {
  return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase()
}

export class WorkerResultError extends Error {
  constructor(result) {
    super(result.message ?? result.error_code ?? 'Worker result error')
    this.result = result
  }
}

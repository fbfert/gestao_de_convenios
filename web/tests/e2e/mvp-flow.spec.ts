import { expect, test, type Page, type TestInfo } from '@playwright/test'

async function login(page: Page) {
  await page.goto('/login')
  await expect(page.getByTestId('login-page')).toBeVisible()
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  const loginResponse = page.waitForResponse((response) => {
    return response.request().method() === 'POST' && response.url().includes('/login')
  })

  await page.getByTestId('login-submit').click()

  const response = await loginResponse
  const responseBody = await response.text()

  expect(
    response.status(),
    `POST /login retornou ${response.status()} com corpo: ${responseBody}`,
  ).toBe(200)
  await expect(page).toHaveURL(/\/solicitacoes$/)
  await expect(page.getByTestId('shell-layout')).toBeVisible()
}

test('fluxo completo de negocio', async ({ page }, testInfo: TestInfo) => {
  await login(page)

  await page.goto('/solicitacoes')
  await expect(page.getByTestId('solicitacoes-page')).toBeVisible()
  await page.getByTestId('solicitacao-convenio').selectOption({ label: 'Unimed' })
  await page.getByTestId('solicitacao-especialidade').selectOption({ label: 'Fisioterapia' })
  await page
    .getByTestId('solicitacao-paciente')
    .selectOption({ label: 'Ana Paula Ribeiro · UNI-2026-0001 · Unimed' })
  await page
    .getByTestId('solicitacao-profissional')
    .selectOption({ label: 'Dra. Marina Tavares · Fisioterapia' })
  await page.getByTestId('solicitacao-medico').fill('Dr. Carlos Almeida')
  await page.getByTestId('solicitacao-submit').click()

  const solicitacaoRow = page.locator('[data-testid^="solicitacao-row-"]').first()
  await expect(solicitacaoRow).toBeVisible()
  await expect(solicitacaoRow).toContainText('Em análise')
  const solicitacaoId = Number((await solicitacaoRow.getAttribute('data-testid'))?.replace('solicitacao-row-', ''))
  await page.getByTestId(`solicitacao-aprovar-${solicitacaoId}`).click()
  await expect(page.getByTestId(`solicitacao-status-${solicitacaoId}`)).toContainText('Aprovada')

  await page.goto('/guias')
  await expect(page.getByTestId('guias-page')).toBeVisible()
  await page.getByTestId('guia-convenio').selectOption({ label: 'Unimed' })
  await page.getByTestId('guia-especialidade').selectOption({ label: 'Fisioterapia' })
  await page
    .getByTestId('guia-paciente')
    .selectOption({ label: 'Ana Paula Ribeiro · UNI-2026-0001' })
  await page.getByTestId('guia-profissional').selectOption({ label: 'Dra. Marina Tavares' })
  await page.getByTestId('guia-numero').fill(`GUIA-E2E-${Date.now()}`)
  await page.getByTestId('guia-tipo-terapia').selectOption('especializada')
  await page.getByTestId('guia-submit').click()

  const guideRow = page.locator('[data-testid^="guia-row-"]').first()
  await expect(guideRow).toBeVisible()
  const guideId = Number((await guideRow.getAttribute('data-testid'))?.replace('guia-row-', ''))
  await page.getByTestId(`guia-finalizar-${guideId}`).click()
  await page.getByTestId(`guia-senha-${guideId}`).fill('ABC123')
  await page.screenshot({
    path: testInfo.outputPath(`guia-finalizar-${guideId}-pre-submit.png`),
    fullPage: true,
  })

  const finalizeResponsePromise = page.waitForResponse((response) => {
    return response.request().method() === 'PATCH' && response.url().includes(`/guias/${guideId}/finalizar`)
  })

  await page.getByTestId(`guia-finalizar-confirmar-${guideId}`).click()

  const finalizeResponse = await finalizeResponsePromise
  const finalizeResponseText = await finalizeResponse.text()
  console.log(
    `[E2E] PATCH /guias/${guideId}/finalizar => ${finalizeResponse.status()} :: ${finalizeResponseText}`,
  )

  await expect(
    finalizeResponse.status(),
    `PATCH /guias/${guideId}/finalizar retornou ${finalizeResponse.status()} com corpo: ${finalizeResponseText}`,
  ).toBe(200)
  await expect(page.getByTestId(`guia-status-${guideId}`)).toHaveText('Finalizada')

  await page.goto('/antecipacoes')
  await expect(page.getByTestId('antecipacoes-page')).toBeVisible()
  await page.getByTestId('antecipacao-filtro-convenio').selectOption({ label: 'Unimed' })
  await page
    .getByTestId('antecipacao-filtro-paciente')
    .selectOption({ label: 'Ana Paula Ribeiro' })
  await page.getByRole('button', { name: 'Aplicar' }).click()
  const antecipacaoRow = page.locator('[data-testid^="antecipacao-row-"]').first()
  await expect(antecipacaoRow).toBeVisible()
  const antecipacaoId = Number(
    (await antecipacaoRow.getAttribute('data-testid'))?.replace('antecipacao-row-', ''),
  )
  await expect(page.locator(`[data-testid="antecipacao-cota-text-${antecipacaoId}"]`)).toHaveText(
    '0/1',
  )

  await page.goto(`/lancamentos?antecipacao_id=${antecipacaoId}`)
  await expect(page.getByTestId('lancamentos-page')).toBeVisible()
  await page.getByTestId('lancamento-profissional').selectOption({ label: 'Dra. Marina Tavares' })
  const lancamentoResponsePromise = page.waitForResponse((response) => {
    return (
      response.request().method() === 'POST' &&
      response.url().includes(`/antecipacoes/${antecipacaoId}/lancamentos`)
    )
  })

  await page.getByTestId('lancamento-submit').click()

  const lancamentoResponse = await lancamentoResponsePromise
  const lancamentoResponseText = await lancamentoResponse.text()
  console.log(
    `[E2E] POST /antecipacoes/${antecipacaoId}/lancamentos => ${lancamentoResponse.status()} :: ${lancamentoResponseText}`,
  )

  await expect(
    lancamentoResponse.status(),
    `POST /antecipacoes/${antecipacaoId}/lancamentos retornou ${lancamentoResponse.status()} com corpo: ${lancamentoResponseText}`,
  ).toBe(201)

  await page.goto('/antecipacoes')
  await page.getByTestId('antecipacao-filtro-convenio').selectOption({ label: 'Unimed' })
  await page
    .getByTestId('antecipacao-filtro-paciente')
    .selectOption({ label: 'Ana Paula Ribeiro' })
  await page.getByRole('button', { name: 'Aplicar' }).click()
  await expect(page.locator(`[data-testid="antecipacao-cota-text-${antecipacaoId}"]`)).toHaveText(
    '1/1',
  )

  await page.goto('/guias')
  const conciliacaoResponsePromise = page.waitForResponse((response) => {
    return (
      response.request().method() === 'POST' &&
      response.url().includes(`/guias/${guideId}/conciliacao`)
    )
  })

  await page.getByTestId(`guia-gerar-conciliacao-${guideId}`).click()

  const conciliacaoResponse = await conciliacaoResponsePromise
  const conciliacaoResponseText = await conciliacaoResponse.text()
  console.log(
    `[E2E] POST /guias/${guideId}/conciliacao => ${conciliacaoResponse.status()} :: ${conciliacaoResponseText}`,
  )

  await expect(
    conciliacaoResponse.status(),
    `POST /guias/${guideId}/conciliacao retornou ${conciliacaoResponse.status()} com corpo: ${conciliacaoResponseText}`,
  ).toBe(201)

  const conciliacaoListResponsePromise = page.waitForResponse((response) => {
    return response.request().method() === 'GET' && response.url().includes('/conciliacoes')
  })

  await page.goto('/conciliacao')

  const conciliacaoListResponse = await conciliacaoListResponsePromise
  const conciliacaoListResponseText = await conciliacaoListResponse.text()
  console.log(
    `[E2E] GET /conciliacoes => ${conciliacaoListResponse.status()} :: ${conciliacaoListResponseText}`,
  )

  await expect(
    conciliacaoListResponse.status(),
    `GET /conciliacoes retornou ${conciliacaoListResponse.status()} com corpo: ${conciliacaoListResponseText}`,
  ).toBe(200)
  console.log('[E2E] after conciliacao list load')
  await expect(page.getByTestId('conciliacao-page')).toBeVisible()
  const conciliacaoRow = page.locator('[data-testid^="conciliacao-row-"]').first()
  await expect(conciliacaoRow).toBeVisible()
  console.log('[E2E] conciliacao row visible')
  const conciliacaoId = Number(
    (await conciliacaoRow.getAttribute('data-testid'))?.replace('conciliacao-row-', ''),
  )
  await expect(page.getByTestId(`conciliacao-pagar-${conciliacaoId}`)).toBeDisabled()
  console.log('[E2E] pagar button disabled before conferir')
  await page.getByTestId(`conciliacao-conferir-${conciliacaoId}`).click()
  await expect(page.getByTestId(`conciliacao-status-${conciliacaoId}`)).toContainText('Conferida')
  await expect(page.getByTestId(`conciliacao-pagar-${conciliacaoId}`)).toBeEnabled()
  console.log('[E2E] pagar button enabled after conferir')
  await page.getByTestId(`conciliacao-pagar-${conciliacaoId}`).click()
  await expect(page.getByTestId(`conciliacao-status-${conciliacaoId}`)).toContainText('Paga')
  console.log('[E2E] conciliacao paga')

  await page.getByTestId('shell-logout').click()
  await expect(page).toHaveURL(/\/login$/)
  console.log('[E2E] logout done')
  await page.goto('/')
  await expect(page).toHaveURL(/\/login$/)
  console.log('[E2E] redirect after logout done')
  await page.close()
})

test('isolamento cross-tenant via browser', async ({ page }) => {
  await login(page)

  await page.goto('/guias')
  await expect(page.getByTestId('guias-page')).toBeVisible()
  const clean = await page.getByTestId('guias-page').evaluate((node, needle) => {
    return !node.textContent?.includes(String(needle))
  }, 'GUIA-BETA-001')

  expect(clean).toBe(true)
})

test('guard de rota sem autenticacao', async ({ page }) => {
  await page.goto('/guias')
  await expect(page).toHaveURL(/\/login$/)
  await expect(page.getByTestId('login-page')).toBeVisible()
  await expect(page.getByTestId('guias-page')).toHaveCount(0)
})

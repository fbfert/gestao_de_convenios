import { writeFileSync } from 'node:fs'
import { expect, test, type Page, type TestInfo } from '@playwright/test'

async function login(page: Page) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
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
  await expect(page).toHaveURL(/\/dashboard$/)
  await expect(page.getByTestId('shell-layout')).toBeVisible()
}

async function selectOption(page: Page, testId: string, label: string) {
  await expect(page.getByTestId(testId)).toBeEnabled()
  await page.getByTestId(testId).click()
  await page.getByRole('option', { name: label }).click()
}

test('fluxo completo de negocio', async ({ page }, testInfo: TestInfo) => {
  await login(page)

  await page.goto('/solicitacoes', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('solicitacoes-page')).toBeVisible()
  await page.getByRole('button', { name: 'Novo' }).click()
  await selectOption(page, 'solicitacao-convenio', 'Unimed')
  await selectOption(page, 'solicitacao-paciente', 'Ana Paula Ribeiro · UNI-2026-0001 · Unimed')
  await selectOption(page, 'solicitacao-item-especialidade-0', 'Fisioterapia')
  await selectOption(page, 'solicitacao-item-profissional-0', 'Dra. Marina Tavares')
  await selectOption(page, 'solicitacao-medico', 'Dr. Carlos Almeida')
  const solicitacaoResponsePromise = page.waitForResponse((response) => {
    return response.request().method() === 'POST' && response.url().includes('/solicitacoes')
  })
  await page.getByTestId('solicitacao-submit').click()
  const solicitacaoResponse = await solicitacaoResponsePromise
  const solicitacaoResponseText = await solicitacaoResponse.text()
  console.log(
    `[E2E] POST /solicitacoes => ${solicitacaoResponse.status()} :: ${solicitacaoResponseText}`,
  )
  await expect(
    solicitacaoResponse.status(),
    `POST /solicitacoes retornou ${solicitacaoResponse.status()} com corpo: ${solicitacaoResponseText}`,
  ).toBe(201)

  // Criar não navega mais direto pra lista: a etapa de anexos aparece na
  // mesma tela primeiro, e só "Concluir" leva de volta.
  await expect(page.getByTestId('solicitacao-anexos-step')).toBeVisible()
  await page.getByTestId('solicitacao-anexos-concluir').click()

  const solicitacaoRow = page.locator('[data-testid^="solicitacao-row-"]').first()
  await expect(solicitacaoRow).toBeVisible()
  await expect(solicitacaoRow).toContainText('Análise Interna')
  const solicitacaoId = Number((await solicitacaoRow.getAttribute('data-testid'))?.replace('solicitacao-row-', ''))
  const aprovarResponsePromise = page.waitForResponse((response) => {
    return (
      response.request().method() === 'PATCH' &&
      response.url().includes(`/solicitacoes/${solicitacaoId}/status`)
    )
  })
  page.once('dialog', (dialog) => dialog.accept())
  await page.getByTestId(`solicitacao-acoes-${solicitacaoId}`).click()
  await page.getByTestId(`solicitacao-status-action-ready_for_automation-${solicitacaoId}`).click()
  const aprovarResponse = await aprovarResponsePromise
  const aprovarResponseText = await aprovarResponse.text()
  console.log(
    `[E2E] PATCH /solicitacoes/${solicitacaoId}/status => ${aprovarResponse.status()} :: ${aprovarResponseText}`,
  )
  await expect(
    aprovarResponse.status(),
    `PATCH /solicitacoes/${solicitacaoId}/status retornou ${aprovarResponse.status()} com corpo: ${aprovarResponseText}`,
  ).toBe(200)
  await expect(page.getByTestId(`solicitacao-status-${solicitacaoId}`)).toContainText(
    'Pronto para Automatização',
  )

  await page.reload({ waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('solicitacoes-page')).toBeVisible()
  const seededPatientButton = page.getByRole('button', { name: 'Paciente Guia Popup' })
  await expect(seededPatientButton).toBeVisible()
  await seededPatientButton.click()
  await expect(page.getByTestId('solicitacao-guia-modal')).toBeVisible()
  await expect(page.getByTestId('solicitacao-guia-content')).toContainText('GUIA-POPUP-SEED')

  const conveniosGuiasResponsePromise = page.waitForResponse((response) => {
    return response.request().method() === 'GET' && response.url().includes('/convenios')
  })
  const especialidadesGuiasResponsePromise = page.waitForResponse((response) => {
    return response.request().method() === 'GET' && response.url().includes('/especialidades')
  })

  await page.goto('/guias', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('guias-page')).toBeVisible()
  await page.getByTestId('guia-novo').click()
  await Promise.all([conveniosGuiasResponsePromise, especialidadesGuiasResponsePromise])
  const pacientesResponsePromise = page.waitForResponse((response) => {
    return (
      response.request().method() === 'GET' &&
      response.url().includes('/pacientes?') &&
      response.url().includes('convenio_id=1')
    )
  })
  await selectOption(page, 'guia-convenio', 'Unimed')
  const pacientesResponse = await pacientesResponsePromise
  const pacientesResponseText = await pacientesResponse.text()
  console.log(
    `[E2E] GET /pacientes?convenio_id=1 => ${pacientesResponse.status()} :: ${pacientesResponseText}`,
  )
  await expect(
    pacientesResponse.status(),
    `GET /pacientes?convenio_id=1 retornou ${pacientesResponse.status()} com corpo: ${pacientesResponseText}`,
  ).toBe(200)
  await selectOption(page, 'guia-paciente', 'Ana Paula Ribeiro · UNI-2026-0001')
  await selectOption(page, 'guia-profissional', 'Dra. Marina Tavares')
  await page.getByTestId('guia-numero').fill(`GUIA-E2E-${Date.now()}`)
  await selectOption(page, 'guia-tipo-terapia', 'Especializada')
  const createGuideResponsePromise = page.waitForResponse((response) => {
    return response.request().method() === 'POST' && response.url().includes('/guias')
  })
  await page.getByTestId('guia-submit').click()
  const createGuideResponse = await createGuideResponsePromise
  expect(createGuideResponse.status()).toBe(201)
  const guideId = (await createGuideResponse.json()).data.id as number

  const guideRow = page.getByTestId(`guia-row-${guideId}`)
  await expect(guideRow).toBeVisible()
  const guiaDetailResponsePromise = page.waitForResponse((response) => {
    return response.request().method() === 'GET' && response.url().includes(`/guias/${guideId}`)
  })
  await guideRow.getByRole('link').click()
  const guiaDetailResponse = await guiaDetailResponsePromise
  expect(guiaDetailResponse.status()).toBe(200)
  await expect(page).toHaveURL(new RegExp(`/guias/${guideId}$`))
  await expect(page.getByTestId('guia-detalhe-page')).toBeVisible()
  await expect(page.getByText('Carteirinha: UNI-2026-0001')).toBeVisible()
  await expect(page.getByTestId(`guia-finalizar-${guideId}`)).toHaveText('Finalizar')
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
  await expect(page.getByText('Aprovado', { exact: true })).toBeVisible()

  await page.goto('/antecipacoes', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('antecipacoes-page')).toBeVisible()
  await selectOption(page, 'antecipacao-filtro-convenio', 'Unimed')
  await selectOption(page, 'antecipacao-filtro-paciente', 'Ana Paula Ribeiro')
  await page.getByRole('button', { name: 'Aplicar' }).click()
  await expect(page.getByTestId('antecipacao-alerta-continuidade')).toContainText(
    'sem próximos agendamentos',
  )
  const antecipacaoRow = page.locator('[data-testid^="antecipacao-row-"]').first()
  await expect(antecipacaoRow).toBeVisible()
  const antecipacaoId = Number(
    (await antecipacaoRow.getAttribute('data-testid'))?.replace('antecipacao-row-', ''),
  )
  await expect(page.locator(`[data-testid="antecipacao-cota-text-${antecipacaoId}"]`)).toHaveText(
    '0/1',
  )

  await page.goto(`/lancamentos?antecipacao_id=${antecipacaoId}`, {
    waitUntil: 'domcontentloaded',
  })
  await expect(page.getByTestId('lancamentos-page')).toBeVisible()
  const pdfPath = testInfo.outputPath('registro-sessao.pdf')
  writeFileSync(
    pdfPath,
    '%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>\nendobj\ntrailer\n<< /Root 1 0 R /Size 4 >>\nstartxref\n0\n%%EOF\n',
    'utf8',
  )
  await selectOption(page, 'lancamento-import-profissional', 'Dra. Marina Tavares')
  await page.getByTestId('lancamento-import-transcricao').fill(`GUIA Nº: 521381566206
Clínica: Centro Neuro Kids Ltda
Paciente: Ana Paula Ribeiro
Número Cartão: 0220 090000 551.330-8
Profissional Executante: Mariana
Terapia aplicada: ABA - AV. Neuropsicológica

Sessões
1 08/04/26 14:50 15:40 Bruno Marinho Aplicação testes Neuropsicológicos`)
  await page.getByTestId('lancamento-importar').click()
  await expect(page.getByTestId('lancamento-import-pdf')).toBeVisible()
  await page.getByTestId('lancamento-import-pdf').setInputFiles(pdfPath)
  await expect(page.getByTestId('lancamento-confirmar-envio')).toBeEnabled()
  const confirmImportResponsePromise = page.waitForResponse((response) => {
    return (
      response.request().method() === 'POST' &&
      response.url().includes(`/antecipacoes/${antecipacaoId}/lancamentos/importar-transcricao`)
    )
  })
  await page.getByTestId('lancamento-confirmar-envio').click()
  expect((await confirmImportResponsePromise).status()).toBe(201)

  await page.goto('/antecipacoes', { waitUntil: 'domcontentloaded' })
  await selectOption(page, 'antecipacao-filtro-convenio', 'Unimed')
  await selectOption(page, 'antecipacao-filtro-paciente', 'Ana Paula Ribeiro')
  await page.getByRole('button', { name: 'Aplicar' }).click()
  await expect(page.locator(`[data-testid="antecipacao-cota-text-${antecipacaoId}"]`)).toHaveText(
    '1/1',
  )

  const guiasListResponsePromise = page.waitForResponse((response) => {
    return response.request().method() === 'GET' && response.url().includes('/guias?')
  })
  await page.goto('/guias', { waitUntil: 'domcontentloaded' })
  const guiasListResponse = await guiasListResponsePromise
  const guiasListResponseText = await guiasListResponse.text()
  console.log(
    `[E2E] GET /guias => ${guiasListResponse.status()} :: ${guiasListResponseText}`,
  )
  await expect(
    guiasListResponse.status(),
    `GET /guias retornou ${guiasListResponse.status()} com corpo: ${guiasListResponseText}`,
  ).toBe(200)
  await expect(page.getByTestId(`guia-status-${guideId}`)).toHaveText('Aprovado')
  await expect(page.getByTestId(`guia-gerar-conciliacao-${guideId}`)).toBeEnabled()
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

  await page.goto('/conciliacao', { waitUntil: 'domcontentloaded' })

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
  await page.goto('/', { waitUntil: 'domcontentloaded' })
  await page.waitForURL(/\/login$/)
  await expect(page).toHaveURL(/\/login$/)
  console.log('[E2E] redirect after logout done')
})

test('detalhe de guia abre pela lista e atualiza após finalizar', async ({ page }) => {
  await login(page)

  await page.goto('/guias', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('guias-page')).toBeVisible()
  await page.getByTestId('guia-novo').click()
  await expect(page.getByTestId('guia-convenio')).toBeVisible()
  await page.getByTestId('guia-numero').fill(`GUIA-DETALHE-${Date.now()}`)

  const createResponsePromise = page.waitForResponse((response) => {
    return response.request().method() === 'POST' && response.url().includes('/guias')
  })
  await page.getByTestId('guia-submit').click()
  const createResponse = await createResponsePromise
  expect(createResponse.status()).toBe(201)
  const guideId = (await createResponse.json()).data.id as number

  const guideRow = page.getByTestId(`guia-row-${guideId}`)
  await expect(guideRow).toBeVisible()
  const detailResponsePromise = page.waitForResponse((response) => {
    return response.request().method() === 'GET' && response.url().includes(`/guias/${guideId}`)
  })
  await guideRow.getByRole('link').click()
  expect((await detailResponsePromise).status()).toBe(200)
  await expect(page).toHaveURL(new RegExp(`/guias/${guideId}$`))
  await expect(page.getByTestId('guia-detalhe-page')).toBeVisible()
  await expect(page.getByTestId(`guia-finalizar-${guideId}`)).toHaveText('Finalizar')
  await page.getByTestId(`guia-finalizar-${guideId}`).click()
  await page.getByTestId(`guia-senha-${guideId}`).fill('DETALHE123')

  const finalizeResponsePromise = page.waitForResponse((response) => {
    return response.request().method() === 'PATCH' && response.url().includes(`/guias/${guideId}/finalizar`)
  })
  await page.getByTestId(`guia-finalizar-confirmar-${guideId}`).click()
  expect((await finalizeResponsePromise).status()).toBe(200)
  await expect(page.getByText('Aprovado', { exact: true })).toBeVisible()
})

test('pedido com duas especialidades recebe anexos por especialidade', async ({ page }) => {
  await login(page)

  await page.goto('/solicitacoes/nova', { waitUntil: 'domcontentloaded' })
  await selectOption(page, 'solicitacao-convenio', 'Unimed')
  await selectOption(page, 'solicitacao-paciente', 'Ana Paula Ribeiro · UNI-2026-0001 · Unimed')
  await selectOption(page, 'solicitacao-item-especialidade-0', 'Fisioterapia')
  await selectOption(page, 'solicitacao-item-profissional-0', 'Dra. Marina Tavares')

  await page.getByTestId('solicitacao-item-adicionar').click()
  await selectOption(page, 'solicitacao-item-especialidade-1', 'Fonoaudiologia')
  await selectOption(page, 'solicitacao-item-profissional-1', 'Dra. Paula Menezes')
  await selectOption(page, 'solicitacao-medico', 'Dr. Carlos Almeida')

  const createPromise = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' && response.url().endsWith('/api/solicitacoes'),
  )
  await page.getByTestId('solicitacao-submit').click()
  const created = await createPromise
  expect(created.status()).toBe(201)

  const body = await created.json()
  const solicitacaoId = body.data.id as number
  const itens = body.data.itens as Array<{ id: number }>
  expect(itens).toHaveLength(2)

  // A etapa de anexos já aparece na mesma tela, sem precisar abrir o menu de
  // ações na lista.
  await expect(page.getByTestId('solicitacao-anexos')).toBeVisible()

  // Pedido Médico vale para o pedido inteiro; Plano é por especialidade.
  const uploadPedido = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' &&
      response.url().includes(`/solicitacoes/${solicitacaoId}/documentos`),
  )
  await page
    .getByTestId('anexo-slot-pedido_medico')
    .locator('input[type="file"]')
    .setInputFiles({ name: 'pedido.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4') })
  expect((await uploadPedido).status()).toBe(201)
  await expect(page.getByTestId('anexo-slot-pedido_medico')).toContainText('pedido.pdf')

  const uploadPlano = page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' &&
      response.url().includes(`/solicitacoes/${solicitacaoId}/documentos`),
  )
  await page
    .getByTestId(`anexo-slot-plano_individualizado-item-${itens[1].id}`)
    .locator('input[type="file"]')
    .setInputFiles({ name: 'plano-fono.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4') })
  expect((await uploadPlano).status()).toBe(201)

  await expect(
    page.getByTestId(`anexo-slot-plano_individualizado-item-${itens[1].id}`),
  ).toContainText('plano-fono.pdf')
  await expect(
    page.getByTestId(`anexo-slot-plano_individualizado-item-${itens[0].id}`),
  ).toContainText('Nenhum arquivo anexado')
})

test('detalhe de guia inexistente exibe erro tratado', async ({ page }) => {
  await login(page)

  await page.goto('/guias/999999', { waitUntil: 'domcontentloaded' })

  await expect(page.getByText('Não foi possível encontrar esta guia.')).toBeVisible()
})

test('isolamento cross-tenant via browser', async ({ page }) => {
  await login(page)

  await page.goto('/guias', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('guias-page')).toBeVisible()
  const clean = await page.getByTestId('guias-page').evaluate((node, needle) => {
    return !node.textContent?.includes(String(needle))
  }, 'GUIA-BETA-001')

  expect(clean).toBe(true)
})

test('guard de rota sem autenticacao', async ({ page }) => {
  await page.goto('/guias', { waitUntil: 'domcontentloaded' })
  await expect(page).toHaveURL(/\/login$/)
  await expect(page.getByTestId('login-page')).toBeVisible()
  await expect(page.getByTestId('guias-page')).toHaveCount(0)
})

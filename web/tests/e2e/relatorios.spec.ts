import { expect, test, type Page } from '@playwright/test'

async function login(page: Page) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

test('o menu agrupa Painel e Relatórios sob Gestão de Convênios', async ({ page }) => {
  await login(page)

  await page.goto('/inicio', { waitUntil: 'domcontentloaded' })

  const grupo = page.getByTestId('gestao-convenios-page')
  await expect(grupo).toBeVisible()
  await expect(grupo.getByTestId('gestao-convenios-page-card-painel')).toBeVisible()
  await expect(grupo.getByTestId('gestao-convenios-page-card-relatórios')).toBeVisible()

  await grupo.getByTestId('gestao-convenios-page-card-relatórios').click()
  await expect(page).toHaveURL(/\/relatorios/)
})

test('a aba Operação mostra KPI, gráfico e tabela, e o recorte fica na URL', async ({ page }) => {
  await login(page)

  await page.goto('/relatorios', { waitUntil: 'domcontentloaded' })

  await expect(page.getByTestId('relatorios-page')).toBeVisible()

  // O gráfico só monta depois da resposta; o KPI é a prova de que ela chegou.
  await expect(page.getByTestId('kpi-guias_geradas-valor')).toBeVisible({ timeout: 30000 })
  await expect(page.getByTestId('grafico-guias_por_status')).toBeVisible()
  await expect(page.getByTestId('tabela-por_convenio')).toBeVisible()

  // Trocar o preset precisa aparecer no endereço — é o que torna o link
  // compartilhável reproduzir o MESMO relatório.
  await page.getByTestId('preset-mes_anterior').click()
  await expect(page).toHaveURL(/preset=mes_anterior/)
  await expect(page).toHaveURL(/de=\d{4}-\d{2}-\d{2}/)
  await expect(page).toHaveURL(/ate=\d{4}-\d{2}-\d{2}/)

  // `click()` e não `check()`: o estado do campo vem da URL, e `check()` relê o
  // `checked` logo depois de clicar — antes de o React comitar a re-renderização
  // disparada pela navegação. Ele então clica de novo e desmarca.
  await page.getByTestId('filtro-comparar').click()
  await expect(page).toHaveURL(/comparar=1/)
  await expect(page.getByTestId('kpi-guias_geradas-variacao')).toBeVisible({ timeout: 30000 })
})

test('as quatro abas abrem para o admin e a aba escolhida fica na URL', async ({ page }) => {
  await login(page)
  await page.goto('/relatorios', { waitUntil: 'domcontentloaded' })

  for (const aba of ['financeiro', 'automacoes', 'uso', 'operacao']) {
    await page.getByTestId(`aba-${aba}`).click()
    await expect(page).toHaveURL(new RegExp(`aba=${aba}`))
    await expect(page.getByTestId('kpis')).toBeVisible({ timeout: 30000 })
  }
})

/**
 * O gráfico precisa trocar de cor com o tema.
 *
 * Gráfico não tem teste de contraste: uma paleta cravada em hex continuaria
 * legível no tema claro e passaria despercebida no de alto contraste, que é
 * justamente onde a cor importa. Aqui a prova é direta — a cor do traço muda.
 */
test('a paleta do gráfico acompanha o tema de alto contraste', async ({ page }) => {
  await login(page)
  await page.goto('/relatorios', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('kpi-guias_geradas-valor')).toBeVisible({ timeout: 30000 })

  const corDaArea = async () =>
    page.locator('[data-testid="grafico-guias_por_status"] .recharts-area-area').first().getAttribute('fill')

  const claro = await corDaArea()
  expect(claro, 'a área precisa nascer pintada').toBeTruthy()

  await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'contraste'))

  await expect
    .poll(corDaArea, { timeout: 10000, message: 'a paleta não acompanhou a troca de tema' })
    .not.toBe(claro)
})

import { expect, test, type Page } from '@playwright/test'

/**
 * Change `ajustes-dashboard-lancamento-simulacao`: o que só o navegador prova.
 *
 * As contagens dos blocos de antecipação e o filtro de guias em conflito têm
 * teste na API (AntecipacoesApiTest, DashboardGuiasCardTest). Aqui interessa
 * o elo que quebrou em produção: o link do dashboard chegar à listagem com o
 * filtro, e a listagem mandá-lo à API.
 */

/*
  A API de teste é um `php artisan serve`, que atende uma requisição por vez.
  Terminar o teste com consultas ainda em voo (os relatórios, a listagem de
  guias) deixa o servidor ocupado com elas, e o spec SEGUINTE estoura o prazo
  esperando uma busca — falha em arquivo alheio, só na suíte cheia.
*/
test.afterEach(async ({ page }) => {
  await page.waitForLoadState('networkidle')
})

async function login(page: Page, email = 'admin@clinica-exemplo.test') {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.getByTestId('login-email').fill(email)
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

/**
 * Espera a próxima listagem de guias e devolve os parâmetros enviados.
 *
 * A tela faz outras consultas a `/api/guias` (alertas, seletores); só a
 * listagem manda `ordenar_por`.
 */
function proximaListagemDeGuias(page: Page) {
  return page
    .waitForRequest((req) => {
      if (req.method() !== 'GET' || !/\/api\/guias(\?|$)/.test(req.url())) return false
      return new URL(req.url()).searchParams.has('ordenar_por')
    })
    .then((req) => new URL(req.url()).searchParams)
}

test('dashboard oferece Ver relatórios e os blocos de antecipação', async ({ page }) => {
  await login(page)

  const painel = page.getByTestId('dashboard-page')
  await expect(painel.getByText('Antecipações elegíveis', { exact: true })).toBeVisible()
  await expect(painel.getByText('Antecipações realizadas', { exact: true })).toBeVisible()
  await expect(painel.locator('a[href="/antecipacoes?status=gerada"]')).toContainText('neste mês')

  await page.getByTestId('dashboard-ver-relatorios').click()
  await expect(page).toHaveURL(/\/relatorios/)
  await expect(page.getByTestId('relatorios-page')).toBeVisible()
})

test('perfil sem relatório nem antecipação não vê o atalho nem os blocos', async ({ page }) => {
  await login(page, 'profissional@clinica-exemplo.test')

  const painel = page.getByTestId('dashboard-page')
  await expect(painel.getByText('Telas operacionais')).toBeVisible()
  await expect(page.getByTestId('dashboard-ver-relatorios')).toHaveCount(0)
  await expect(painel.getByText('Antecipações elegíveis', { exact: true })).toHaveCount(0)
  await expect(painel.getByText('Antecipações realizadas', { exact: true })).toHaveCount(0)
})

test('link de sessões em conflito chega filtrado, e o selo remove o filtro', async ({ page }) => {
  await login(page)

  const primeira = proximaListagemDeGuias(page)
  await page.goto('/guias?sessoes_em_conflito=1', { waitUntil: 'domcontentloaded' })
  expect((await primeira).get('sessoes_em_conflito')).toBe('1')

  const selo = page.getByTestId('guia-filtro-sessoes_em_conflito')
  await expect(selo).toContainText('Sessões em conflito')

  const semFiltro = proximaListagemDeGuias(page)
  await selo.click()
  // Filtro vazio vai como parâmetro vazio, igual aos demais; a API o ignora.
  expect((await semFiltro).get('sessoes_em_conflito') ?? '').toBe('')
  await expect(selo).toHaveCount(0)
  await expect(page).not.toHaveURL(/sessoes_em_conflito/)
})

test('links de negadas pendentes e senha vencendo também chegam filtrados', async ({ page }) => {
  await login(page)

  const negadas = proximaListagemDeGuias(page)
  await page.goto('/guias?status=denied&pendente=1', { waitUntil: 'domcontentloaded' })
  const parametrosNegadas = await negadas
  expect(parametrosNegadas.get('status')).toBe('denied')
  expect(parametrosNegadas.get('pendente')).toBe('1')
  await expect(page.getByTestId('guia-filtro-pendente')).toBeVisible()

  const senha = proximaListagemDeGuias(page)
  await page.goto('/guias?senha_vencendo=1', { waitUntil: 'domcontentloaded' })
  expect((await senha).get('senha_vencendo')).toBe('1')
  await expect(page.getByTestId('guia-filtro-senha_vencendo')).toBeVisible()
})

/*
  Não salva: a suíte compartilha o banco, e `finalizar-na-unimed` depende da
  simulação ligada. Prova só o controle e a confirmação.
*/
test('desligar a simulação da finalização pede confirmação', async ({ page }) => {
  await login(page)
  await page.goto('/automacoes/configuracoes', { waitUntil: 'domcontentloaded' })

  const simulacao = page.getByTestId('automacoes-config-finalizar-simulacao-ativo')
  await expect(simulacao).toBeChecked()

  // Desistir mantém ligada.
  await simulacao.click()
  await expect(page.getByTestId('confirm-dialog')).toContainText('Unimed de verdade')
  await page.getByTestId('confirm-dialog-cancelar').click()
  await expect(simulacao).toBeChecked()

  // Confirmar desliga.
  await simulacao.click()
  await page.getByTestId('confirm-dialog-confirmar').click()
  await expect(simulacao).not.toBeChecked()

  // Religar não pergunta nada.
  await simulacao.click()
  await expect(page.getByTestId('confirm-dialog')).toHaveCount(0)
  await expect(simulacao).toBeChecked()
})

import { expect, test, type Page } from '@playwright/test'

async function login(page: Page, email = 'admin@clinica-exemplo.test') {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.getByTestId('login-email').fill(email)
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

/**
 * Cada aba monta os SEUS gráficos.
 *
 * A prova é a presença do contêiner com a chave que o serviço daquela aba
 * declarou. O gráfico pode estar vazio — o banco de teste tem pouco dado —, e
 * é justamente por isso que o teste olha a chave: se a página trocasse de aba
 * sem trocar de gráfico, ou se uma série mudasse de nome na API, os números
 * continuariam aparecendo e o desenho estaria errado em silêncio.
 */
const GRAFICO_DA_ABA: Record<string, string> = {
  operacao: 'grafico-guias_por_status',
  financeiro: 'grafico-executado_x_pago',
  automacoes: 'grafico-execucoes_por_dia',
  uso: 'grafico-acoes_por_hora',
}

test('as quatro abas abrem para o admin, cada uma com KPI e gráfico próprios', async ({ page }) => {
  await login(page)
  await page.goto('/relatorios', { waitUntil: 'domcontentloaded' })

  for (const aba of ['financeiro', 'automacoes', 'uso', 'operacao']) {
    await page.getByTestId(`aba-${aba}`).click()
    await expect(page).toHaveURL(new RegExp(`aba=${aba}`))
    await expect(page.getByTestId('kpis')).toBeVisible({ timeout: 30000 })
    await expect(page.getByTestId(GRAFICO_DA_ABA[aba])).toBeVisible({ timeout: 30000 })
  }
})

test('o filtro de convênio entra na URL e vale para a aba aberta', async ({ page }) => {
  await login(page)
  await page.goto('/relatorios', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('kpis')).toBeVisible({ timeout: 30000 })

  await page.getByTestId('filtro-convenio').click()
  await page.getByRole('option', { name: 'Unimed', exact: true }).click()

  await expect(page).toHaveURL(/convenio_id=\d+/)
  await expect(page.getByTestId('kpis')).toBeVisible({ timeout: 30000 })
})

/**
 * O recorte inteiro sobrevive ao recarregamento.
 *
 * É a razão de os filtros viverem na URL: relatório é feito para ser mandado
 * para alguém, e o link precisa reproduzir o MESMO relatório do outro lado.
 */
test('abrir o endereço de novo reproduz o mesmo recorte', async ({ page }) => {
  await login(page)

  const endereco = '/relatorios?aba=uso&preset=mes_anterior&de=2026-08-01&ate=2026-08-31&comparar=1'
  await page.goto(endereco, { waitUntil: 'domcontentloaded' })

  await expect(page.getByTestId('aba-uso')).toHaveAttribute('data-state', 'active')
  await expect(page.getByTestId('periodo-escolhido')).toContainText('01/08/2026 a 31/08/2026')
  await expect(page.getByTestId('filtro-comparar')).toBeChecked()
  await expect(page.getByTestId('preset-mes_anterior')).toHaveAttribute('aria-pressed', 'true')
})

/**
 * Permissão é POR ABA.
 *
 * O papel `funcionario` recebe operação e automações, e não o financeiro — é o
 * recorte que justifica quatro permissões em vez de uma. A tela precisa
 * refletir isso: oferecer uma aba que só devolve 403 é pior do que não
 * oferecer.
 *
 * Não mexe em dado de semente, então não precisa desfazer nada no fim (a suíte
 * roda com um worker e banco compartilhado — ver playwright.config.ts).
 */
test('quem não tem a permissão do financeiro não enxerga a aba', async ({ page }) => {
  await login(page, 'funcionario@clinica-exemplo.test')

  await page.goto('/relatorios', { waitUntil: 'domcontentloaded' })

  await expect(page.getByTestId('aba-operacao')).toBeVisible()
  await expect(page.getByTestId('aba-automacoes')).toBeVisible()
  await expect(page.getByTestId('aba-financeiro')).toHaveCount(0)
  await expect(page.getByTestId('aba-uso')).toHaveCount(0)

  // E a aba pedida pela URL também não aparece: a permissão manda, não o link.
  await page.goto('/relatorios?aba=financeiro', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('aba-financeiro')).toHaveCount(0)
  await expect(page.getByTestId('aba-operacao')).toHaveAttribute('data-state', 'active')
})

/**
 * Sem nenhuma das quatro permissões, a página não existe para a pessoa.
 *
 * O papel `profissional` não recebe relatório nenhum. O menu esconde a entrada
 * e o endereço direto devolve ao painel — uma tela de abas vazias seria pior do
 * que tela nenhuma, porque parece defeito.
 */
test('quem não tem permissão de relatório nenhuma não chega à página', async ({ page }) => {
  await login(page, 'profissional@clinica-exemplo.test')

  await page.goto('/inicio', { waitUntil: 'domcontentloaded' })

  const grupo = page.getByTestId('gestao-convenios-page')
  await expect(grupo.getByTestId('gestao-convenios-page-card-painel')).toBeVisible()
  await expect(grupo.getByTestId('gestao-convenios-page-card-relatórios')).toHaveCount(0)

  await page.goto('/relatorios', { waitUntil: 'domcontentloaded' })
  await expect(page).toHaveURL(/\/dashboard$/)
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

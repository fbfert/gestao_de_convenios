import { expect, test, type Page } from '@playwright/test'

/**
 * A rede que impede a tela branca, vista do navegador — ver a spec
 * `resiliencia-da-interface`.
 *
 * São duas provas distintas:
 *
 * 1. O gatilho reproduzido: o DOM reescrito por um tradutor. Sem a correção do
 *    `Botao`, clicar em "Sair" depois da reescrita esvaziava o `#root`.
 * 2. A rede propriamente dita: um erro de renderização vira uma tela que
 *    explica e oferece saída, não uma página em branco.
 */

async function login(page: Page) {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

/**
 * Reescreve o DOM exatamente como o tradutor do navegador faz: cada nó de
 * texto vira `<font><font>texto</font></font>`.
 *
 * É a reprodução do estrago real — o texto deixa de ser filho direto do
 * elemento que o continha, e um `insertBefore` que mirava nele passa a falhar.
 */
async function traduzirODom(page: Page) {
  await page.evaluate(() => {
    const raiz = document.getElementById('root')

    if (!raiz) {
      return
    }

    const walker = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT)
    const nos: Node[] = []

    while (walker.nextNode()) {
      if (walker.currentNode.nodeValue?.trim()) {
        nos.push(walker.currentNode)
      }
    }

    for (const no of nos) {
      const externo = document.createElement('font')
      const interno = document.createElement('font')
      interno.textContent = no.nodeValue
      externo.appendChild(interno)
      no.parentNode?.replaceChild(externo, no)
    }
  })
}

/**
 * O gatilho reproduzido, no `Botao` de verdade.
 *
 * Usa `/botao-carregando` (rota só do modo e2e) em vez de um botão de tela
 * real por um motivo aprendido na marra: a primeira versão deste teste clicava
 * em "Sair" e **passava mesmo SEM a correção** — o logout desmonta a tela
 * antes de re-renderizar com `carregando`, então o `insertBefore` problemático
 * nunca acontecia. O teste não provava nada.
 *
 * Aqui o carregamento liga no clique e não desliga, então o momento do
 * `insertBefore(spinner, rótulo)` é garantido — que é exatamente o que
 * quebrava com o texto embrulhado em `<font>`.
 */
test('o DOM reescrito por tradutor não derruba a página ao clicar num botão que carrega', async ({
  page,
}) => {
  const erros: string[] = []
  page.on('pageerror', (erro) => erros.push(erro.message))

  await login(page)
  await page.goto('/botao-carregando', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('botao-que-carrega')).toBeVisible()

  await traduzirODom(page)

  // A reescrita funcionou: há <font> dentro do #root.
  expect(await page.locator('#root font').count()).toBeGreaterThan(0)

  await page.getByTestId('botao-que-carrega').click()

  /*
   * As duas coisas que o bug produzia, e que aqui não podem acontecer:
   *
   * 1. o NotFoundError do insertBefore
   * 2. o #root esvaziado, que era o sintoma que a clínica via
   */
  await expect(page.locator('#root')).not.toBeEmpty()
  await expect(page.getByTestId('botao-que-carrega')).toBeVisible()
  expect(erros.filter((mensagem) => mensagem.includes('insertBefore'))).toEqual([])

  // E a tela de erro também não apareceu: o clique simplesmente funcionou.
  await expect(page.getByTestId('app-erro')).toHaveCount(0)
})

test('erro de renderização vira tela de erro, e Voltar ao início recupera', async ({ page }) => {
  await login(page)

  await page.goto('/erro-simulado', { waitUntil: 'domcontentloaded' })

  // Em vez da página em branco: a tela que explica.
  await expect(page.getByTestId('app-erro')).toBeVisible({ timeout: 15000 })
  await expect(page.getByTestId('app-erro')).toContainText('Algo deu errado nesta tela')
  await expect(page.getByTestId('app-erro')).toContainText('não foram perdidos')

  // O código curto, que é o que o suporte usa para achar a linha do log.
  await expect(page.getByTestId('app-erro-codigo')).toHaveText(/^[0-9A-F]{6}$/)

  // E a saída funciona sem recarregar: resetKeys rearma o boundary na troca
  // de rota.
  await page.getByTestId('app-erro-inicio').click()
  await expect(page).toHaveURL(/\/dashboard$/)
  await expect(page.getByTestId('app-erro')).toHaveCount(0)
})

test('o erro de renderização chega ao servidor', async ({ page }) => {
  await login(page)

  const relato = page.waitForRequest(
    (requisicao) =>
      requisicao.method() === 'POST' && requisicao.url().includes('/erros-cliente'),
    { timeout: 15000 },
  )

  await page.goto('/erro-simulado', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('app-erro')).toBeVisible({ timeout: 15000 })

  const corpo = JSON.parse((await relato).postData() ?? '{}')

  expect(corpo.message).toContain('Erro simulado')
  // A pilha de componentes é o que distingue um erro de renderização dos
  // outros: é ela que diz em qual componente a árvore quebrou.
  expect(corpo.componentStack).toBeTruthy()
  expect(corpo.url).toContain('/erro-simulado')
})

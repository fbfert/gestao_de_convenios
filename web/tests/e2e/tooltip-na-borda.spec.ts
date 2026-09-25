import { expect, test, type Page } from '@playwright/test'

/**
 * O tooltip que derrubou Solicitações em 24 e 25/09/2026.
 *
 * O registro de erros do servidor (a change `tela-de-erro-em-vez-de-tela-branca`
 * funcionando pela primeira vez) trouxe três quedas com "Minified React error
 * #185" — laço de `setState` — e a pilha de componentes apontou um único
 * componente, que o sourcemap traduziu para `Tooltip.tsx`.
 *
 * A causa: o `useLayoutEffect` que desloca o painel para caber na viewport
 * dependia do próprio deslocamento. Encostado na borda direita e numa
 * coordenada com fração de pixel (zoom do navegador, Windows a 125%), a
 * medição seguinte não devolve exatamente o mesmo número, o efeito grava de
 * novo, e o React desiste com o #185.
 *
 * Este teste ABRE o tooltip nessa condição e afirma que a página sobrevive.
 * Verificado nas duas direções: sem a correção do `Tooltip`, o erro #185
 * aparece e a tela de erro toma o lugar da rota.
 */

test.use({
  // Escala fracionária: garante coordenada com fração de pixel, que é o que
  // faz a conta do deslocamento não convergir.
  deviceScaleFactor: 1.25,
  viewport: { width: 1000, height: 700 },
})

async function login(page: Page) {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

/**
 * A medição que não se repete, injetada de propósito.
 *
 * O laço acontece quando duas medições seguidas do painel não devolvem o
 * mesmo número — na clínica, por zoom do navegador ou escala do Windows; o
 * headless do teste, com escala e viewport fixos, mede sempre igual e nunca
 * cai nisso sozinho. Então o teste faz o `getBoundingClientRect` do painel
 * oscilar 0,4px entre uma chamada e a outra: é o mesmo estímulo, e é
 * determinístico. Um componente que mede uma vez por abertura não se importa;
 * um que realimenta a própria medição entra no #185.
 */
async function medicaoQueOscila(page: Page) {
  await page.addInitScript(() => {
    const original = Element.prototype.getBoundingClientRect
    let vez = 0

    Element.prototype.getBoundingClientRect = function () {
      const caixa = original.call(this)

      if ((this as Element).getAttribute('role') !== 'tooltip') {
        return caixa
      }

      vez += 1
      const jitter = vez % 2 === 0 ? 0.4 : -0.4

      return new DOMRect(caixa.x + jitter, caixa.y, caixa.width, caixa.height)
    }
  })
}

test('tooltip encostado na borda, com medição que oscila, não entra em laço', async ({ page }) => {
  const erros: string[] = []
  page.on('pageerror', (erro) => erros.push(erro.message))

  await medicaoQueOscila(page)
  await login(page)
  await page.goto('/tooltip-na-borda', { waitUntil: 'domcontentloaded' })

  const gatilho = page.getByTestId('tooltip-na-borda').getByRole('button', { name: 'Dica na borda' })
  await expect(gatilho).toBeVisible()

  // `locator` e não `getByRole`: o painel é `aria-hidden` de propósito (o
  // leitor de tela usa o `sr-only`), e `getByRole` o deixa de fora.
  const painel = page.locator('[role="tooltip"]')

  // Abre pelo mouse (hover) e pelo clique, que é o que a clínica faz.
  await gatilho.hover()
  await expect(painel).toBeVisible()
  await gatilho.click()
  await gatilho.click()
  await expect(painel).toBeVisible()

  // O painel coube na tela: a borda direita dele não passa da viewport.
  const caixa = await painel.boundingBox()
  const largura = await page.evaluate(() => document.documentElement.clientWidth)
  expect(caixa).not.toBeNull()
  expect(caixa!.x + caixa!.width).toBeLessThanOrEqual(largura + 1)

  // E nada disto pode ter acontecido: o laço do React, nem a tela de erro.
  expect(erros.filter((m) => m.includes('#185') || m.includes('Maximum update depth'))).toEqual([])
  await expect(page.getByTestId('app-erro')).toHaveCount(0)
})

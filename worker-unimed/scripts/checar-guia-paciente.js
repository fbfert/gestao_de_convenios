// Script de diagnostico avulso: loga no portal, abre "Exames em aberto" e
// varre as linhas procurando pelos ultimos 6 digitos do cartao do paciente
// (mesma tecnica que capturarAutorizacaoBatch ja usa por numero_guia, so que
// aqui sem saber o numero da guia de antemao). So leitura — nunca clica em
// nenhuma guia encontrada, so le o texto da linha.
import fs from 'fs'
import { chromium } from 'playwright'
import { login } from '../src/portal.js'
import { abrirExamesAbertos } from '../src/operations/statusSenha.js'

const DEFAULT_TIMEOUT = Number(process.env.UNIMED_WORKER_STEP_TIMEOUT_MS ?? 5000)

async function main() {
  const credential = JSON.parse(process.env.UNIMED_CREDENTIAL_JSON)
  const cardLast6 = String(process.env.UNIMED_CARD_LAST6 ?? '').trim()

  const browser = await chromium.launch({ headless: true })
  const page = await browser.newPage()
  const matches = []

  try {
    await login(page, credential)
    await abrirExamesAbertos(page)

    let paginaAtual = 1
    const maxPaginas = 15

    while (paginaAtual <= maxPaginas) {
      const rows = page.locator('table tr')
      const count = await rows.count()

      for (let i = 0; i < count; i += 1) {
        const row = rows.nth(i)
        const text = await row.innerText().catch(() => '')
        const digits = text.replace(/\D/g, '')
        if (cardLast6 && digits.includes(cardLast6)) {
          matches.push(text.replace(/\s+/g, ' ').trim())
        }
      }

      const next = page.locator('a:has-text("Próxima"), a:has-text("Proxima"), [data-next-page]').first()
      const hasNext = await next.isVisible({ timeout: 500 }).catch(() => false)
      if (!hasNext) {
        break
      }

      await next.click({ timeout: DEFAULT_TIMEOUT })
      await page.waitForTimeout(1000)
      paginaAtual += 1
    }

    console.log('PAGINAS_VARRIDAS:', paginaAtual)
    console.log('MATCHES_COUNT:', matches.length)
    fs.writeFileSync('/tmp/matches-guia-paciente.json', JSON.stringify(matches, null, 2))
  } catch (error) {
    console.error('ERRO:', error?.message ?? error)
    await page.screenshot({ path: '/tmp/erro-checar-guia.png', fullPage: true }).catch(() => {})
  } finally {
    await browser.close()
  }
}

main()

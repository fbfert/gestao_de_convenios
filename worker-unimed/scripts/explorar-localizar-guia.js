// Script de diagnostico avulso, SOMENTE LEITURA: loga no portal, abre o
// cadastro do beneficiario, busca a guia em "Localizar Guia" (mesmo caminho
// de localizarGuiaPorCadastro em statusSenha.js) e, se achar, clica na linha
// pra ver pra onde o portal leva -- sem preencher/submeter mais nada depois
// disso. Objetivo: descobrir se da pra reaproveitar essa tela pra ler
// senha/validade/sessoes, igual abrirGuiaPorFiltro ja faz em "Exames em
// aberto".
import fs from 'fs'
import { chromium } from 'playwright'
import { login, abrirBeneficiario, preencherCarteirinha, atualizarCadastroSeNecessario, splitCarteirinha, waitProcessing, textoRestricao } from '../src/portal.js'

const DEFAULT_TIMEOUT = Number(process.env.UNIMED_WORKER_STEP_TIMEOUT_MS ?? 10000)

async function main() {
  const credential = JSON.parse(process.env.UNIMED_CREDENTIAL_JSON)
  const carteirinha = process.env.UNIMED_CARTEIRINHA
  const numeroGuia = process.env.UNIMED_NUMERO_GUIA

  const browser = await chromium.launch({ headless: true })
  const page = await browser.newPage()

  try {
    await login(page, credential)
    console.log('LOGIN_OK')

    const card = splitCarteirinha(carteirinha)
    const popup = await abrirBeneficiario(page)
    console.log('POPUP_ABERTA')

    await preencherCarteirinha(popup, card)
    console.log('CARTEIRINHA_PREENCHIDA')

    const restricao = await textoRestricao(popup)
    console.log('RESTRICAO:', restricao ?? 'nenhuma')

    await atualizarCadastroSeNecessario(popup)
    console.log('CADASTRO_ATUALIZADO_SE_NECESSARIO')

    await popup.locator('#s_NR_GUIA, [name="s_NR_GUIA"]').fill(String(numeroGuia), { timeout: DEFAULT_TIMEOUT })
    await popup.locator('[name="Button_Filtro"]').first().click({ timeout: DEFAULT_TIMEOUT })
    await waitProcessing(popup)
    await popup.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT }).catch(() => {})
    await popup.getByText(/exame\(s\) encontrado\(s\)/).first().waitFor({ state: 'visible', timeout: DEFAULT_TIMEOUT }).catch(() => {})

    await popup.screenshot({ path: '/tmp/explorar-01-lista.png', fullPage: true }).catch(() => {})
    const htmlLista = await popup.content()
    fs.writeFileSync('/tmp/explorar-01-lista.html', htmlLista)
    console.log('LISTA_CAPTURADA')

    const rows = popup.locator('table tr')
    const count = await rows.count()
    let rowFound = null
    for (let i = 0; i < count; i += 1) {
      const row = rows.nth(i)
      const text = await row.innerText().catch(() => '')
      if (text.includes(String(numeroGuia))) {
        rowFound = row
        console.log('LINHA_ENCONTRADA:', text.replace(/\s+/g, ' ').trim())
        break
      }
    }

    if (!rowFound) {
      console.log('GUIA_NAO_ENCONTRADA_NA_LISTA')
      return
    }

    const link = rowFound.locator('a').first()
    const temLink = await link.count()
    console.log('TEM_LINK_CLICAVEL:', temLink > 0 ? 'sim' : 'nao')

    if (temLink > 0) {
      await link.click({ timeout: DEFAULT_TIMEOUT }).catch((e) => console.log('CLIQUE_FALHOU:', e.message))
      await waitProcessing(popup)
      await popup.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT }).catch(() => {})
      await popup.waitForTimeout(1500)

      await popup.screenshot({ path: '/tmp/explorar-02-apos-clique.png', fullPage: true }).catch(() => {})
      const htmlApos = await popup.content()
      fs.writeFileSync('/tmp/explorar-02-apos-clique.html', htmlApos)
      console.log('APOS_CLIQUE_CAPTURADO')

      const camposInteresse = ['NR_SENHA', 'DT_VALIDADE_SENHA', 'QT_SOLIC_1', 'QT_AUTORIZADA_1', 'DT_AUTORIZACAO']
      for (const nome of camposInteresse) {
        const loc = popup.locator(`[name="${nome}"]`).first()
        const existe = await loc.count()
        if (existe > 0) {
          const valor = await loc.inputValue().catch(() => '(erro ao ler)')
          console.log(`CAMPO ${nome}: existe, valor="${valor}"`)
        } else {
          console.log(`CAMPO ${nome}: NAO EXISTE nesta tela`)
        }
      }

      console.log('URL_FINAL:', popup.url())
      console.log('TITLE_FINAL:', await popup.title().catch(() => '?'))
    }
  } catch (error) {
    console.error('ERRO:', error?.message ?? error)
    await page.screenshot({ path: '/tmp/explorar-erro.png', fullPage: true }).catch(() => {})
  } finally {
    await browser.close()
  }
}

main()

// Script de diagnostico avulso (nao roda pelo server.js/fila de jobs):
// abre a tela de digitacao de guia SP/SADT ate o ponto de selecionar o
// "Nome do Contratado", le as opcoes do select "Profissional Executante"
// (nome + codigo_operadora de cada profissional vinculado a clinica na
// Unimed) e para por ai — nunca chega a Finalizar nem a nenhuma acao que
// grave algo no portal. So leitura.
import fs from 'fs'
import { chromium } from 'playwright'
import {
  login,
  abrirBeneficiario,
  preencherCarteirinha,
  atualizarCadastroSeNecessario,
  textoRestricao,
  splitCarteirinha,
} from '../src/portal.js'
import {
  abrirSpSadt,
  preencherFormularioPrincipal,
  selecionarContratado,
} from '../src/operations/gerarGuia.js'

async function main() {
  const credential = JSON.parse(process.env.UNIMED_CREDENTIAL_JSON)
  const patient = JSON.parse(process.env.UNIMED_PATIENT_JSON)
  const card = splitCarteirinha(patient.carteirinha)

  const browser = await chromium.launch({ headless: true })
  let page = await browser.newPage()

  try {
    await login(page, credential)
    const mainPage = page

    page = await abrirBeneficiario(page)
    await preencherCarteirinha(page, card)

    const restriction = await textoRestricao(page)
    if (restriction) {
      console.log('RESTRICAO_BENEFICIARIO:', restriction)
      return
    }

    await atualizarCadastroSeNecessario(page)
    page = await abrirSpSadt(page, mainPage)
    await preencherFormularioPrincipal(page, {})
    await selecionarContratado(page, credential.nome_contratado)

    // So leitura a partir daqui: nao clicamos em mais nada.
    await page.waitForTimeout(2000)

    const select = page.locator('[name="CD_PROFISSIONAL"], #CD_PROFISSIONAL')
    const visible = await select.isVisible({ timeout: 5000 }).catch(() => false)
    console.log('SELECT_VISIVEL:', visible)

    const count = await select.locator('option').count()
    console.log('OPTIONS_COUNT:', count)

    const options = []
    for (let i = 0; i < count; i += 1) {
      const opt = select.locator('option').nth(i)
      const value = await opt.getAttribute('value')
      const text = (await opt.innerText()).trim()
      options.push({ value, text })
    }

    fs.writeFileSync('/tmp/profissionais-unimed.json', JSON.stringify(options, null, 2))
    console.log('SALVO', options.length, 'opcoes em /tmp/profissionais-unimed.json')
  } catch (error) {
    console.error('ERRO:', error?.message ?? error)
    await page.screenshot({ path: '/tmp/erro-listar-profissionais.png', fullPage: true }).catch(() => {})
  } finally {
    await browser.close()
  }
}

main()

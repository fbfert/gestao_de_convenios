// Script de diagnostico avulso (nao roda pelo server.js/fila de jobs):
// abre a tela de digitacao de guia SP/SADT ate o campo de procedimento
// (CD_ITEM_1) e, pra cada codigo em UNIMED_CODIGOS_JSON, digita o codigo e le
// se o portal exibe DS_ITEM_GENERICO_1 (== exige descricao manual do item
// generico). NUNCA avanca pra anexos, profissional executante nem Finalizar
// — so leitura de um campo. Objetivo: descobrir quais códigos do cadastro
// Especialidade x Convenio (com descricao_operadora/valor_generico nulos)
// precisam de descricao antes que a automacao real encontre um deles.
//
// Dados sensiveis (credencial, carteirinha, medico) NUNCA hardcoded aqui —
// tudo via env vars, no mesmo padrao de listar-profissionais.js.
import fs from 'fs'
import { chromium } from 'playwright'
import {
  login,
  abrirBeneficiario,
  preencherCarteirinha,
  atualizarCadastroSeNecessario,
  textoRestricao,
  waitProcessing,
  splitCarteirinha,
  DEFAULT_TIMEOUT,
} from '../src/portal.js'
import {
  abrirSpSadt,
  selecionarContratado,
  selecionarPrestador,
  preencherFormularioPrincipal,
} from '../src/operations/gerarGuia.js'

async function main() {
  const credential = JSON.parse(process.env.UNIMED_CREDENTIAL_JSON)
  const patient = JSON.parse(process.env.UNIMED_PATIENT_JSON) // { carteirinha, medico: {nome, crm, crm_uf}, cid }
  const codigos = JSON.parse(process.env.UNIMED_CODIGOS_JSON) // [{codigo, especialidade}, ...]
  const card = splitCarteirinha(patient.carteirinha)

  const browser = await chromium.launch({ headless: true })
  let page = await browser.newPage()
  const resultados = []

  try {
    await login(page, credential)
    const mainPage = page

    page = await abrirBeneficiario(page)
    await preencherCarteirinha(page, card)

    const restricao1 = await textoRestricao(page)
    if (restricao1) {
      console.log('RESTRICAO_BENEFICIARIO:', restricao1)
      return
    }

    await atualizarCadastroSeNecessario(page)
    page = await abrirSpSadt(page, mainPage)

    const restricao2 = await textoRestricao(page)
    if (restricao2) {
      console.log('RESTRICAO_SPSADT:', restricao2)
      return
    }

    await selecionarContratado(page, credential.nome_contratado)
    await selecionarPrestador(page, patient.medico)
    await preencherFormularioPrincipal(page, { cid: patient.cid ?? '' })

    // So leitura a partir daqui: nunca preenchemos anexos, profissional
    // executante nem clicamos em Finalizar.
    for (const { codigo, especialidade } of codigos) {
      const campoCodigo = page.locator('[name="CD_ITEM_1"], #CD_ITEM_1')
      await campoCodigo.fill('', { timeout: DEFAULT_TIMEOUT })
      await campoCodigo.fill(codigo, { timeout: DEFAULT_TIMEOUT })
      await page.keyboard.press('Tab').catch(() => {})
      await waitProcessing(page)

      const generico = await page
        .locator('[name="DS_ITEM_GENERICO_1"], #DS_ITEM_GENERICO_1')
        .isVisible({ timeout: 800 })
        .catch(() => false)

      const linha = { codigo, especialidade, generico }
      resultados.push(linha)
      console.log(`${codigo}\t${especialidade}\tgenerico=${generico}`)
    }

    fs.writeFileSync('/tmp/sonda-item-generico-resultado.json', JSON.stringify(resultados, null, 2))
    console.log('SALVO', resultados.length, 'resultados em /tmp/sonda-item-generico-resultado.json')
  } catch (error) {
    console.error('ERRO:', error?.message ?? error)
    await page.screenshot({ path: '/tmp/erro-sonda-item-generico.png', fullPage: true }).catch(() => {})
  } finally {
    await browser.close()
  }
}

main()

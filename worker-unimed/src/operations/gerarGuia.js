import { chromium } from 'playwright'
import {
  DEFAULT_TIMEOUT,
  WorkerResultError,
  abrirBeneficiario,
  atualizarCadastroSeNecessario,
  fillIfVisible,
  hasText,
  login,
  mapPortalStatus,
  parseNumber,
  pickValue,
  preencherCarteirinha,
  selectIfVisible,
  splitCarteirinha,
  textoRestricao,
  waitProcessing,
  normalize,
} from '../portal.js'

const ACCEPTED_UPLOAD_EXTENSIONS = new Set(['jpg', 'gif', 'doc', 'jpeg', 'xls', 'png', 'zip', 'pdf'])
const MAX_UPLOAD_SIZE = 5 * 1024 * 1024

export async function executarGerarGuia(request, options = {}) {
  const browser = options.page ? null : await chromium.launch({ headless: true })
  const page = options.page ?? await browser.newPage()

  try {
    return await gerarGuia(page, request)
  } catch (error) {
    if (error instanceof WorkerResultError) {
      return error.result
    }

    return {
      status: 'failed',
      error_code: 'WORKER_INTERNAL_FATAL',
      message: error instanceof Error ? error.message : 'Falha interna no worker.',
    }
  } finally {
    if (browser) {
      await browser.close()
    }
  }
}

export async function gerarGuia(page, request) {
  const payload = request.payload ?? {}
  const credential = payload.credential ?? {}
  const medico = payload.medico ?? {}
  const card = splitCarteirinha(payload.paciente?.carteirinha)

  await login(page, credential)
  const mainPage = page
  page = await abrirBeneficiario(page)
  await preencherCarteirinha(page, card)

  const restriction = await textoRestricao(page)
  if (restriction) {
    return {
      status: 'succeeded',
      execution_id: request.executionId ?? null,
      guia_status: 'needs_verification',
      status_guia: 'needs_verification',
      numero_guia: null,
      unimed_status: restriction,
      status_operadora: restriction,
      message: 'Beneficiario com restricao administrativa.',
    }
  }

  await atualizarCadastroSeNecessario(page)
  page = await abrirSpSadt(page, mainPage)
  await preencherFormularioPrincipal(page, payload)
  await selecionarContratado(page, credential.nome_contratado)
  const estrategiaMedico = await selecionarPrestador(page, medico)
  await preencherProcedimento(page, payload)
  await enviarAnexos(page, payload)
  await selecionarProfissionalExecutante(page, payload)

  return await finalizar(page, request, estrategiaMedico)
}

/**
 * Sem id nos dois cliques: sao links "Digitação de guia SP/SADT" e "Digitar
 * solicitação manualmente e realizar validação para autorizar." na tela do
 * beneficiario, achados por texto.
 *
 * O segundo clique tem um comportamento irregular no portal real: as vezes a
 * navegacao continua na mesma popup do beneficiario, mas as vezes o portal
 * fecha essa popup e devolve o controle pra janela original (que ja tinha
 * navegado ate a listagem de exames), navegando ela ate o formulario. Como
 * isso alterna, checamos qual das duas sobrou de pe depois do clique.
 */
async function abrirSpSadt(page, mainPage) {
  await page.getByText('Digitação de guia SP/SADT').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
  await page.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT })

  await page
    .getByText('Digitar solicitação manualmente e realizar validação para autorizar.')
    .click({ timeout: DEFAULT_TIMEOUT })
    .catch(() => {})

  await new Promise((resolve) => setTimeout(resolve, 1500))

  if (page.isClosed()) {
    await mainPage.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT }).catch(() => {})
    return mainPage
  }

  await waitProcessing(page)
  await page.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT }).catch(() => {})
  return page
}

async function preencherFormularioPrincipal(page, payload) {
  await fillIfVisible(page, '[name="DT_EMISSAO_GUIA"], #DT_EMISSAO_GUIA', formatPortalDate(new Date()))
  await selectIfVisible(page, '[name="FG_ATENDIMENTO_RN"], #FG_ATENDIMENTO_RN', 'N')
  await selectIfVisible(page, '[name="DM_CARATER_SOLIC"], #DM_CARATER_SOLIC', '1')
  await selectIfVisible(page, '[name="DM_TP_ATEND_SADT"], #DM_TP_ATEND_SADT', '03')
  await selectIfVisible(page, '[name="DM_TP_ACIDENTE"], #DM_TP_ACIDENTE', '9')
  await fillIfVisible(page, '[name="DS_INDIC_CLINICA"], #DS_INDIC_CLINICA', String(payload.cid ?? ''))
}

/**
 * Campo "Nome do Contratado *" — a clinica contratada executante da guia,
 * nao o profissional solicitante (esse e o `selecionarPrestador` abaixo).
 * Sem preencher isto o Finalizar falha com "O valor do campo Nome do
 * contratado é obrigatório", sem nenhum outro sinal — so aparece revisitando
 * a pagina depois, ja que o worker so espera pelo `#resultado-guia` e
 * silenciosamente retorna 'uncertain' quando essa validacao barra o envio.
 */
async function selecionarContratado(page, nomeContratado) {
  const nome = String(nomeContratado ?? '').trim()
  if (!nome) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'CONFIGURATION_MISSING_CONTRATADO',
      message: 'Nome do contratado (clínica na Unimed) não configurado.',
    })
  }

  const popup = await abrirBuscaContratado(page)
  await fillIfVisible(popup, '[name="s_nm_prestador"], #s_nm_prestador', nome)
  await submeterBusca(popup)
  await waitProcessing(popup)
  const encontrado = await escolherPrestadorAtivo(popup, nome)

  // O clique em "Selecionar" dispara um postback assincrono da popup pro
  // NM_CONTRATADO da pagina principal — fechar a popup logo em seguida
  // (sem esperar) mata esse postback antes de terminar e o campo fica
  // vazio, sem nenhum erro visivel na hora (so estoura depois, no
  // Finalizar, como "Nome do contratado é obrigatório").
  await page.waitForTimeout(2000)
  await fecharSePossivel(popup)

  if (!encontrado) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'CONTRATADO_NOT_FOUND',
      message: 'Contratado (clínica) não encontrado ou inativo no portal.',
    })
  }
}

async function abrirBuscaContratado(page) {
  const [popup] = await Promise.all([
    page.context().waitForEvent('page', { timeout: DEFAULT_TIMEOUT }),
    page.locator('#link_busca_contrt').click({ timeout: DEFAULT_TIMEOUT }),
  ])
  await popup.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT })
  await popup.waitForTimeout(1000)
  return popup
}

/**
 * `#link_busca_solic` ("solic" de solicitante) abre a popup de busca do
 * profissional solicitante (com `s_nr_crm`/`Button_DoSearch` dentro dela,
 * nao na pagina da guia). Toda a busca daqui pra frente roda nessa popup.
 */
async function selecionarPrestador(page, medico) {
  const crm = String(medico.crm ?? '').trim()
  if (crm) {
    const popup = await abrirBuscaPrestador(page)
    await fillIfVisible(popup, '[name="s_nr_crm"], #s_nr_crm', crm)
    await submeterBusca(popup)
    await waitProcessing(popup)
    if (await escolherPrestadorAtivo(popup, medico.nome)) {
      return 'crm'
    }
    await fecharSePossivel(popup)
  }

  {
    const popup = await abrirBuscaPrestador(page)
    await fillIfVisible(popup, '[name="s_nm_prestador"], #s_nm_prestador', String(medico.nome ?? ''))
    await submeterBusca(popup)
    await waitProcessing(popup)
    if (await escolherPrestadorAtivo(popup, medico.nome)) {
      return 'nome'
    }
    await fecharSePossivel(popup)
  }

  {
    const popup = await abrirBuscaPrestador(page)
    await fillIfVisible(popup, '[name="s_nm_prestador"], #s_nm_prestador', 'nao cooperado')
    await submeterBusca(popup)
    await waitProcessing(popup)
    if (await escolherPrestadorAtivo(popup, 'MEDICO NAO COOPERADO')) {
      return 'nao_cooperado'
    }
    await fecharSePossivel(popup)
  }

  throw new WorkerResultError({
    status: 'failed',
    error_code: 'PRESTADOR_SOLICITANTE_NOT_FOUND',
    message: 'Prestador solicitante ativo não encontrado.',
  })
}

async function preencherProcedimento(page, payload) {
  const codigo = String(payload.codigo_procedimento ?? '').trim()
  if (!codigo) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'CONFIGURATION_INVALID_ITEM',
      message: 'Código de procedimento ausente no payload.',
    })
  }

  await page.locator('[name="CD_ITEM_1"], #CD_ITEM_1').fill(codigo, { timeout: DEFAULT_TIMEOUT })
  await page.keyboard.press('Tab').catch(() => {})
  await waitProcessing(page)

  const quantidade = String(payload.quantidade ?? payload.quantidade_padrao ?? 1)
  await fillIfVisible(page, '[name="NR_QTD_1"], #NR_QTD_1', quantidade)

  const genericDescription = page.locator('[name="DS_ITEM_GENERICO_1"], #DS_ITEM_GENERICO_1')
  if (await genericDescription.isVisible({ timeout: 500 }).catch(() => false)) {
    const descricao = payload.valor_generico || payload.descricao_operadora || codigo
    await genericDescription.fill(String(descricao), { timeout: DEFAULT_TIMEOUT })
    await fillIfVisible(page, '[name="VL_ITEM_GENERICO_1"], #VL_ITEM_GENERICO_1', '0,01')
  }
}

async function enviarAnexos(page, payload) {
  const anexos = normalizarAnexos(payload)
  const pedido = anexos.find((anexo) => anexo.tipo === 'pedido_medico')
  if (!pedido) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'PEDIDO_MEDICO_REQUIRED',
      message: 'Pedido Médico obrigatório não informado.',
    })
  }

  for (const anexo of anexos) {
    validarAnexo(anexo)
    await uploadAnexo(page, anexo)
  }
}

async function selecionarProfissionalExecutante(page, payload) {
  const codigo = String(payload.codigo_profissional_operadora ?? '').trim()
  if (!codigo) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'CONFIGURATION_INVALID_EXECUTOR',
      message: 'Código do profissional executante ausente no payload.',
    })
  }

  // O select ja esta na pagina (actionable) antes das opcoes serem
  // repopuladas depois da popup de anexo fechar — e quanto maior o anexo,
  // mais demora. Um tempo fixo nao da conta em toda solicitacao; esperamos
  // ativamente a opcao certa aparecer, ate um teto generoso.
  const select = page.locator('[name="CD_PROFISSIONAL"], #CD_PROFISSIONAL')
  const opcao = select.locator(`option[value="${codigo}"]`)
  await opcao.waitFor({ state: 'attached', timeout: 30000 }).catch(() => {})

  await select.selectOption(codigo, { timeout: DEFAULT_TIMEOUT }).catch(() => {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'PROFISSIONAL_EXECUTANTE_NOT_FOUND',
      message: 'Profissional executante não encontrado no portal.',
    })
  })
}

async function finalizar(page, request, estrategiaMedico) {
  const finalize = page.locator('[name="Button_Finalizar"]')
  await finalize.click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)
  await page.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT }).catch(() => {})
  await page.waitForTimeout(1500)

  const result = await parseResultado(page)

  if (!result) {
    return {
      status: 'uncertain',
      execution_id: request.executionId ?? null,
      error_code: 'UNCERTAIN_AFTER_SUBMIT',
      message: 'Resultado ambíguo após finalizar guia. Não houve retry automático.',
    }
  }

  return {
    status: 'succeeded',
    execution_id: request.executionId ?? null,
    ...result,
    medico_strategy: estrategiaMedico,
  }
}

/**
 * A confirmacao real (`lista_impressao.do`, apos o Finalizar navegar pra
 * fora da tela de digitacao) nao tem nenhum id/data-attr no resultado — so
 * uma tabela comum com cabecalho "Nº Guia"/"Situação"/"Senha de
 * Autorização"/"Procedimentos". `#resultado-guia`/`[data-result="guia"]`
 * (usados antes aqui) nunca existiram na pagina real, nem no sucesso: por
 * isso toda solicitacao — mesmo as que o portal aceitou de verdade — voltava
 * 'uncertain'. Le pelas colunas do cabecalho, nao por selector fixo, ja que
 * nao ha nenhum marcador estavel.
 */
async function parseResultado(page) {
  const bodyText = await page.locator('body').innerText().catch(() => '')
  const tables = page.locator('table')
  const count = await tables.count()

  for (let i = count - 1; i >= 0; i -= 1) {
    const table = tables.nth(i)
    const headerCells = await table.locator('tr').first().locator('th, td').allInnerTexts().catch(() => [])
    const guiaIdx = headerCells.findIndex((h) => normalize(h).includes('GUIA'))
    if (guiaIdx === -1) continue

    const situacaoIdx = headerCells.findIndex((h) => normalize(h).includes('SITUA'))
    const senhaIdx = headerCells.findIndex((h) => normalize(h).includes('SENHA'))
    const procedimentosIdx = headerCells.findIndex((h) => normalize(h).includes('PROCEDIMENTO'))

    const rows = table.locator('tr')
    const rowCount = await rows.count()

    for (let r = 1; r < rowCount; r += 1) {
      const cells = await rows.nth(r).locator('td').allInnerTexts().catch(() => [])
      const numeroGuia = cells[guiaIdx]?.trim()
      if (!numeroGuia) continue

      const procedimentosTexto = procedimentosIdx >= 0 ? cells[procedimentosIdx] ?? '' : ''
      const statusOperadora = situacaoIdx >= 0 ? (cells[situacaoIdx] ?? '').trim() : ''

      return {
        numero_guia: numeroGuia,
        protocolo_operadora: pickValue(bodyText, /Protocolo de Atendimento:\s*([A-Za-z0-9.-]+)/i),
        sessoes_solicitadas: parseNumber(pickValue(procedimentosTexto, /Qtd:\s*(\d+)/i)),
        sessoes_autorizadas: parseNumber(pickValue(procedimentosTexto, /Qtd Aut:\s*(\d+)/i)),
        senha: senhaIdx >= 0 ? (cells[senhaIdx] ?? '').trim() || null : null,
        unimed_status: statusOperadora,
        status_operadora: statusOperadora,
        guia_status: mapPortalStatus(statusOperadora),
      }
    }
  }

  return null
}

async function escolherPrestadorAtivo(page, nomeEsperado) {
  const rows = page.locator('[data-prestador-row], table tr')
  const count = await rows.count()
  const expected = normalize(String(nomeEsperado ?? ''))

  for (let index = 0; index < count; index += 1) {
    const row = rows.nth(index)
    const text = await row.innerText().catch(() => '')
    const normalized = normalize(text)
    const active = normalized.includes('OK - ATIVO') || normalized.includes('OK ATIVO')
    const nameMatches = !expected || normalized.includes(expected)

    if (active && nameMatches) {
      await row.locator('button, a, input[type="button"]').first().click({ timeout: DEFAULT_TIMEOUT })
      await waitProcessing(page)
      return true
    }
  }

  return false
}

/**
 * Preencher o CRM (ou o nome) sozinho ja dispara a busca no portal real —
 * o campo tem um handler que submete perguntar do usuario. Quando isso
 * acontece a pagina ja navegou pro resultado antes deste clique rodar, e o
 * botao simplesmente nao existe mais ali; so clicamos se ele ainda estiver.
 */
async function submeterBusca(page) {
  const botao = page.locator('[name="Button_DoSearch"]').first()
  if (await botao.isVisible({ timeout: 500 }).catch(() => false)) {
    await botao.click({ timeout: DEFAULT_TIMEOUT }).catch(() => {})
  }
  // A busca (auto-disparada ou pelo clique acima) navega pro resultado; sem
  // esperar aqui, escolherPrestadorAtivo as vezes conta a tabela antes dela
  // terminar de carregar e conclui "nao encontrado" tarde demais.
  await page.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT }).catch(() => {})
  await page.waitForTimeout(800)
}

/**
 * Abre uma popup de busca nova a cada tentativa em vez de reaproveitar a
 * mesma popup entre CRM/nome/nao-cooperado. O link "Refazer pesquisa" que
 * devolveria pro formulario dentro da mesma popup e instavel (as vezes some
 * antes do timeout de visibilidade) — reabrir do zero pelo mesmo
 * `#link_busca_solic` da pagina principal, que ja se mostrou confiavel, e
 * mais lento mas nao depende dessa navegacao interna.
 */
async function abrirBuscaPrestador(page) {
  const [popup] = await Promise.all([
    page.context().waitForEvent('page', { timeout: DEFAULT_TIMEOUT }),
    page.locator('#link_busca_solic').click({ timeout: DEFAULT_TIMEOUT }),
  ])
  await popup.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT })
  // A popup nasce em about:blank antes de navegar pro formulario de busca de
  // verdade; domcontentloaded sozinho as vezes resolve cedo demais (ainda no
  // about:blank), antes do form estar no ar.
  await popup.waitForTimeout(1000)
  return popup
}

async function fecharSePossivel(popup) {
  await popup.close().catch(() => {})
}

async function uploadAnexo(page, anexo) {
  // `path` e relativo ao storage do Laravel (so faz sentido dentro do
  // gescon-app); `local_path` e o caminho absoluto, o unico que o worker
  // consegue de fato abrir no volume compartilhado.
  const path = anexo.local_path ?? anexo.path

  if (!path || path.startsWith('fixture:')) {
    await uploadAnexoMock(page, anexo)
    return
  }

  // Sem input de arquivo na tela principal: cada item tem seu proprio icone
  // de anexo (#item_anexos_N, so ativo depois do codigo do procedimento
  // preenchido), que abre uma popup propria com o formulario de upload de
  // verdade — "Anexar" (Button_Insert) grava o arquivo, "Finalizar"
  // (btn_finalizar) fecha a popup e volta pra guia.
  const [popup] = await Promise.all([
    page.context().waitForEvent('page', { timeout: DEFAULT_TIMEOUT }),
    page.locator('#item_anexos_1').click({ timeout: DEFAULT_TIMEOUT, force: true }),
  ])
  await popup.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT })
  await popup.waitForTimeout(500)

  await popup.locator('input[type="file"]').first().setInputFiles(path, { timeout: DEFAULT_TIMEOUT })
  await popup.locator('[name="Button_Insert"]').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(popup)
  await popup.waitForTimeout(500)

  // Nao da pra confirmar pelo nome original: o portal exibe o nome do
  // ARQUIVO NO DISCO (prefixado com o codigo do procedimento), e o Laravel
  // guarda o pedido medico com nome aleatorio (uuid), nunca igual ao
  // nome_original do banco. "Total de registros" e o sinal confiavel.
  const totalTexto = await popup.locator('body').innerText({ timeout: DEFAULT_TIMEOUT }).catch(() => '')
  const totalRegistros = Number(totalTexto.match(/Total de registros:\s*(\d+)/)?.[1] ?? 0)
  const confirmed = totalRegistros >= 1

  await popup.locator('#btn_finalizar').click({ timeout: DEFAULT_TIMEOUT }).catch(() => {})
  await popup.close().catch(() => {})

  if (!confirmed) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: anexo.tipo === 'pedido_medico' ? 'PEDIDO_MEDICO_UPLOAD_FAILED' : 'UPLOAD_FAILED',
      message: `Upload não confirmado para ${anexo.tipo}.`,
    })
  }
}

async function uploadAnexoMock(page, anexo) {
  await page.evaluate((item) => {
    if (window.__forceUploadFailure) {
      return
    }
    window.__uploadedFiles = window.__uploadedFiles || []
    window.__uploadedFiles.push(item)
    const list = document.querySelector('#uploaded-files')
    if (list) {
      const li = document.createElement('li')
      li.textContent = item.nome_original || item.tipo
      li.dataset.tipo = item.tipo
      list.appendChild(li)
    }
  }, anexo)

  await waitProcessing(page)
  const confirmedByType = await page.locator(`#uploaded-files [data-tipo="${anexo.tipo}"]`)
    .first()
    .isVisible({ timeout: DEFAULT_TIMEOUT })
    .catch(() => false)
  const confirmedByName = await page.getByText(anexo.nome_original ?? anexo.tipo)
    .first()
    .isVisible({ timeout: 500 })
    .catch(() => false)
  const confirmed = confirmedByType || confirmedByName

  if (!confirmed) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: anexo.tipo === 'pedido_medico' ? 'PEDIDO_MEDICO_UPLOAD_FAILED' : 'UPLOAD_FAILED',
      message: `Upload não confirmado para ${anexo.tipo}.`,
    })
  }
}

function normalizarAnexos(payload) {
  const anexos = []
  if (payload.pedido_medico) anexos.push({ ...payload.pedido_medico, tipo: 'pedido_medico' })
  for (const anexo of payload.anexos ?? []) {
    anexos.push(anexo)
  }
  return anexos.filter(Boolean)
}

function validarAnexo(anexo) {
  const name = String(anexo.nome_original ?? anexo.path ?? '')
  const extension = name.includes('.') ? name.split('.').pop().toLowerCase() : ''
  if (extension && !ACCEPTED_UPLOAD_EXTENSIONS.has(extension)) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'UPLOAD_TYPE_NOT_ALLOWED',
      message: `Tipo de arquivo não aceito para ${anexo.tipo}.`,
    })
  }

  if (Number(anexo.size ?? 0) > MAX_UPLOAD_SIZE) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'UPLOAD_TOO_LARGE',
      message: `Arquivo maior que 5 MB para ${anexo.tipo}.`,
    })
  }
}

function formatPortalDate(date) {
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  return `${day}/${month}/${date.getFullYear()}`
}

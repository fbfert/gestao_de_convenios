import { chromium } from 'playwright'
import {
  DEFAULT_TIMEOUT,
  WorkerResultError,
  hasText,
  login,
  waitForPopup,
  waitProcessing,
} from '../portal.js'

/**
 * Finaliza na Unimed uma guia já cumprida na clínica.
 *
 * O passo que faltava do ciclo: as sessões estão lançadas no Gescon, a folha
 * assinada está anexada, e alguém ainda precisava abrir o portal e digitar
 * até dez datas à mão. Aqui isso vira uma operação como as outras.
 *
 * Duas coisas moldam o código todo:
 *
 * 1. NÃO EXISTE HOMOLOGAÇÃO. Este arquivo foi escrito contra a descrição de
 *    quem opera o portal e uma fixture montada a partir do HTML que ela
 *    forneceu — o portal real só será visto em produção. Por isso cada passo
 *    confere o EFEITO do que fez (o valor ficou no campo? o anexo entrou na
 *    lista?) e falha com um código próprio em vez de seguir adiante no
 *    escuro. A primeira execução real precisa dizer onde parou, não só que
 *    parou.
 *
 * 2. O ÚLTIMO CLIQUE NÃO TEM VOLTA. "Gravar e Finalizar" encerra a guia na
 *    operadora. Por isso o modo simulação (`payload.simular`) percorre tudo e
 *    para antes dele, devolvendo screenshot do que teria sido enviado.
 */

/** Fixos para a Unimed — ver a spec `automacao-unimed-finalizar-guia`. */
const REGIME_AMBULATORIAL = '01'
const TIPO_OUTRAS_TERAPIAS = '03'

/** O portal aceita no máximo dez datas de série por guia. */
const MAXIMO_DE_SERIES = 10

export async function executarFinalizarGuia(request, options = {}) {
  const browser = options.page ? null : await chromium.launch({ headless: true })
  const page = options.page ?? (await browser.newPage())

  try {
    return await finalizarGuia(page, request)
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

export async function finalizarGuia(page, request) {
  const payload = request.payload ?? {}
  const credential = payload.credential ?? {}
  const numeroGuia = String(payload.numero_guia ?? '').trim()
  const sessoes = normalizarSessoes(payload.sessoes ?? [])
  const anexos = payload.anexos ?? []
  const simular = Boolean(payload.simular)

  if (!numeroGuia) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'NUMERO_GUIA_AUSENTE',
      message: 'A finalização precisa do número da guia.',
    })
  }

  if (sessoes.length === 0) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'SEM_SESSOES_PARA_ENVIAR',
      message: 'A finalização precisa de ao menos uma sessão para preencher.',
    })
  }

  await login(page, credential)

  const execucao = await abrirExecucaoDaGuia(page, numeroGuia)

  await fixarRegimeETipo(execucao)
  await conferirQuantidadeAutorizada(execucao, sessoes.length, payload.sessoes_autorizadas)
  await preencherDatasDaSerie(execucao, sessoes)

  for (const anexo of anexos) {
    await anexarFolha(execucao, anexo)
  }

  if (simular) {
    // Tudo preenchido, nada gravado. A evidência é o que permite conferir no
    // portal, com olhos humanos, o que teria sido enviado.
    const evidencia = await capturarEvidencia(execucao)

    return {
      status: 'succeeded',
      simulado: true,
      numero_guia: numeroGuia,
      sessoes_preenchidas: sessoes.length,
      anexos_enviados: anexos.length,
      evidencia,
      message: 'Simulação concluída: a guia foi preenchida no portal, mas NÃO foi gravada nem finalizada.',
    }
  }

  await gravarEFinalizar(execucao)

  return {
    status: 'succeeded',
    simulado: false,
    numero_guia: numeroGuia,
    sessoes_preenchidas: sessoes.length,
    anexos_enviados: anexos.length,
  }
}

/**
 * Da home pós-login até a tela de execução da guia.
 *
 * O caminho até a tela de busca é a parte menos conhecida do fluxo: quem opera
 * descreveu o campo (`s_nr_guia`) e o botão (`Button_FIltro`), não a URL. Por
 * isso o worker aceita um caminho configurado (`payload.caminho_busca_guia`) e,
 * na falta dele, procura o campo onde já está. Não achando, falha dizendo onde
 * parou — a primeira execução real devolve a URL que falta.
 */
async function abrirExecucaoDaGuia(page, numeroGuia) {
  if (!(await campoDeBuscaVisivel(page))) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'TELA_BUSCA_GUIA_NAO_ENCONTRADA',
      message: 'Não encontrei o campo de busca de guia (s_nr_guia) depois do login.',
      diagnostico: await diagnosticar(page),
    })
  }

  await page.locator('[name="s_nr_guia"]').fill(numeroGuia, { timeout: DEFAULT_TIMEOUT })
  await page.locator('[name="Button_FIltro"]').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)

  // A linha do resultado traz o número da guia como link. Casar pelo texto
  // exato, e não pegar o primeiro link da lista: o filtro pode devolver mais
  // de uma linha, e abrir a guia errada aqui significaria finalizar a guia de
  // outro paciente.
  const link = page.locator('a.MagnetoDataLink', { hasText: new RegExp(`^\\s*${escaparRegex(numeroGuia)}\\s*$`) })

  if ((await link.count()) === 0) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'GUIA_NAO_ENCONTRADA_NO_PORTAL',
      message: `A busca não devolveu a guia ${numeroGuia}.`,
      diagnostico: await diagnosticar(page),
    })
  }

  await link.first().click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)

  // A execução abre na mesma aba. Se abrisse em popup, `waitForPopup` seria o
  // caminho — mas o link é um <a href> comum no HTML que temos.
  const chegou = await page
    .locator('#DM_REGIME_ATEND')
    .waitFor({ timeout: Math.max(DEFAULT_TIMEOUT, 15000) })
    .then(() => true)
    .catch(() => false)

  if (!chegou) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'TELA_EXECUCAO_NAO_ABRIU',
      message: 'Cliquei no número da guia mas a tela de execução não apareceu.',
      diagnostico: await diagnosticar(page),
    })
  }

  return page
}

async function campoDeBuscaVisivel(page) {
  return page
    .locator('[name="s_nr_guia"]')
    .first()
    .isVisible({ timeout: Math.max(DEFAULT_TIMEOUT, 10000) })
    .catch(() => false)
}

/**
 * Regime ambulatorial e tipo "outras terapias", sempre — sobrescrevendo o que
 * a tela trouxer pré-selecionado.
 *
 * Confere o valor depois de selecionar: um `selectOption` que não encontra a
 * opção lança, mas um que "funciona" numa tela que depois reseta o campo por
 * script próprio, não. A conferência pega os dois casos.
 */
async function fixarRegimeETipo(page) {
  await selecionarEConferir(page, '#DM_REGIME_ATEND', REGIME_AMBULATORIAL, 'REGIME_ATENDIMENTO_RECUSADO')
  await selecionarEConferir(page, '#DM_TP_ATEND_SADT', TIPO_OUTRAS_TERAPIAS, 'TIPO_ATENDIMENTO_RECUSADO')
}

async function selecionarEConferir(page, seletor, valor, errorCode) {
  await page.locator(seletor).selectOption(valor, { timeout: DEFAULT_TIMEOUT }).catch(async (erro) => {
    throw new WorkerResultError({
      status: 'failed',
      error_code: errorCode,
      message: `Não consegui selecionar "${valor}" em ${seletor}: ${erro.message}`,
      diagnostico: await diagnosticar(page),
    })
  })

  const ficou = await page.locator(seletor).inputValue({ timeout: DEFAULT_TIMEOUT }).catch(() => null)

  if (ficou !== valor) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: errorCode,
      message: `Selecionei "${valor}" em ${seletor}, mas o campo ficou com "${ficou}".`,
      diagnostico: await diagnosticar(page),
    })
  }
}

/**
 * A quantidade autorizada na tela precisa bater com a que a API mandou.
 *
 * Divergência aqui é problema de dado, não de execução: significa que o Gescon
 * e o portal discordam sobre quantas sessões a operadora liberou. Preencher
 * assim mesmo — para mais ou para menos — seria escolher um dos dois no
 * escuro.
 */
async function conferirQuantidadeAutorizada(page, aPreencher, autorizadaSegundoAApi) {
  const texto = await page.locator('#QT_AUTORIZADA_1').inputValue({ timeout: DEFAULT_TIMEOUT }).catch(() => null)

  if (texto === null) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'QT_AUTORIZADA_NAO_LIDA',
      message: 'Não consegui ler a quantidade autorizada (QT_AUTORIZADA_1) na tela de execução.',
      diagnostico: await diagnosticar(page),
    })
  }

  const noPortal = Number(String(texto).trim())

  if (!Number.isFinite(noPortal) || noPortal <= 0) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'QT_AUTORIZADA_NAO_LIDA',
      message: `A quantidade autorizada na tela não é um número utilizável: "${texto}".`,
      diagnostico: await diagnosticar(page),
    })
  }

  if (autorizadaSegundoAApi != null && Number(autorizadaSegundoAApi) !== noPortal) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'QT_AUTORIZADA_DIVERGENTE',
      message:
        `O Gescon diz ${autorizadaSegundoAApi} sessões autorizadas e o portal diz ${noPortal}. `
        + 'Confira a guia antes de finalizar.',
      qt_autorizada_portal: noPortal,
      qt_autorizada_gescon: Number(autorizadaSegundoAApi),
    })
  }

  if (aPreencher > noPortal) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'SESSOES_ACIMA_DO_AUTORIZADO',
      message: `Tenho ${aPreencher} sessões para enviar e o portal autoriza ${noPortal}.`,
      qt_autorizada_portal: noPortal,
    })
  }

  if (aPreencher > MAXIMO_DE_SERIES) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'SESSOES_ACIMA_DO_MAXIMO',
      message: `O portal aceita ${MAXIMO_DE_SERIES} datas de série e recebi ${aPreencher}.`,
    })
  }
}

/**
 * Preenche `dt_serie_1..N`, da sessão mais antiga para a mais recente.
 *
 * O formato de cada campo é a maior incógnita do fluxo (`dd/mm/aaaa hh:mm` é o
 * palpite de quem opera, não um fato observado). Por isso o worker confere o
 * que FICOU no campo depois de escrever: se o portal recusou, normalizou para
 * outra coisa ou deixou vazio, a falha carrega o que foi enviado e o que o
 * campo aceitou — e a primeira execução real devolve o formato certo em vez de
 * só "falhou".
 */
async function preencherDatasDaSerie(page, sessoes) {
  for (const [indice, sessao] of sessoes.entries()) {
    const seletor = `#dt_serie_${indice + 1}`
    const campo = page.locator(seletor)

    if ((await campo.count()) === 0) {
      throw new WorkerResultError({
        status: 'failed',
        error_code: 'DT_SERIE_CAMPO_INDISPONIVEL',
        message: `A tela não tem o campo ${seletor} para a sessão ${indice + 1}.`,
        diagnostico: await diagnosticar(page),
      })
    }

    if (await campo.isDisabled({ timeout: DEFAULT_TIMEOUT }).catch(() => false)) {
      throw new WorkerResultError({
        status: 'failed',
        error_code: 'DT_SERIE_CAMPO_INDISPONIVEL',
        message: `O campo ${seletor} está desabilitado — não consegui preencher a sessão ${indice + 1}.`,
        diagnostico: await diagnosticar(page),
      })
    }

    const valor = formatarDataSerie(sessao)

    await campo.fill(valor, { timeout: DEFAULT_TIMEOUT })
    // O datepicker do Magneto reformata no blur; sem tirar o foco, a leitura
    // abaixo veria o texto cru que acabamos de digitar, não o que o campo
    // aceitou de fato.
    await campo.blur({ timeout: DEFAULT_TIMEOUT }).catch(() => {})
    await waitProcessing(page)

    const ficou = await campo.inputValue({ timeout: DEFAULT_TIMEOUT }).catch(() => '')

    if (!valorFoiAceito(valor, ficou)) {
      throw new WorkerResultError({
        status: 'failed',
        error_code: 'DT_SERIE_FORMATO_RECUSADO',
        message:
          `Preenchi ${seletor} com "${valor}" e o campo ficou com "${ficou}". `
          + 'O formato esperado pelo portal provavelmente é outro.',
        campo: seletor,
        enviado: valor,
        aceito: ficou,
        diagnostico: await diagnosticar(page),
      })
    }
  }
}

/**
 * Anexa uma folha, abrindo a popup uma vez por arquivo.
 *
 * Reabrir a cada folha é o comportamento conservador: não sabemos se a popup
 * aceita vários arquivos em sequência, e abrir de novo funciona nos dois
 * casos. Confere a lista depois de cada envio — anexo silenciosamente perdido
 * é pior que anexo recusado com erro.
 */
async function anexarFolha(page, anexo) {
  const caminho = anexo.local_path ?? anexo.path

  if (!caminho) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'ANEXO_SEM_CAMINHO',
      message: `A folha "${anexo.nome_original ?? '(sem nome)'}" veio sem caminho de arquivo.`,
    })
  }

  const gatilho = page.locator('[title="Item anexos"], #item_anexos_1').first()

  if ((await gatilho.count()) === 0) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'ANEXO_BOTAO_NAO_ENCONTRADO',
      message: 'Não encontrei o ícone de anexos na tela de execução.',
      diagnostico: await diagnosticar(page),
    })
  }

  const popup = await waitForPopup(
    page,
    () => gatilho.click({ timeout: DEFAULT_TIMEOUT, force: true }),
    { timeout: Math.max(DEFAULT_TIMEOUT, 15000), contexto: 'anexarFolha' },
  )

  await popup.waitForLoadState('domcontentloaded', { timeout: DEFAULT_TIMEOUT })
  await popup.waitForTimeout(500)

  await popup
    .locator('[name="File_NM_ARQUIVO_FISICO_File"], input[type="file"]')
    .first()
    .setInputFiles(caminho, { timeout: DEFAULT_TIMEOUT })

  await popup.locator('[name="Button_Insert"]').click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(popup)
  await popup.waitForTimeout(500)

  // Mesmo sinal que gerarGuia usa: o portal exibe o nome do arquivo no disco
  // (uuid), nunca o nome original, então "Total de registros" é o que dá para
  // conferir.
  const corpo = await popup.locator('body').innerText({ timeout: DEFAULT_TIMEOUT }).catch(() => '')
  const total = Number(corpo.match(/Total de registros:\s*(\d+)/)?.[1] ?? 0)

  await popup.locator('#btn_finalizar, [name="btn_finalizar"]').first().click({ timeout: DEFAULT_TIMEOUT }).catch(() => {})
  await popup.close().catch(() => {})

  if (total < 1) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'ANEXO_NAO_CONFIRMADO',
      message: `O portal não confirmou o envio da folha "${anexo.nome_original ?? caminho}".`,
      anexo: anexo.nome_original ?? caminho,
    })
  }
}

/** O clique sem volta. Só chega aqui fora do modo simulação. */
async function gravarEFinalizar(page) {
  const botao = page.locator('#Button_Submit, [name="Button_Submit"]').first()

  if ((await botao.count()) === 0) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'BOTAO_GRAVAR_FINALIZAR_NAO_ENCONTRADO',
      message: 'Não encontrei o botão "Gravar e Finalizar" na tela de execução.',
      diagnostico: await diagnosticar(page),
    })
  }

  // Mesmo padrão do Finalizar em gerarGuia.js: o clique dispara navegação, e o
  // portal já demorou mais que os 5s do timeout padrão sob carga real.
  await botao.click({ timeout: Math.max(DEFAULT_TIMEOUT, 30000) })
  await waitProcessing(page)

  const erroNaTela = await primeiroErroVisivel(page)

  if (erroNaTela) {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'GRAVAR_FINALIZAR_RECUSADO',
      message: `O portal recusou a finalização: ${erroNaTela}`,
      diagnostico: await diagnosticar(page),
    })
  }
}

/**
 * Erro que o portal mostra na própria tela, sem mudar de página.
 *
 * O Magneto responde 200 com a mensagem pintada no corpo — sem isto, uma
 * recusa ("campo obrigatório não preenchido") passaria por sucesso.
 */
async function primeiroErroVisivel(page) {
  for (const marcador of ['MagnetoErrorMessage', 'MagnetoMensagemErro', 'erro']) {
    const alvo = page.locator(`.${marcador}`).first()

    if ((await alvo.count()) > 0) {
      const texto = await alvo.innerText({ timeout: 1000 }).catch(() => '')

      if (texto.trim() !== '') {
        return texto.replace(/\s+/g, ' ').trim().slice(0, 300)
      }
    }
  }

  if (await hasText(page, 'Campo obrigatório')) {
    return 'Campo obrigatório não preenchido.'
  }

  return null
}

async function capturarEvidencia(page) {
  const screenshot = await page
    .screenshot({ fullPage: true })
    .then((buffer) => buffer.toString('base64'))
    .catch(() => null)

  return {
    url: page.url(),
    screenshot_base64: screenshot,
    regime_atendimento: await page.locator('#DM_REGIME_ATEND').inputValue().catch(() => null),
    tipo_atendimento: await page.locator('#DM_TP_ATEND_SADT').inputValue().catch(() => null),
    datas_preenchidas: await lerDatasPreenchidas(page),
  }
}

async function lerDatasPreenchidas(page) {
  const valores = []

  for (let i = 1; i <= MAXIMO_DE_SERIES; i++) {
    const valor = await page.locator(`#dt_serie_${i}`).inputValue().catch(() => '')

    if (valor && valor.trim() !== '') {
      valores.push(valor.trim())
    }
  }

  return valores
}

/**
 * O que a tela mostrava quando algo deu errado.
 *
 * Existe porque não há homologação: sem isto, a primeira execução em produção
 * devolveria "falhou" e nada mais, e o próximo passo seria adivinhar.
 */
async function diagnosticar(page) {
  const texto = await page
    .locator('body')
    .innerText({ timeout: 1000 })
    .then((valor) => valor.replace(/\s+/g, ' ').trim().slice(0, 500))
    .catch(() => '(não consegui ler a página)')

  return { url: page.url(), pagina: texto }
}

/**
 * Ordena por data e hora e descarta o que não tem data.
 *
 * A ordem importa: o portal lê `dt_serie_1..N` como a série cronológica, e
 * mandar fora de ordem é declarar sessões que não aconteceram assim.
 */
function normalizarSessoes(sessoes) {
  return sessoes
    .filter((sessao) => sessao && sessao.data)
    .map((sessao) => ({ data: String(sessao.data), hora: sessao.hora ? String(sessao.hora) : null }))
    .sort((a, b) => `${a.data} ${a.hora ?? '00:00'}`.localeCompare(`${b.data} ${b.hora ?? '00:00'}`))
}

/**
 * `aaaa-mm-dd` + `HH:MM` no formato que o portal parece esperar.
 *
 * Isolado numa função por ser a incógnita do fluxo: quando a primeira execução
 * real disser o formato certo, é só aqui que se mexe.
 */
export function formatarDataSerie(sessao) {
  const [ano, mes, dia] = String(sessao.data).slice(0, 10).split('-')
  const data = `${dia}/${mes}/${ano}`

  return sessao.hora ? `${data} ${sessao.hora.slice(0, 5)}` : data
}

/**
 * O campo aceitou o que escrevemos?
 *
 * Comparação tolerante de propósito: o datepicker pode devolver `08/04/2026`
 * onde mandamos `08/04/2026 14:50` (campo só de data), e isso não é recusa —
 * é o portal dizendo que a hora vai em outro lugar. Recusa é campo vazio ou
 * com uma data diferente da que mandamos.
 */
export function valorFoiAceito(enviado, ficou) {
  const limpo = String(ficou ?? '').trim()

  if (limpo === '') {
    return false
  }

  const dataEnviada = String(enviado).slice(0, 10)

  return limpo.startsWith(dataEnviada)
}

function escaparRegex(valor) {
  return valor.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

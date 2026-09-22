import { chromium } from 'playwright'
import { DEFAULT_TIMEOUT, WorkerResultError, login, waitProcessing } from '../portal.js'

/**
 * Pergunta ao portal se a guia já está entre os "Exames finalizados".
 *
 * Existe por causa do passivo: a clínica finalizou guias no portal durante
 * meses antes de a automação existir, e o Gescon não sabe disso. A tela de
 * exames finalizados responde a pergunta guia a guia, e é a mesma mecânica de
 * filtro (`s_nr_guia` + `Button_FIltro`) que a consulta de status já usa em
 * "Exames em aberto" — ver statusSenha.js, que aprendeu ao vivo cada uma das
 * esperas replicadas aqui.
 *
 * A diferença que importa: **esta tela vem com a data inicial preenchida**, e é
 * ela que decide se as guias antigas aparecem. Limpar `s_dt_ini` é o passo
 * crítico, e por isso é conferido em vez de presumido — ver `limparDataInicial`.
 *
 * A operação não altera nada no portal. É uma pergunta.
 */

export async function executarConferirGuiaFinalizada(request, options = {}) {
  const browser = options.page ? null : await chromium.launch({ headless: true })
  const page = options.page ?? (await browser.newPage())

  try {
    return await conferirGuiasFinalizadas(page, request)
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

export async function conferirGuiasFinalizadas(page, request) {
  const payload = request.payload ?? {}
  const guias = Array.isArray(payload.guias) ? payload.guias : [payload]

  // Sem guia a conferir, nem abre o portal — ver a spec. Login é caro e a
  // Unimed não precisa receber uma visita para não perguntarmos nada.
  if (guias.length === 0) {
    return {
      status: 'succeeded',
      execution_id: request.executionId ?? null,
      operation: 'conferir_guia_finalizada',
      results: [],
      resumo: resumir([]),
    }
  }

  await login(page, payload.credential ?? {})

  /*
   * Um login para o lote inteiro, como consultarStatusBatch faz. Login é o
   * passo mais lento e mais frágil do fluxo (LOGIN_TIMEOUT de 30s em
   * portal.js), e o passivo pode ter dezenas de guias.
   */
  const results = []

  for (const guia of guias) {
    results.push(await conferirUma(page, guia))
  }

  return {
    status: 'succeeded',
    execution_id: request.executionId ?? null,
    operation: 'conferir_guia_finalizada',
    results,
    resumo: resumir(results),
  }
}

/**
 * Uma guia falhar não pode deixar as outras sem resposta — mesma escolha de
 * `consultarStatusBatch`. A falha vira o desfecho DAQUELA guia, e o lote segue.
 */
async function conferirUma(page, guia) {
  const numeroGuia = String(guia.numero_guia ?? '').trim()
  const identificacao = { guia_id: guia.guia_id ?? guia.id ?? null, numero_guia: guia.numero_guia ?? null }

  if (!numeroGuia) {
    return {
      ...identificacao,
      desfecho: 'falhou',
      error_code: 'NUMERO_GUIA_AUSENTE',
      message: 'A guia não tem número da operadora — não há o que buscar no portal.',
    }
  }

  try {
    await abrirExamesFinalizados(page)
    await limparDataInicial(page)

    const encontrada = await buscarGuia(page, numeroGuia)

    return { ...identificacao, desfecho: encontrada ? 'finalizada' : 'nao_finalizada' }
  } catch (error) {
    if (error instanceof WorkerResultError) {
      return {
        ...identificacao,
        desfecho: 'falhou',
        error_code: error.result.error_code,
        message: error.result.message,
        diagnostico: error.result.diagnostico ?? null,
      }
    }

    return {
      ...identificacao,
      desfecho: 'falhou',
      error_code: 'WORKER_INTERNAL_FATAL',
      message: error instanceof Error ? error.message : 'Falha interna ao conferir a guia.',
    }
  }
}

/**
 * A tela é alcançada por um ícone (`ico16examFinalizada.gif`) cujo marcador
 * exato não conhecemos: esta operação foi escrita contra a descrição de quem
 * opera o portal, não contra o HTML.
 *
 * Por isso tenta os caminhos plausíveis — o id, o texto do link, a imagem pelo
 * nome do arquivo — e falha nomeando a tela quando nenhum funciona, em vez de
 * seguir numa página qualquer e concluir que a guia não está finalizada.
 *
 * Achado ao vivo em 22/09/2026: o seletor que bate é sempre
 * `a:has-text("Exames finalizados")` — o clique acontece e navega pra URL
 * certa (`finalizadas.do`), mas a checagem de chegada usava
 * `locator.isVisible({ timeout })`. O Playwright **ignora silenciosamente**
 * esse `timeout` em `isVisible()` — a própria tipagem do pacote documenta
 * "does not wait for the element to become visible and returns immediately".
 * Ou seja: nunca houve espera nenhuma, nem os 10s originais nem os 30s de uma
 * primeira tentativa de correção aqui mesmo (confirmado ao vivo: um lote de
 * 50 guias "esperando" 30s cada rodou em 12s). `waitFor` é quem de fato
 * espera — mesmo padrão já usado logo abaixo em `buscarGuia` para "exame(s)
 * encontrado(s)".
 */
async function abrirExamesFinalizados(page) {
  const candidatos = [
    '#exames-finalizados',
    'a:has-text("Exames finalizados")',
    'a:has-text("Exames Finalizados")',
    'img[src*="examFinalizada"]',
  ]

  const ABRIR_EXAMES_FINALIZADOS_TIMEOUT = Math.max(DEFAULT_TIMEOUT, 30000)

  for (const seletor of candidatos) {
    const alvo = page.locator(seletor).first()

    if ((await alvo.count()) === 0) {
      continue
    }

    await alvo.click({ timeout: DEFAULT_TIMEOUT, force: true }).catch(() => {})
    await waitProcessing(page)

    // O sinal de que chegamos é o formulário de filtro desta tela.
    const chegou = await page
      .locator('[name="s_nr_guia"]')
      .first()
      .waitFor({ state: 'visible', timeout: ABRIR_EXAMES_FINALIZADOS_TIMEOUT })
      .then(() => true)
      .catch(() => false)

    if (chegou) {
      return
    }
  }

  throw new WorkerResultError({
    status: 'failed',
    error_code: 'TELA_EXAMES_FINALIZADOS_NAO_ABRIU',
    message: 'Não consegui abrir a tela de exames finalizados no portal.',
    diagnostico: await diagnosticar(page),
  })
}

/**
 * O passo que não pode falhar em silêncio.
 *
 * O portal traz `s_dt_ini` preenchida com uma data recente. Filtrar com ela é
 * perguntar "esta guia foi finalizada nos últimos dias?", quando a pergunta é
 * "foi finalizada alguma vez?" — e as guias do passivo são exatamente as
 * antigas.
 *
 * O dano de errar aqui é assimétrico e invisível: num lote, dezenas de guias
 * seriam marcadas como "conferida, não estava lá" de uma vez, e o erro sumiria
 * no meio do resultado. Por isso o campo é LIDO de volta depois de limpo.
 */
async function limparDataInicial(page) {
  const campo = page.locator('[name="s_dt_ini"]').first()

  if ((await campo.count()) === 0) {
    // Sem o campo não há data escondendo nada — a tela pode simplesmente não
    // ter esse filtro. Não é falha.
    return
  }

  await campo.fill('', { timeout: DEFAULT_TIMEOUT })
  // O datepicker do Magneto pode repor o valor no blur; sem tirar o foco, a
  // leitura veria o campo vazio que acabamos de deixar, e não o que o portal
  // decidiu manter.
  await campo.blur({ timeout: DEFAULT_TIMEOUT }).catch(() => {})

  const ficou = await campo.inputValue({ timeout: DEFAULT_TIMEOUT }).catch(() => null)

  if (ficou !== null && ficou.trim() !== '') {
    throw new WorkerResultError({
      status: 'failed',
      error_code: 'FILTRO_DATA_NAO_LIMPO',
      message:
        `Limpei a data inicial do filtro e o portal a repôs como "${ficou}". `
        + 'Buscar assim esconderia as guias antigas, que são justamente as que interessam.',
      valor_reposto: ficou,
      diagnostico: await diagnosticar(page),
    })
  }
}

/** Devolve true quando a guia aparece no resultado do filtro. */
async function buscarGuia(page, numeroGuia) {
  await page.locator('[name="s_nr_guia"]').first().fill(numeroGuia, { timeout: DEFAULT_TIMEOUT })
  await page.locator('[name="Button_FIltro"]').first().click({ timeout: DEFAULT_TIMEOUT })
  await waitProcessing(page)

  /*
   * Esperar o rótulo "exame(s) encontrado(s)", e não só waitProcessing.
   *
   * Achado ao vivo em 10/09/2026 na consulta de status (ver statusSenha.js): o
   * indicador "Processando..." some ANTES de a tabela ser reescrita, e ler o
   * DOM nesse intervalo devolve o resultado do filtro anterior. Aqui isso
   * viraria um "não finalizada" falso.
   *
   * 30s e não 10s (achado ao vivo em 22/09/2026, mesma causa do timeout em
   * abrirExamesFinalizados acima): a tabela de Exames finalizados deste
   * tenant tem 1.512 guias no histórico, bem mais pesada que a de Exames em
   * aberto.
   */
  await page
    .getByText(/exame\(s\) encontrado\(s\)/)
    .first()
    .waitFor({ state: 'visible', timeout: Math.max(DEFAULT_TIMEOUT, 30000) })
    .catch(() => {})

  return await linhaDaGuia(page, numeroGuia)
}

async function linhaDaGuia(page, numeroGuia) {
  const linhas = page.locator('table tr')
  const total = await linhas.count()

  for (let i = 0; i < total; i += 1) {
    const texto = await linhas.nth(i).innerText().catch(() => '')

    if (texto.includes(String(numeroGuia))) {
      return true
    }
  }

  return false
}

function resumir(results) {
  return {
    finalizadas: results.filter((item) => item.desfecho === 'finalizada').length,
    nao_finalizadas: results.filter((item) => item.desfecho === 'nao_finalizada').length,
    falhas: results.filter((item) => item.desfecho === 'falhou').length,
  }
}

async function diagnosticar(page) {
  const texto = await page
    .locator('body')
    .innerText({ timeout: 1000 })
    .then((valor) => valor.replace(/\s+/g, ' ').trim().slice(0, 500))
    .catch(() => '(não consegui ler a página)')

  return { url: page.url(), pagina: texto }
}

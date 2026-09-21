import { expect, request, test, type APIRequestContext, type Page } from '@playwright/test'

// Sem o sufixo /api: caminho iniciado por barra ANULA o caminho do baseURL.
const API = 'http://127.0.0.1:8001'

/**
 * A conferência de guias finalizadas na operadora, pelo navegador — ver a spec
 * `conferencia-de-guias-finalizadas`.
 *
 * A suíte sobe o **worker de verdade** (ver `playwright.config.ts`), servindo
 * as fixtures HTML do disco. Isso permite exercitar o caminho inteiro — API →
 * worker → marca na guia → tela — sem abrir nenhuma rota de teste no código de
 * produção. A credencial da Unimed aponta para a fixture via `file:`, desvio
 * que o worker só aceita sob `UNIMED_PERMITIR_FIXTURES_LOCAIS` e que produção
 * nunca liga.
 *
 * A fila roda em `sync` no ambiente de teste, então a conferência acontece
 * dentro da própria requisição: quando o POST responde, a marca já está lá.
 */

import { fileURLToPath, pathToFileURL } from 'node:url'
import { dirname, resolve } from 'node:path'

const __dirname = dirname(fileURLToPath(import.meta.url))

/**
 * A fixture de "Exames finalizados" do worker, servida por `file:`.
 *
 * Ela considera finalizados apenas dois números fixos (ver o topo do arquivo),
 * o que dá aos testes um lado "está lá" e um lado "não está" sem precisar do
 * portal real.
 */
const FIXTURE_EXAMES_FINALIZADOS = pathToFileURL(
  resolve(__dirname, '../../../worker-unimed/tests/fixtures/portal-exames-finalizados.html'),
).href

/** Números que a fixture dá por finalizados. */
const GUIA_FINALIZADA_NA_FIXTURE = '50144618222'

async function apiAutenticada(): Promise<APIRequestContext> {
  const anonima = await request.newContext({ baseURL: API })
  const resposta = await anonima.post('/api/login', {
    data: { email: 'admin@clinica-exemplo.test', password: 'password' },
  })
  expect(resposta.status(), await resposta.text()).toBe(200)
  const { token } = await resposta.json()
  await anonima.dispose()

  return request.newContext({
    baseURL: API,
    extraHTTPHeaders: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  })
}

async function login(page: Page) {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

async function unimedId(api: APIRequestContext): Promise<number> {
  const convenios = (await (await api.get('/api/convenios')).json()).data as Array<{
    id: number
    nome: string
  }>
  const unimed = convenios.find((item) => item.nome === 'Unimed')
  expect(unimed, 'esperava o convênio Unimed na semente').toBeTruthy()

  return unimed!.id
}

/**
 * Liga a automação na Unimed, e o `afterEach` devolve o convênio a manual.
 *
 * A suíte compartilha um banco, e `mvp-flow` cria guia à mão na Unimed — coisa
 * que `GuiaService::criar` recusa para convênio do robô. Mesmo cuidado de
 * `adicionar-sessoes.spec.ts` e `finalizar-na-unimed.spec.ts`.
 */
async function ligarAutomacaoUnimed(
  api: APIRequestContext,
  convenioId: number,
  opcoes: { baseUrl?: string } = {},
): Promise<void> {
  await api.patch(`/api/convenios/${convenioId}`, {
    data: { nome: 'Unimed', connector_type: 'scraping', connector_driver: 'unimed_rda', ativo: true },
  })

  const credenciais = await api.put(`/api/configuracoes/convenios-credenciais/${convenioId}`, {
    data: {
      driver: 'unimed_rda',
      ativo: true,
      credenciais: {
        login: 'operador-unimed',
        password: 'senha-unimed',
        /*
         * A fixture por padrão, e não um host inexistente.
         *
         * Com o worker de verdade no ar, apontar para um host que não resolve
         * faz a execução falhar com código estrutural — e o disjuntor
         * (UnimedCircuitBreakerService) PAUSA a credencial do convênio, o que
         * derruba os testes seguintes com "credencial não configurada".
         * Achado ao subir o worker na suíte.
         */
        base_url: opcoes.baseUrl ?? FIXTURE_EXAMES_FINALIZADOS,
      },
    },
  })
  expect(credenciais.ok(), await credenciais.text()).toBeTruthy()
}

test.afterEach(async () => {
  const api = await apiAutenticada()
  const convenios = (await (await api.get('/api/convenios')).json()).data as Array<{
    id: number
    nome: string
  }>
  const unimed = convenios.find((item) => item.nome === 'Unimed')

  if (unimed) {
    /*
     * Reativa a credencial antes de devolver o convênio a manual.
     *
     * Uma execução que falhe com código estrutural faz o disjuntor pausar a
     * credencial, e ela ficaria pausada para o próximo teste — que veria
     * "credencial não configurada" e falharia por um motivo alheio ao que
     * testa.
     */
    await api.post(`/api/configuracoes/convenios-credenciais/${unimed.id}/reativar`).catch(() => {})

    await api.patch(`/api/convenios/${unimed.id}`, {
      data: { nome: 'Unimed', connector_type: 'manual', connector_driver: null, ativo: true },
    })
  }

  await api.dispose()
})

async function guiaUnimed(
  api: APIRequestContext,
  convenioId: number,
  numeroFixo?: string,
): Promise<{ id: number; numero: string }> {
  const sufixo = String(Date.now()).slice(-9)

  const paciente = await api.post('/api/pacientes', {
    data: {
      nome: `Paciente Conferencia E2E ${sufixo}`,
      carteirinha: `0220${sufixo}0002`,
      convenio_id: convenioId,
      ativo: true,
    },
  })
  expect(paciente.status(), await paciente.text()).toBe(201)

  const profissional = (await (await api.get('/api/profissionais')).json()).data[0] as {
    id: number
    especialidade_id: number
  }

  // Número fixo quando o teste precisa que a fixture reconheça a guia.
  const numero = numeroFixo ?? `CONF-E2E-${sufixo}`
  const criada = await api.post('/api/guias', {
    data: {
      convenio_id: convenioId,
      paciente_id: (await paciente.json()).data.id,
      profissional_id: profissional.id,
      especialidade_id: profissional.especialidade_id,
      numero_guia: numero,
      tipo_terapia: 'especializada',
      sessoes_solicitadas: 10,
      sessoes_autorizadas: 10,
      data_solicitacao: new Date().toISOString().slice(0, 10),
    },
  })
  expect(criada.status(), await criada.text()).toBe(201)

  return { id: (await criada.json()).data.id, numero }
}

test('a guia Unimed oferece "Conferir na Unimed", e o disparo chega à API', async ({ page }) => {
  const api = await apiAutenticada()
  const convenioId = await unimedId(api)
  const guia = await guiaUnimed(api, convenioId)
  await ligarAutomacaoUnimed(api, convenioId)
  await api.dispose()

  await login(page)
  await page.goto('/guias', { waitUntil: 'domcontentloaded' })
  await page.getByTestId('guia-filtro-numero-guia').fill(guia.numero)
  await page.getByTestId('guia-filtro-numero-guia').press('Enter')

  const botao = page.getByTestId(`guia-conferir-finalizada-${guia.id}`)
  await expect(botao).toBeVisible({ timeout: 15000 })
  await expect(botao).toHaveText('Conferir na Unimed')

  const disparo = page.waitForResponse(
    (resposta) =>
      resposta.request().method() === 'POST' &&
      resposta.url().includes(`/guias/${guia.id}/conferir-finalizada-unimed`),
  )

  await botao.click()
  expect((await disparo).status()).toBe(202)
})

test('a ação em lote existe e enfileira as guias ainda não conferidas', async ({ page }) => {
  const api = await apiAutenticada()
  const convenioId = await unimedId(api)
  await guiaUnimed(api, convenioId)
  await ligarAutomacaoUnimed(api, convenioId)
  await api.dispose()

  await login(page)
  await page.goto('/guias', { waitUntil: 'domcontentloaded' })

  const disparo = page.waitForResponse(
    (resposta) =>
      resposta.request().method() === 'POST' &&
      resposta.url().includes('/guias/conferir-finalizadas-unimed'),
  )

  await page.getByTestId('guias-conferir-lote-botao').click()

  const resposta = await disparo
  expect(resposta.status()).toBe(202)

  const corpo = await resposta.json()
  expect(corpo.data.operacao).toBe('conferir_guia_finalizada')
  expect(corpo.data.total_guias).toBeGreaterThan(0)

  await expect(page.getByTestId('guias-conferir-lote-total')).toBeVisible()
})

test('o filtro do passivo existe e, sem guia marcada, não devolve nada', async ({ page }) => {
  const api = await apiAutenticada()
  const convenioId = await unimedId(api)
  const guia = await guiaUnimed(api, convenioId)
  await api.dispose()

  await login(page)
  await page.goto('/guias', { waitUntil: 'domcontentloaded' })

  // A guia recém-criada aparece na listagem normal...
  await page.getByTestId('guia-filtro-numero-guia').fill(guia.numero)
  await page.getByTestId('guia-filtro-numero-guia').press('Enter')
  await expect(page.getByTestId(`guia-status-${guia.id}`)).toBeVisible({ timeout: 15000 })

  // ...e some quando o filtro pede só as finalizadas na operadora, porque
  // ninguém conferiu nada ainda.
  await page.getByTestId('guia-filtro-finalizada-operadora').click()
  await expect(page.getByTestId(`guia-status-${guia.id}`)).toHaveCount(0)
})

test('guia de convênio sem automação não oferece a conferência', async ({ page }) => {
  const api = await apiAutenticada()
  const convenios = (await (await api.get('/api/convenios')).json()).data as Array<{
    id: number
    connector_driver: string | null
  }>
  const manual = convenios.find((item) => item.connector_driver !== 'unimed_rda')
  const guia = await guiaUnimed(api, manual!.id)
  await api.dispose()

  await login(page)
  await page.goto('/guias', { waitUntil: 'domcontentloaded' })
  await page.getByTestId('guia-filtro-numero-guia').fill(guia.numero)
  await page.getByTestId('guia-filtro-numero-guia').press('Enter')

  await expect(page.getByTestId(`guia-status-${guia.id}`)).toBeVisible({ timeout: 15000 })
  await expect(page.getByTestId(`guia-conferir-finalizada-${guia.id}`)).toHaveCount(0)
})

/**
 * O caminho inteiro, com o worker de verdade contra a fixture.
 *
 * É o que nenhum teste da API alcança: a conferência roda, grava a marca, e a
 * tela reflete. A fila é `sync` no ambiente de teste, então quando o POST
 * responde a marca já existe.
 */
test('conferir de verdade marca a guia, e o selo aparece na tela', async ({ page }) => {
  const api = await apiAutenticada()
  const convenioId = await unimedId(api)
  // Número que a fixture dá por finalizado.
  const guia = await guiaUnimed(api, convenioId, GUIA_FINALIZADA_NA_FIXTURE)
  await ligarAutomacaoUnimed(api, convenioId, { baseUrl: FIXTURE_EXAMES_FINALIZADOS })

  const conferencia = await api.post(`/api/guias/${guia.id}/conferir-finalizada-unimed`)
  expect(conferencia.status(), await conferencia.text()).toBe(202)

  // A marca já está gravada: a fila roda em `sync`.
  const depois = await (await api.get(`/api/guias/${guia.id}`)).json()
  expect(depois.data.finalizada_na_operadora_em, 'a fixture dá esta guia por finalizada').not.toBeNull()
  expect(depois.data.conferida_na_operadora_em).not.toBeNull()
  // E o status NÃO mudou — é marca própria, não status.
  expect(depois.data.status).toBe('under_review')
  await api.dispose()

  await login(page)
  await page.goto('/guias', { waitUntil: 'domcontentloaded' })
  await page.getByTestId('guia-filtro-numero-guia').fill(guia.numero)
  await page.getByTestId('guia-filtro-numero-guia').press('Enter')

  await expect(page.getByTestId(`guia-selo-operadora-${guia.id}`)).toBeVisible({ timeout: 15000 })
  await expect(page.getByTestId(`guia-selo-operadora-${guia.id}`)).toContainText('Finalizada na operadora')
  // Os dois convivem: o status conta o ciclo aqui, o selo conta o portal.
  await expect(page.getByTestId(`guia-status-${guia.id}`)).toBeVisible()

  // E o filtro do passivo passa a encontrá-la.
  await page.getByTestId('guia-filtro-finalizada-operadora').click()
  await expect(page.getByTestId(`guia-selo-operadora-${guia.id}`)).toBeVisible()
})

test('guia que a fixture NÃO dá por finalizada fica conferida e sem selo', async ({ page }) => {
  const api = await apiAutenticada()
  const convenioId = await unimedId(api)
  const guia = await guiaUnimed(api, convenioId, '50100000999')
  await ligarAutomacaoUnimed(api, convenioId, { baseUrl: FIXTURE_EXAMES_FINALIZADOS })

  const conferencia = await api.post(`/api/guias/${guia.id}/conferir-finalizada-unimed`)
  expect(conferencia.status(), await conferencia.text()).toBe(202)

  const depois = await (await api.get(`/api/guias/${guia.id}`)).json()
  expect(depois.data.finalizada_na_operadora_em).toBeNull()
  // Mas a conferência ficou registrada: é o que o lote seguinte usa para
  // saber o que já foi olhado.
  expect(depois.data.conferida_na_operadora_em).not.toBeNull()
  await api.dispose()

  await login(page)
  await page.goto('/guias', { waitUntil: 'domcontentloaded' })
  await page.getByTestId('guia-filtro-numero-guia').fill(guia.numero)
  await page.getByTestId('guia-filtro-numero-guia').press('Enter')

  await expect(page.getByTestId(`guia-status-${guia.id}`)).toBeVisible({ timeout: 15000 })
  await expect(page.getByTestId(`guia-selo-operadora-${guia.id}`)).toHaveCount(0)
  // O botão muda de texto: já foi conferida uma vez.
  await expect(page.getByTestId(`guia-conferir-finalizada-${guia.id}`)).toHaveText('Conferir de novo')
})

test('reconferir todas devolve ao lote as guias que já tinham sido conferidas', async ({ page }) => {
  const api = await apiAutenticada()
  const convenioId = await unimedId(api)
  const guia = await guiaUnimed(api, convenioId, GUIA_FINALIZADA_NA_FIXTURE)
  await ligarAutomacaoUnimed(api, convenioId, { baseUrl: FIXTURE_EXAMES_FINALIZADOS })

  // Confere uma vez: a guia sai do lote padrão.
  await api.post(`/api/guias/${guia.id}/conferir-finalizada-unimed`)
  await api.dispose()

  await login(page)
  await page.goto('/guias', { waitUntil: 'domcontentloaded' })

  const disparo = page.waitForResponse(
    (resposta) =>
      resposta.request().method() === 'POST' &&
      resposta.url().includes('/guias/conferir-finalizadas-unimed'),
  )

  await page.getByTestId('guias-reconferir-lote-botao').click()
  await page.getByTestId('confirm-dialog-confirmar').click()

  const resposta = await disparo
  expect(resposta.status()).toBe(202)
  expect((await resposta.json()).data.total_guias).toBeGreaterThan(0)
})

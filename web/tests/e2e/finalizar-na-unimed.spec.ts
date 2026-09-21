import { expect, request, test, type APIRequestContext, type Page } from '@playwright/test'

// Sem o sufixo /api: caminho iniciado por barra ANULA o caminho do baseURL.
const API = 'http://127.0.0.1:8001'

/**
 * A finalização na Unimed vista do navegador — ver a spec
 * `automacao-unimed-finalizar-guia`.
 *
 * O worker tem cobertura contra a fixture, e a API contra o worker falso.
 * O que só o navegador prova é o que está aqui: qual botão a tela oferece
 * conforme o convênio, e os diálogos de decisão que o pré-voo pede.
 *
 * Nenhum teste aqui chega a acionar o robô de verdade: a simulação nasce
 * ligada, e o que se exercita é a decisão antes do envio.
 *
 * O bloqueio por conflito de agenda NÃO tem teste aqui de propósito: montar a
 * precondição exigiria gravar sessões em conflito, e é exatamente isso que as
 * regras impedem pela API. Criá-las por fora provaria o teste, não o produto.
 * A cobertura desse caminho está em FinalizarGuiaUnimedApiTest
 * (`pre_voo_acusa_conflito_de_agenda_e_nao_libera` e
 * `disparo_recusa_com_conflito_de_agenda`), onde o passivo pode ser simulado
 * gravando direto no banco.
 */

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
 * Liga a automação na Unimed e garante credencial ativa.
 *
 * Chamada DEPOIS de a guia existir, sempre: `GuiaService::criar` recusa guia
 * criada à mão em convênio do robô — quem gera guia lá é a automação. Como o
 * que se testa aqui é a FINALIZAÇÃO, a guia nasce no convênio manual e o
 * convênio vira automatizado em seguida.
 *
 * O `afterEach` devolve o convênio a manual: a suíte inteira compartilha um
 * banco, e `mvp-flow` cria guia à mão na Unimed. Mesmo cuidado de
 * `adicionar-sessoes.spec.ts`.
 */
async function ligarAutomacaoUnimed(api: APIRequestContext, convenioId: number): Promise<void> {
  await api.patch(`/api/convenios/${convenioId}`, {
    data: {
      nome: 'Unimed',
      connector_type: 'scraping',
      connector_driver: 'unimed_rda',
      ativo: true,
    },
  })

  const credenciais = await api.put(`/api/configuracoes/convenios-credenciais/${convenioId}`, {
    data: {
      driver: 'unimed_rda',
      ativo: true,
      credenciais: {
        login: 'operador-unimed',
        password: 'senha-unimed',
        base_url: 'https://portal.unimed.test',
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
    await api.patch(`/api/convenios/${unimed.id}`, {
      data: { nome: 'Unimed', connector_type: 'manual', connector_driver: null, ativo: true },
    })
  }

  await api.dispose()
})

/**
 * Um paciente só deste arquivo.
 *
 * A suíte inteira compartilha um banco, e as regras de agenda são POR
 * PACIENTE: usar o paciente da semente faria as sessões criadas aqui
 * competirem com as de `antecipacoes` e `mvp-flow` pelos mesmos dias, e a
 * falha apareceria em spec alheio, de forma intermitente. Paciente próprio
 * isola isso pela raiz.
 */
async function pacienteExclusivo(api: APIRequestContext, convenioId: number): Promise<number> {
  const sufixo = String(Date.now()).slice(-9)

  const criado = await api.post('/api/pacientes', {
    data: {
      nome: `Paciente Finalizar E2E ${sufixo}`,
      carteirinha: `0220${sufixo}0000`,
      convenio_id: convenioId,
      ativo: true,
    },
  })
  expect(criado.status(), await criado.text()).toBe(201)

  return (await criado.json()).data.id
}

/**
 * Guia aprovada com `sessoes` sessões — uma por dia, porque o limite diário
 * da especialidade não deixa duas caírem no mesmo.
 */
async function guiaComSessoes(
  api: APIRequestContext,
  convenioId: number,
  sessoes: number,
  autorizadas: number,
): Promise<{ id: number; numero: string }> {
  const especialidades = (await (await api.get('/api/especialidades')).json()).data as Array<{
    id: number
    nome: string
  }>
  const aba = especialidades.find((item) => /\bABA\b/i.test(item.nome))
  expect(aba, 'esperava uma especialidade ABA na semente').toBeTruthy()

  const profissionais = (await (await api.get('/api/profissionais')).json()).data as Array<{
    id: number
    especialidade_id: number
  }>
  const profissional = profissionais.find((item) => item.especialidade_id === aba!.id)

  const pacienteId = await pacienteExclusivo(api, convenioId)

  const numero = `FINAL-E2E-${Date.now()}`
  const criada = await api.post('/api/guias', {
    data: {
      convenio_id: convenioId,
      paciente_id: pacienteId,
      profissional_id: profissional!.id,
      especialidade_id: aba!.id,
      numero_guia: numero,
      tipo_terapia: 'especializada',
      sessoes_solicitadas: autorizadas,
      sessoes_autorizadas: autorizadas,
      data_solicitacao: new Date().toISOString().slice(0, 10),
    },
  })
  expect(criada.status(), await criada.text()).toBe(201)
  const guia = (await criada.json()).data

  await api.patch(`/api/guias/${guia.id}/aprovar`, { data: {} })

  for (let i = 0; i < sessoes; i++) {
    const data = new Date()
    data.setDate(data.getDate() - (i + 1))

    const lancada = await api.post(`/api/guias/${guia.id}/lancamentos`, {
      data: {
        profissional_id: profissional!.id,
        data_sessao: data.toISOString().slice(0, 10),
        hora_inicio: '08:00',
      },
    })
    expect(lancada.status(), await lancada.text()).toBe(201)
  }

  return { id: guia.id, numero }
}

async function login(page: Page) {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

async function abrirGrupoDaGuia(page: Page, numero: string) {
  await page.goto('/lancamentos', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('lancamentos-page')).toBeVisible()
  await page.getByTestId('lancamento-filtro-busca').fill(numero)
  await expect(page.getByText(numero).first()).toBeVisible({ timeout: 15000 })
}

test('guia Unimed oferece Finalizar na Unimed, e não a finalização manual', async ({ page }) => {
  const api = await apiAutenticada()
  const convenioId = await unimedId(api)
  const guia = await guiaComSessoes(api, convenioId, 2, 2)
  await ligarAutomacaoUnimed(api, convenioId)
  await api.dispose()

  await login(page)
  await abrirGrupoDaGuia(page, guia.numero)

  await expect(page.getByTestId(`guia-finalizar-unimed-${guia.id}`)).toBeVisible()
  await expect(page.getByTestId(`guia-finalizar-${guia.id}`)).toHaveCount(0)

  await page.getByTestId(`guia-finalizar-unimed-${guia.id}`).click()

  // A simulação nasce ligada — a tela precisa deixar isso explícito antes de
  // qualquer clique, senão alguém acha que finalizou de verdade.
  await expect(page.getByTestId(`guia-finalizar-unimed-simulacao-${guia.id}`)).toBeVisible({
    timeout: 15000,
  })
})

test('pede confirmação para finalizar sem folha e com menos sessões que o autorizado', async ({ page }) => {
  const api = await apiAutenticada()
  const convenioId = await unimedId(api)
  const guia = await guiaComSessoes(api, convenioId, 2, 10)
  await ligarAutomacaoUnimed(api, convenioId)
  await api.dispose()

  await login(page)
  await abrirGrupoDaGuia(page, guia.numero)
  await page.getByTestId(`guia-finalizar-unimed-${guia.id}`).click()

  const menosSessoes = page.getByTestId('guia-finalizar-unimed-decisao-confirmar_menos_sessoes')
  const semAnexo = page.getByTestId('guia-finalizar-unimed-decisao-confirmar_sem_anexo')

  await expect(menosSessoes).toBeVisible({ timeout: 15000 })
  await expect(menosSessoes).toContainText('2')
  await expect(menosSessoes).toContainText('10')
  await expect(semAnexo).toBeVisible()

  // Enquanto faltar confirmar, não dá para enviar.
  await expect(page.getByTestId(`guia-finalizar-unimed-confirmar-${guia.id}`)).toBeDisabled()

  await page.getByTestId('guia-finalizar-unimed-decisao-check-confirmar_menos_sessoes').check()
  await expect(page.getByTestId(`guia-finalizar-unimed-confirmar-${guia.id}`)).toBeDisabled()

  await page.getByTestId('guia-finalizar-unimed-decisao-check-confirmar_sem_anexo').check()
  await expect(page.getByTestId(`guia-finalizar-unimed-confirmar-${guia.id}`)).toBeEnabled()
})

test('dispara a finalização e acompanha a execução até o fim', async ({ page }) => {
  const api = await apiAutenticada()
  const convenioId = await unimedId(api)
  const guia = await guiaComSessoes(api, convenioId, 2, 2)
  await ligarAutomacaoUnimed(api, convenioId)
  await api.dispose()

  await login(page)
  await abrirGrupoDaGuia(page, guia.numero)
  await page.getByTestId(`guia-finalizar-unimed-${guia.id}`).click()

  // Sem folha anexada: uma decisão a confirmar.
  await page.getByTestId('guia-finalizar-unimed-decisao-check-confirmar_sem_anexo').check()

  const disparo = page.waitForResponse(
    (resposta) =>
      resposta.request().method() === 'POST' &&
      resposta.url().includes(`/guias/${guia.id}/finalizar-unimed`),
  )

  await page.getByTestId(`guia-finalizar-unimed-confirmar-${guia.id}`).click()

  const resposta = await disparo
  expect(resposta.status()).toBe(202)

  const corpo = await resposta.json()
  expect(corpo.data.operacao).toBe('finalizar_guia')
  expect(corpo.data.simulado, 'a simulação nasce ligada').toBe(true)
})

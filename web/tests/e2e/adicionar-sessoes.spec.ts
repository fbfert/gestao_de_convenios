import { expect, request, test, type APIRequestContext, type Page } from '@playwright/test'

// Sem o sufixo /api: caminho iniciado por barra ANULA o caminho do baseURL.
const API = 'http://127.0.0.1:8001'

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

async function primeiro(api: APIRequestContext, caminho: string) {
  const resposta = await api.get(`/api${caminho}`)
  expect(resposta.ok(), `${caminho} -> ${resposta.status()}`).toBeTruthy()
  const corpo = await resposta.json()

  return (corpo.data ?? corpo)[0]
}

/**
 * Cria a solicitação pela API, e não pela interface.
 *
 * O objeto destes testes é o modal de adicionar sessões; o caminho até os dados
 * não pode derrubá-los por um motivo alheio.
 */
async function cenario(api: APIRequestContext, nomeConvenio: string): Promise<number> {
  const convenios = (await (await api.get('/api/convenios')).json()).data as Array<{
    id: number
    nome: string
    sessoes_por_guia: number | null
  }>
  const convenio = convenios.find((item) => item.nome === nomeConvenio)
  expect(convenio, `esperava o convênio ${nomeConvenio}`).toBeTruthy()

  const paciente = await primeiro(api, '/pacientes')
  const cid = await primeiro(api, '/cids')
  const medico = await primeiro(api, '/medicos')
  const profissional = await primeiro(api, '/profissionais')

  const criada = await api.post('/api/solicitacoes', {
    data: {
      paciente_id: paciente.id,
      convenio_id: convenio!.id,
      medico_id: medico.id,
      cid_ids: [cid.id],
      solicitado_em: new Date().toISOString().slice(0, 10),
      itens: [
        {
          especialidade_id: profissional.especialidade_id,
          profissional_id: profissional.id,
          quantidade: 10,
        },
      ],
    },
  })
  expect(criada.status(), await criada.text()).toBe(201)

  return (await criada.json()).data.id
}

/**
 * Devolve a Unimed ao que a semente cria: convênio MANUAL, sem driver.
 *
 * Um dos cenários precisa de convênio com automação, e a semente não traz
 * nenhum — então ele liga o driver da Unimed. Sem desfazer, o estrago é nas
 * OUTRAS specs: a suíte inteira compartilha um banco, e `mvp-flow` cria guia à
 * mão numa solicitação da Unimed, coisa que `GuiaService::criar` recusa quando
 * o convênio é do robô. Roda sempre, inclusive depois de falha.
 */
test.afterEach(async () => {
  const api = await apiAutenticada()
  const convenios = (await (await api.get('/api/convenios')).json()).data as Array<{
    id: number
    nome: string
  }>
  const unimed = convenios.find((item) => item.nome === 'Unimed')

  if (unimed) {
    await api.patch(`/api/convenios/${unimed.id}`, {
      data: {
        nome: unimed.nome,
        connector_type: 'manual',
        connector_driver: null,
        ativo: true,
      },
    })
  }

  await api.dispose()
})

async function login(page: Page) {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

async function abrirModal(page: Page, solicitacaoId: number) {
  await page.goto('/solicitacoes', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('solicitacoes-page')).toBeVisible()
  // A ação mora no menu de Ações: a célula é estreita demais para um segundo
  // botão, e ele transbordava para debaixo da coluna Info.
  await page.getByTestId(`solicitacao-acoes-${solicitacaoId}`).click()
  await page.getByTestId(`solicitacao-adicionar-sessoes-${solicitacaoId}`).click()
  await expect(page.getByTestId('adicionar-sessoes-modal')).toBeVisible()
}

test('adicionar repetido mostra o aviso, conclui, e o item novo vira 2ª remessa', async ({ page }) => {
  const api = await apiAutenticada()
  const id = await cenario(api, 'Unimed')
  await api.dispose()

  await login(page)
  await abrirModal(page, id)

  // Caminho A: repetir a especialidade já pedida.
  await page.locator('[data-testid^="adicionar-sessoes-item-"]').first().click()

  // O aviso de repetição aparece — e NÃO bloqueia. Bloquear a repetição
  // bloquearia o caso de uso principal da feature.
  await expect(page.getByTestId('aviso-repeticao')).toBeVisible()
  await expect(page.getByTestId('adicionar-sessoes-confirmar')).toBeEnabled()

  await page.getByTestId('adicionar-sessoes-confirmar').click()
  await expect(page.getByTestId('adicionar-sessoes-modal')).toBeHidden()

  // R9: sem isto a listagem mostra duas linhas idênticas.
  await expect(page.locator('[data-testid^="solicitacao-item-remessa-"]').first()).toHaveText(
    '2ª remessa',
  )
})

test('em convenio com automacao o item novo pode ser enviado', async ({ page }) => {
  const api = await apiAutenticada()
  const convenios = (await (await api.get('/api/convenios')).json()).data as Array<{
    id: number
    nome: string
    connector_driver: string | null
  }>
  const unimed = convenios.find((item) => item.nome === 'Unimed')!
  // A semente cria os três convênios como manuais; o cenário deste teste é o
  // convênio COM automação, onde quem gera a guia é o robô.
  const ligado = await api.patch(`/api/convenios/${unimed.id}`, {
    data: {
      nome: unimed.nome,
      connector_type: 'scraping',
      connector_driver: 'unimed_rda',
      ativo: true,
    },
  })
  expect(ligado.status(), await ligado.text()).toBeLessThan(300)

  const id = await cenario(api, 'Unimed')
  // Aprova para sair de under_review, que é o status que barra o envio.
  expect((await api.patch(`/api/solicitacoes/${id}/aprovar`)).status()).toBe(200)
  await api.dispose()

  await login(page)
  await abrirModal(page, id)
  await page.locator('[data-testid^="adicionar-sessoes-item-"]').first().click()
  await page.getByTestId('adicionar-sessoes-confirmar').click()
  await expect(page.getByTestId('adicionar-sessoes-modal')).toBeHidden()

  // O ponto da feature: o item novo entra numa solicitação já aprovada, o
  // status regride para pronta-para-automatização e o envio habilita.
  //
  // Escopado à LINHA desta solicitação: a lista tem outras, e um `.last()`
  // solto pegaria o botão de uma delas — que pode estar legitimamente
  // desabilitado por um motivo sem relação com este teste.
  const linha = page.locator('tr', { has: page.getByTestId(`solicitacao-paciente-${id}`) })
  const enviar = linha.locator('[data-testid^="solicitacao-item-enviar-unimed-"]')

  await expect(enviar.first()).toBeEnabled({ timeout: 30000 })
  await expect(enviar).toHaveCount(2)
})

test('convenio sem sessoes por guia nao mostra aviso de limite e deixa a quantidade vazia', async ({
  page,
}) => {
  const api = await apiAutenticada()
  // SC Saúde não tem `sessoes_por_guia` na semente — nulo é "não sabemos".
  const id = await cenario(api, 'SC Saúde')
  await api.dispose()

  await login(page)
  await abrirModal(page, id)
  await page.getByTestId('adicionar-sessoes-caminho-nova').click()

  await expect(page.getByTestId('adicionar-sessoes-quantidade')).toHaveValue('')
  // Aviso sem fonte não aparece, em vez de aparecer com um número inventado.
  await expect(page.getByTestId('aviso-limite')).toHaveCount(0)
})

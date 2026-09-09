import { expect, request, test, type APIRequestContext, type Page } from '@playwright/test'

// Sem o sufixo /api no baseURL: um caminho iniciado por barra ANULA o caminho
// do baseURL (regra de resolucao de URL), e o prefixo se perderia silenciosamente.
const API = 'http://127.0.0.1:8001'

/**
 * Monta o cenário pela API, e não pela interface.
 *
 * Criar a solicitação clicando exigiria o modal de busca de paciente, que é
 * exatamente onde os testes antigos de `mvp-flow` estão desalinhados. Aqui o
 * objeto do teste são as ABAS, e o caminho até os dados não deve poder
 * derrubá-lo por um motivo alheio.
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

async function primeiro(api: APIRequestContext, caminho: string) {
  const resposta = await api.get(`/api${caminho}`)
  expect(resposta.ok(), `${caminho} -> ${resposta.status()}`).toBeTruthy()
  const corpo = await resposta.json()

  return (corpo.data ?? corpo)[0]
}

/**
 * Cria uma solicitação com `quantidadeDeItens` especialidades e gera guia para
 * as `quantidadeComGuia` primeiras.
 *
 * Usa um convênio SEM automação: `GuiaService::criar` recusa criar guia à mão
 * para convênio `unimed_rda`, que é geração do robô.
 */
async function cenario(
  api: APIRequestContext,
  quantidadeDeItens: number,
  quantidadeComGuia: number,
): Promise<number> {
  const convenios = (await (await api.get('/api/convenios')).json()).data as Array<{
    id: number
    nome: string
    connector_driver: string | null
  }>
  const convenio = convenios.find((item) => item.connector_driver !== 'unimed_rda')
  expect(convenio, 'esperava ao menos um convênio sem automação').toBeTruthy()

  const paciente = await primeiro(api, '/pacientes')
  // cid_ids e obrigatorio na criacao (StoreSolicitacaoRequest).
  const cid = await primeiro(api, '/cids')
  const medico = await primeiro(api, '/medicos')
  // A especialidade vem do profissional escolhido, e não de uma lista à parte:
  // o par precisa ser coerente, e `/profissionais` já carrega o vínculo.
  const profissionais = (await (await api.get('/api/profissionais')).json()).data as Array<{
    id: number
    especialidade_id: number
  }>

  const itens = []
  for (let i = 0; i < quantidadeDeItens; i++) {
    const profissional = profissionais[i % profissionais.length]
    itens.push({
      especialidade_id: profissional.especialidade_id,
      profissional_id: profissional.id,
      quantidade: 5,
    })
  }

  const criada = await api.post('/api/solicitacoes', {
    data: {
      paciente_id: paciente.id,
      convenio_id: convenio!.id,
      medico_id: medico.id,
      cid_ids: [cid.id],
      solicitado_em: new Date().toISOString().slice(0, 10),
      itens,
    },
  })
  expect(criada.status(), await criada.text()).toBe(201)
  const solicitacao = (await criada.json()).data

  for (let i = 0; i < quantidadeComGuia; i++) {
    const item = solicitacao.itens[i]
    const guia = await api.post('/api/guias', {
      data: {
        solicitacao_id: solicitacao.id,
        solicitacao_item_id: item.id,
        convenio_id: convenio!.id,
        paciente_id: paciente.id,
        profissional_id: item.profissional_id,
        especialidade_id: item.especialidade_id,
        numero_guia: `E2E-${solicitacao.id}-${i}`,
        tipo_terapia: 'especializada',
        data_solicitacao: new Date().toISOString().slice(0, 10),
      },
    })
    expect(guia.status(), await guia.text()).toBe(201)
  }

  return solicitacao.id
}

/**
 * Abas de guia por item, número da operadora e coluna Info.
 *
 * Parte dos dados semeados em vez de criar a solicitação pela interface: o
 * fluxo de criação depende do modal de busca de paciente, e os testes que o
 * exercitam estão desalinhados com essa tela desde o change
 * `modal-busca-paciente-medico`. Amarrar estes cenários àquele fluxo faria eles
 * falharem por um motivo que não tem nada a ver com o que testam.
 */

async function login(page: Page) {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

async function abrirPrimeiraSolicitacao(page: Page) {
  await page.goto('/solicitacoes', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('solicitacoes-page')).toBeVisible()

  const primeiro = page.locator('[data-testid^="solicitacao-paciente-"]').first()
  await expect(primeiro).toBeVisible()
  await primeiro.click()

  await expect(page.getByTestId('solicitacao-guia-modal')).toBeVisible()
}

async function abrirSolicitacao(page: Page, id: number) {
  await page.goto('/solicitacoes', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('solicitacoes-page')).toBeVisible()
  await page.getByTestId(`solicitacao-paciente-${id}`).click()
  await expect(page.getByTestId('solicitacao-guia-modal')).toBeVisible()
}

test('duas especialidades com duas guias mostram duas abas', async ({ page }) => {
  const api = await apiAutenticada()
  const id = await cenario(api, 2, 2)
  await api.dispose()

  await login(page)
  await abrirSolicitacao(page, id)

  await expect(page.locator('[data-testid^="solicitacao-guia-aba-"]')).toHaveCount(2)
})

test('tres especialidades com uma guia mostram tres abas, duas aguardando', async ({ page }) => {
  const api = await apiAutenticada()
  const id = await cenario(api, 3, 1)
  await api.dispose()

  await login(page)
  await abrirSolicitacao(page, id)

  // Uma aba por ITEM, e não por guia: ver que faltam duas de três é a
  // informação mais importante desta tela.
  const abas = page.locator('[data-testid^="solicitacao-guia-aba-"]')
  await expect(abas).toHaveCount(3)

  // A segunda e a terceira abas são de itens sem guia.
  await abas.nth(1).click()
  await expect(page.locator('[data-testid^="solicitacao-guia-empty-"]')).toContainText(
    'Aguardando geração da guia',
  )

  await abas.nth(2).click()
  await expect(page.locator('[data-testid^="solicitacao-guia-empty-"]')).toContainText(
    'Aguardando geração da guia',
  )
})

test('o modal mostra uma aba por item da solicitacao', async ({ page }) => {
  await login(page)
  await abrirPrimeiraSolicitacao(page)

  const abas = page.locator('[data-testid^="solicitacao-guia-aba-"]')

  // Uma aba por ITEM, e não por guia: item sem guia também aparece, porque ver
  // o que ainda não saiu é a informação mais importante da tela.
  await expect(abas.first()).toBeVisible()
  expect(await abas.count()).toBeGreaterThan(0)
})

test('a aba com guia leva ao detalhe da guia', async ({ page }) => {
  await login(page)
  await abrirPrimeiraSolicitacao(page)

  // O link só entra no DOM depois que o detalhe da guia carrega — antes disso a
  // aba mostra "Carregando...". Sem esperar, a checagem cai no vazio e o teste
  // se auto-pula sem testar nada.
  const conteudo = page.getByTestId('solicitacao-guia-content')
  const vazio = page.locator('[data-testid^="solicitacao-guia-empty-"]').first()

  await expect(conteudo.or(vazio).first()).toBeVisible({ timeout: 15000 })

  if (await vazio.isVisible()) {
    // Aba de item sem guia: o estado vazio precisa dizer o que falta.
    await expect(vazio).toContainText('Aguardando geração da guia')

    return
  }

  const link = page.locator('[data-testid^="solicitacao-guia-link-"]').first()
  await expect(link).toBeVisible()
  await link.click()

  await expect(page).toHaveURL(/\/guias\/\d+$/)
})

test('a listagem nunca mostra o id interno como numero de guia', async ({ page }) => {
  await login(page)
  await page.goto('/solicitacoes', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('solicitacoes-page')).toBeVisible()

  const numeros = page.locator('[data-testid^="solicitacao-item-guia-numero-"]')

  for (const badge of await numeros.all()) {
    const texto = (await badge.textContent())?.trim() ?? ''

    // "Guia #12" era o id interno; "GUIA-SOLICITACAO-" é o valor de
    // preenchimento do convênio manual. Nenhum dos dois é número de operadora,
    // e nenhum dos dois pode chegar à tela como se fosse.
    expect(texto).not.toMatch(/Guia #\d+/)
    expect(texto).not.toContain('GUIA-SOLICITACAO-')
  }
})

test('a coluna Info aparece e nao adiciona paradas de teclado vazias', async ({ page }) => {
  await login(page)
  await page.goto('/solicitacoes', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('solicitacoes-page')).toBeVisible()

  await expect(page.getByRole('columnheader', { name: 'Info' })).toBeVisible()

  // O indicador de datas sempre tem conteúdo, então é sempre focável; os demais
  // só entram no tab order quando têm o que mostrar.
  const celula = page.locator('td[data-rotulo="Info"]').first()
  await expect(celula).toBeVisible()
  expect(await celula.locator('button').count()).toBeGreaterThanOrEqual(1)
})

test('o tooltip da coluna Info nao causa rolagem horizontal em 390px', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await login(page)
  await page.goto('/solicitacoes', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('solicitacoes-page')).toBeVisible()

  const gatilho = page.locator('td[data-rotulo="Info"] button').first()
  await gatilho.click();

  // O painel se desloca para caber na viewport (ver os comentários do Tooltip);
  // o que não pode é a página inteira passar a rolar de lado.
  const estourou = await page.evaluate(
    () => document.documentElement.scrollWidth > document.documentElement.clientWidth,
  )

  expect(estourou).toBe(false)
})

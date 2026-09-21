import { expect, request, test, type APIRequestContext, type Page } from '@playwright/test'

// Sem o sufixo /api: caminho iniciado por barra ANULA o caminho do baseURL.
const API = 'http://127.0.0.1:8001'

/**
 * As regras de agenda na grade de conferência — ver a spec
 * `sessoes-regras-de-agenda`.
 *
 * O que interessa aqui é o que só o navegador prova: a linha errada fica
 * marcada, o botão de registrar não deixa passar, e corrigir a hora na própria
 * grade libera o caminho sem recarregar nada. A regra em si já tem teste na
 * API (AvaliadorDeAgendaTest, SessoesRegrasDeAgendaApiTest).
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

/**
 * Um paciente só deste arquivo.
 *
 * As regras de agenda são POR PACIENTE, e a suíte compartilha um banco: usar
 * o paciente da semente faria as sessões daqui competirem pelos mesmos dias
 * com as de `antecipacoes` e `mvp-flow`, e a falha apareceria em spec alheio,
 * de forma intermitente. Paciente próprio isola isso pela raiz.
 */
async function pacienteExclusivo(
  api: APIRequestContext,
  convenioId: number,
  rotulo: string,
): Promise<number> {
  const sufixo = String(Date.now()).slice(-9)

  const criado = await api.post('/api/pacientes', {
    data: {
      nome: `Paciente Agenda E2E ${rotulo} ${sufixo}`,
      carteirinha: `0220${sufixo}0001`,
      convenio_id: convenioId,
      ativo: true,
    },
  })
  expect(criado.status(), await criado.text()).toBe(201)

  return (await criado.json()).data.id
}

/**
 * Cria uma guia aprovada de especialidade ABA, com cota folgada.
 *
 * ABA porque o limite diário dela é oito: assim o único motivo de conflito nos
 * cenários abaixo é o intervalo entre sessões, e não o limite — que tem
 * cobertura própria na API.
 *
 * `paciente` permite reaproveitar o mesmo paciente entre duas guias, que é o
 * que o cenário de choque do profissional precisa (dois pacientes, um
 * executante).
 */
async function guiaAbaAprovada(
  api: APIRequestContext,
  opcoes: { rotulo?: string; pacienteId?: number } = {},
): Promise<{ id: number; profissionalId: number; pacienteId: number }> {
  const profissionais = (await (await api.get('/api/profissionais')).json()).data as Array<{
    id: number
    nome: string
    especialidade_id: number
    especialidade?: { nome: string } | null
  }>

  const especialidades = (await (await api.get('/api/especialidades')).json()).data as Array<{
    id: number
    nome: string
  }>
  const aba = especialidades.find((item) => /\bABA\b/i.test(item.nome))
  expect(aba, 'esperava uma especialidade ABA na semente').toBeTruthy()

  const profissional = profissionais.find((item) => item.especialidade_id === aba!.id)
  expect(profissional, 'esperava um profissional da especialidade ABA').toBeTruthy()

  const convenios = (await (await api.get('/api/convenios')).json()).data as Array<{
    id: number
    connector_driver: string | null
  }>
  // `GuiaService::criar` recusa criar guia à mão para convênio do robô.
  const convenio = convenios.find((item) => item.connector_driver !== 'unimed_rda')
  expect(convenio, 'esperava ao menos um convênio sem automação').toBeTruthy()

  const pacienteId =
    opcoes.pacienteId ?? (await pacienteExclusivo(api, convenio!.id, opcoes.rotulo ?? 'a'))

  const criada = await api.post('/api/guias', {
    data: {
      convenio_id: convenio!.id,
      paciente_id: pacienteId,
      profissional_id: profissional!.id,
      especialidade_id: aba!.id,
      numero_guia: `AGENDA-E2E-${Date.now()}`,
      tipo_terapia: 'especializada',
      sessoes_solicitadas: 10,
      data_solicitacao: new Date().toISOString().slice(0, 10),
    },
  })
  expect(criada.status(), await criada.text()).toBe(201)
  const guia = (await criada.json()).data

  const aprovada = await api.patch(`/api/guias/${guia.id}/aprovar`, { data: {} })
  expect(aprovada.ok(), await aprovada.text()).toBeTruthy()

  return { id: guia.id, profissionalId: profissional!.id, pacienteId }
}

async function login(page: Page) {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

async function preencherLinha(page: Page, linha: number, data: string, hora: string) {
  const alvo = page.getByTestId(`lancamento-linha-${linha}`)
  await alvo.locator('input[type="date"]').fill(data)
  await alvo.locator('input[type="time"]').first().fill(hora)
}

async function abrirGradeDaGuia(page: Page, guiaId: number) {
  await page.goto('/lancamentos/novo', { waitUntil: 'domcontentloaded' })
  await page.getByTestId('lancamento-guia').click()
  await page.getByTestId('selecionar-guia-busca').fill(String(guiaId))
  await page.getByTestId('selecionar-guia-item').filter({ hasText: `#${guiaId} ` }).first().click()
}

test('grade marca as sessões coladas, trava o registro, e corrigir a hora libera', async ({ page }) => {
  const api = await apiAutenticada()
  const { id: guiaId } = await guiaAbaAprovada(api)
  await api.dispose()

  await login(page)
  await abrirGradeDaGuia(page, guiaId)

  // Trinta minutos entre os inícios: menos que os cinquenta exigidos.
  await preencherLinha(page, 1, '2026-09-21', '08:00')
  await preencherLinha(page, 2, '2026-09-21', '08:30')

  // A linha marcada é a MAIS TARDE do par — é ela que o operador vai mover.
  await expect(page.getByTestId('lancamento-linha-2-conflito')).toBeVisible({ timeout: 15000 })
  await expect(page.getByTestId('lancamento-linha-2-conflito')).toContainText('30 minutos')
  await expect(page.getByTestId('lancamento-linha-2')).toHaveAttribute('data-conflito', 'true')

  await expect(page.getByTestId('lancamento-conflito-resumo')).toBeVisible()
  await expect(page.getByTestId('lancamento-submit')).toBeDisabled()

  // Corrigir na própria grade reconfere e libera, sem recarregar a tela.
  await preencherLinha(page, 2, '2026-09-21', '09:00')

  await expect(page.getByTestId('lancamento-linha-2-conflito')).toHaveCount(0, { timeout: 15000 })
  await expect(page.getByTestId('lancamento-conflito-resumo')).toHaveCount(0)
  await expect(page.getByTestId('lancamento-submit')).toBeEnabled()

  const confirmacao = page.waitForResponse(
    (resposta) =>
      resposta.request().method() === 'POST' &&
      resposta.url().includes(`/guias/${guiaId}/lancamentos/importar-transcricao`),
  )
  await page.getByTestId('lancamento-submit').click()
  expect((await confirmacao).status()).toBe(201)
})

test('choque de agenda do profissional avisa mas deixa registrar', async ({ page }) => {
  const api = await apiAutenticada()

  // Duas guias do MESMO executante, para PACIENTES DIFERENTES: é essa
  // combinação que o aviso de choque acusa. O helper já dá a cada guia um
  // paciente próprio, que é exatamente o que se quer aqui.
  const primeira = await guiaAbaAprovada(api, { rotulo: 'choque-1' })
  const segunda = await guiaAbaAprovada(api, { rotulo: 'choque-2' })

  expect(segunda.profissionalId).toBe(primeira.profissionalId)
  expect(segunda.pacienteId).not.toBe(primeira.pacienteId)

  // A primeira guia já tem a sessão que vai chocar.
  const sessaoExistente = await api.post(`/api/guias/${primeira.id}/lancamentos`, {
    data: {
      profissional_id: primeira.profissionalId,
      data_sessao: '2026-09-23',
      hora_inicio: '08:00',
    },
  })
  expect(sessaoExistente.status(), await sessaoExistente.text()).toBe(201)
  await api.dispose()

  await login(page)
  await abrirGradeDaGuia(page, segunda.id)

  await preencherLinha(page, 1, '2026-09-23', '08:00')

  await expect(page.getByTestId('lancamento-linha-1-aviso')).toBeVisible({ timeout: 15000 })
  await expect(page.getByTestId('lancamento-linha-1-aviso')).toContainText('executante já tem sessão')

  // Aviso não é bloqueio.
  await expect(page.getByTestId('lancamento-linha-1')).not.toHaveAttribute('data-conflito', 'true')
  await expect(page.getByTestId('lancamento-submit')).toBeEnabled()
})

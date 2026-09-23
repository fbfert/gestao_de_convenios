import { expect, request, test, type APIRequestContext, type Page } from '@playwright/test'

// Sem o sufixo /api: caminho iniciado por barra ANULA o caminho do baseURL.
const API = 'http://127.0.0.1:8001'

/**
 * Editar paciente aberto pelo endereço, e não pelo botão da listagem.
 *
 * A edição procurava o paciente só na página carregada da listagem. O botão
 * "Editar cadastro" da pasta abre `/pacientes/{id}/editar` sem página nem
 * filtro — e, numa clínica com mais pacientes do que cabem na primeira página,
 * o formulário abria todo em branco.
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

async function login(page: Page) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

test('editar pela pasta carrega os dados de paciente fora da primeira pagina', async ({ page }) => {
  const api = await apiAutenticada()
  const convenios = (await (await api.get('/api/convenios')).json()).data as Array<{
    id: number
    nome: string
    carteirinha_blocos: string | null
  }>
  // Carteirinha em texto livre: o campo é um só, e dá para comparar o valor.
  const convenio = convenios.find((item) => !item.carteirinha_blocos)
  expect(convenio, 'esperava um convênio sem formato de carteirinha').toBeTruthy()

  const sufixo = String(Date.now()).slice(-8)
  const criar = async (nome: string, carteirinha: string) => {
    const resposta = await api.post('/api/pacientes', {
      data: { nome, carteirinha, convenio_id: convenio!.id, ativo: true },
    })
    expect(resposta.status(), await resposta.text()).toBe(201)
    return (await resposta.json()).data as { id: number }
  }

  // Mais pacientes do que cabem numa página, todos antes do alvo na ordem por
  // nome — o alvo fica, com certeza, fora da página 1.
  for (let i = 0; i < 20; i += 1) {
    await criar(`AAA Edicao E2E ${sufixo} ${String(i).padStart(2, '0')}`, `ED${sufixo}${i}`)
  }
  const alvo = await criar(`ZZZ Edicao E2E ${sufixo}`, `ALVO${sufixo}`)
  await api.dispose()

  await login(page)
  await page.goto(`/pacientes/${alvo.id}`, { waitUntil: 'domcontentloaded' })
  await page.getByTestId('paciente-pasta-editar').click()

  await expect(page).toHaveURL(new RegExp(`/pacientes/${alvo.id}/editar$`))
  await expect(page.getByTestId('paciente-nome')).toHaveValue(`ZZZ Edicao E2E ${sufixo}`)
  await expect(page.getByTestId('paciente-carteirinha')).toHaveValue(`ALVO${sufixo}`)
  await expect(page.getByTestId('paciente-convenio')).toContainText(convenio!.nome)

  // Recarregar a rota direto também hidrata.
  await page.reload({ waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('paciente-nome')).toHaveValue(`ZZZ Edicao E2E ${sufixo}`)
})

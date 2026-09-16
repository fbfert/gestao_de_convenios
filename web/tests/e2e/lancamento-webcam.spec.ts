import { expect, request, test, type APIRequestContext, type Page } from '@playwright/test'

// Sem o sufixo /api no baseURL: um caminho iniciado por barra ANULA o caminho
// do baseURL (regra de resolução de URL), e o prefixo se perderia em silêncio.
const API = 'http://127.0.0.1:8001'

/**
 * A webcam da leitura do registro de sessões, em `/lancamentos/novo`.
 *
 * O Chromium entra com câmera FALSA: `--use-fake-device-for-media-stream` dá um
 * vídeo sintético no lugar do hardware, e `--use-fake-ui-for-media-stream`
 * concede a permissão sem o diálogo do navegador. Sem os dois, `getUserMedia`
 * pendura ou rejeita na máquina de CI e o teste não tem o que exercitar.
 *
 * O que estes testes provam é o CAMINHO: o botão aparece, o vídeo abre, a foto
 * congela para conferência, "Tirar outra" volta ao vivo, e a foto confirmada
 * entra na mesma leitura do arquivo escolhido. O que eles NÃO provam é a
 * qualidade da extração a partir de uma foto real — o vídeo sintético é um
 * padrão colorido, não uma folha de sessões, e a leitura de verdade depende da
 * chave OpenAI de produção.
 */
test.use({
  launchOptions: {
    args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'],
  },
})

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

function hoje(): string {
  return new Date().toISOString().slice(0, 10)
}

/** Guia aprovada com saldo, que é o que a tela de lançamento aceita. */
async function guiaParaLancamento(api: APIRequestContext): Promise<number> {
  const convenios = (await (await api.get('/api/convenios')).json()).data as Array<{
    id: number
    connector_driver: string | null
  }>
  // `GuiaService::criar` recusa criar guia à mão para convênio `unimed_rda`.
  const convenio = convenios.find((item) => item.connector_driver !== 'unimed_rda')
  expect(convenio, 'esperava ao menos um convênio sem automação').toBeTruthy()

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
      solicitado_em: hoje(),
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
  const solicitacao = (await criada.json()).data
  const item = solicitacao.itens[0]

  const guiaCriada = await api.post('/api/guias', {
    data: {
      solicitacao_id: solicitacao.id,
      solicitacao_item_id: item.id,
      convenio_id: convenio!.id,
      paciente_id: paciente.id,
      profissional_id: item.profissional_id,
      especialidade_id: item.especialidade_id,
      numero_guia: `E2E-CAM-${Date.now()}`,
      tipo_terapia: 'especializada',
      data_solicitacao: hoje(),
    },
  })
  expect(guiaCriada.status(), await guiaCriada.text()).toBe(201)
  const guia = (await guiaCriada.json()).data

  // Finalizar dá senha e validade, e é o status com saldo que a tela aceita.
  const finalizada = await api.patch(`/api/guias/${guia.id}/finalizar`, {
    data: { senha: `E2E${Date.now()}`.slice(0, 20), validade_senha: '2027-12-31' },
  })
  expect(finalizada.status(), await finalizada.text()).toBe(200)

  return guia.id
}

async function login(page: Page) {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

/**
 * Abre a tela já com a guia escolhida e o executante selecionado.
 *
 * O executante não é um `<select>` nativo — é o `Select` do projeto, um
 * listbox do Headless UI —, então o `selectOption` do Playwright não serve:
 * abre o botão e clica na opção, como o `mvp-flow` já faz.
 */
async function abrirPronta(page: Page, guiaId: number) {
  await page.goto(`/lancamentos/novo?guia_id=${guiaId}`, { waitUntil: 'domcontentloaded' })

  const executante = page.getByTestId('lancamento-profissional')
  await expect(executante).toBeEnabled({ timeout: 30000 })
  await executante.click()
  // A primeira opção real; a de índice 0 é o "Selecione" vazio.
  await page.getByRole('option').nth(1).click()
  await expect(page.getByTestId('lancamento-anexo-botao')).toBeEnabled()
}

test('a webcam so libera com guia e executante escolhidos', async ({ page }) => {
  const api = await apiAutenticada()
  const guiaId = await guiaParaLancamento(api)
  await api.dispose()

  await login(page)
  await page.goto('/lancamentos/novo', { waitUntil: 'domcontentloaded' })

  // Sem guia não há para onde mandar a leitura — mesma condição do botão de
  // arquivo, e não uma regra própria da webcam.
  const botao = page.getByTestId('lancamento-webcam-botao')
  await expect(botao).toBeVisible()
  await expect(botao).toBeDisabled()

  await abrirPronta(page, guiaId)
  await expect(page.getByTestId('lancamento-webcam-botao')).toBeEnabled()
})

test('capturar congela a foto para conferencia, e tirar outra volta ao vivo', async ({ page }) => {
  const api = await apiAutenticada()
  const guiaId = await guiaParaLancamento(api)
  await api.dispose()

  await login(page)
  await abrirPronta(page, guiaId)

  await page.getByTestId('lancamento-webcam-botao').click()

  // O vídeo ao vivo aparece e a captura habilita quando o stream chega.
  await expect(page.getByTestId('lancamento-webcam-preview')).toBeVisible()
  await expect(page.getByTestId('lancamento-webcam-capturar')).toBeEnabled()

  // Capturar NÃO envia: congela para conferência. É o ponto da feature — uma
  // foto ruim custaria a chamada de IA e a espera antes de alguém ver que não
  // deu.
  await page.getByTestId('lancamento-webcam-capturar').click()
  await expect(page.getByTestId('lancamento-webcam-previa')).toBeVisible()
  await expect(page.getByTestId('lancamento-webcam-preview')).toHaveCount(0)
  await expect(page.getByTestId('lancamento-lendo')).toHaveCount(0)

  // Descartar volta ao vivo, sem pedir permissão de novo.
  await page.getByTestId('lancamento-webcam-repetir').click()
  await expect(page.getByTestId('lancamento-webcam-preview')).toBeVisible()
  await expect(page.getByTestId('lancamento-webcam-previa')).toHaveCount(0)

  // E volta CAPTURÁVEL. O `<video>` que retorna é um elemento novo: sem
  // reatribuir o `srcObject` nele, ficaria um quadro preto — visível e inútil,
  // que um `toBeVisible()` sozinho não distinguiria. A captura só habilita
  // depois do `loadedmetadata`, então isto é o que prova que há imagem.
  await expect(page.getByTestId('lancamento-webcam-capturar')).toBeEnabled()
  await page.getByTestId('lancamento-webcam-capturar').click()
  await expect(page.getByTestId('lancamento-webcam-previa')).toBeVisible()
  await page.getByTestId('lancamento-webcam-repetir').click()

  // Cancelar fecha a captura e devolve a tela ao estado anterior.
  await page.getByTestId('lancamento-webcam-cancelar').click()
  await expect(page.getByTestId('lancamento-webcam-preview')).toHaveCount(0)
  await expect(page.getByTestId('lancamento-anexo-botao')).toBeEnabled()
})

test('a foto confirmada entra na mesma leitura do arquivo escolhido', async ({ page }) => {
  const api = await apiAutenticada()
  const guiaId = await guiaParaLancamento(api)
  await api.dispose()

  await login(page)
  await abrirPronta(page, guiaId)

  // A leitura é interceptada: o objeto aqui é provar que a foto confirmada cai
  // em `POST /guias/{id}/lancamentos/ler-registro` com um JPEG anexado — a
  // extração de verdade depende da chave OpenAI e de uma folha real.
  let chamadas = 0
  let corpoDaChamada = ''
  await page.route(`**/api/guias/${guiaId}/lancamentos/ler-registro`, async (route) => {
    chamadas += 1
    corpoDaChamada = route.request().postData() ?? ''

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          confirmacao_pendente: true,
          cabecalho: { numero_cartao: '0155 090000 551.330-8' },
          sessoes: [
            {
              data_sessao: '2026-04-08',
              hora_inicio: '14:50',
              hora_fim: '15:40',
              profissional_nome: 'Bruno Marinho',
              procedimento: 'Aplicação de testes',
            },
          ],
          registros: [],
        },
      }),
    })
  })

  await page.getByTestId('lancamento-webcam-botao').click()
  await expect(page.getByTestId('lancamento-webcam-capturar')).toBeEnabled()
  await page.getByTestId('lancamento-webcam-capturar').click()
  await expect(page.getByTestId('lancamento-webcam-previa')).toBeVisible()

  // Só agora a leitura dispara.
  expect(chamadas).toBe(0)
  await page.getByTestId('lancamento-webcam-usar').click()

  await expect.poll(() => chamadas).toBe(1)

  // A câmera fecha ao confirmar, e o resultado cai na MESMA grade de
  // conferência que a leitura por arquivo preenche.
  await expect(page.getByTestId('lancamento-webcam-preview')).toHaveCount(0)
  await expect(page.getByTestId('lancamento-webcam-previa')).toHaveCount(0)

  // O anexo vai com nome e tipo que `LerRegistroSessoesRequest` aceita
  // (`mimes:pdf,jpg,jpeg,png`), e não como um blob sem extensão.
  expect(corpoDaChamada).toContain('registro-sessoes.jpg')
  expect(corpoDaChamada).toContain('image/jpeg')
})

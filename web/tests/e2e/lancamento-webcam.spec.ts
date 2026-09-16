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
async function guiaParaLancamento(
  api: APIRequestContext,
): Promise<{ id: number; numero: string }> {
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

  // Número único: a resolução da guia pelo número lido busca em
  // `/guias?busca=…`, e um número repetido entre specs traria mais de uma.
  const numeroGuia = `E2E-CAM-${Date.now()}`
  const guiaCriada = await api.post('/api/guias', {
    data: {
      solicitacao_id: solicitacao.id,
      solicitacao_item_id: item.id,
      convenio_id: convenio!.id,
      paciente_id: paciente.id,
      profissional_id: item.profissional_id,
      especialidade_id: item.especialidade_id,
      numero_guia: numeroGuia,
      tipo_terapia: 'especializada',
      data_solicitacao: hoje(),
      // Sem quantidade a guia NUNCA é "disponível para lançamento": o filtro é
      // COALESCE(autorizadas, solicitadas, 0) > lançadas, e nulo vira zero.
      // É por esse filtro que a leitura resolve o número lido.
      sessoes_solicitadas: 10,
      sessoes_autorizadas: 10,
    },
  })
  expect(guiaCriada.status(), await guiaCriada.text()).toBe(201)
  const guia = (await guiaCriada.json()).data

  // Finalizar dá senha e validade, e é o status com saldo que a tela aceita.
  const finalizada = await api.patch(`/api/guias/${guia.id}/finalizar`, {
    data: { senha: `E2E${Date.now()}`.slice(0, 20), validade_senha: '2027-12-31' },
  })
  expect(finalizada.status(), await finalizada.text()).toBe(200)

  return { id: guia.id, numero: numeroGuia }
}

async function login(page: Page) {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

test('a leitura libera antes de escolher guia e executante', async ({ page }) => {
  await login(page)
  await page.goto('/lancamentos/novo', { waitUntil: 'domcontentloaded' })

  // O ponto do fluxo: é a folha que traz o número da guia, então exigir a
  // escolha antes era pedir que alguém procurasse à mão o que a IA leria em
  // seguida. Os dois caminhos de leitura liberam com a tela recém-aberta.
  await expect(page.getByTestId('lancamento-webcam-botao')).toBeEnabled()
  await expect(page.getByTestId('lancamento-anexo-botao')).toBeEnabled()

  // O que continua exigindo os dois é o texto colado, que posta numa rota com
  // guia no caminho e executante no corpo.
  await expect(page.getByTestId('lancamento-analisar-texto')).toBeDisabled()
})

test('capturar congela a foto para conferencia, e tirar outra volta ao vivo', async ({ page }) => {
  await login(page)
  // Sem guia escolhida de propósito: capturar não depende dela.
  await page.goto('/lancamentos/novo', { waitUntil: 'domcontentloaded' })

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


/**
 * Responde a leitura sem chamar a IA, com o cabeçalho pedido.
 *
 * Só a leitura é interceptada: a busca que resolve a guia pelo número vai à
 * API de verdade, que é o que prova a integração entre as duas.
 */
async function interceptarLeitura(
  page: Page,
  cabecalho: Record<string, string | null>,
): Promise<{ chamadas: () => number; corpo: () => string }> {
  let chamadas = 0
  let corpo = ''

  await page.route('**/api/lancamentos/ler-registro', async (route) => {
    chamadas += 1
    corpo = route.request().postData() ?? ''

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: {
          confirmacao_pendente: true,
          cabecalho: {
            guia_numero: null,
            clinica: null,
            paciente: null,
            numero_cartao: null,
            profissional_executante: null,
            terapia_aplicada: null,
            ...cabecalho,
          },
          sessoes: [
            {
              data_sessao: '2026-04-08',
              hora_inicio: '14:50',
              hora_fim: '15:40',
              acompanhante: 'Bruno Marinho',
              resumo_atividades: 'Aplicação de testes',
            },
          ],
          registros: [],
        },
      }),
    })
  })

  return { chamadas: () => chamadas, corpo: () => corpo }
}

/** Abre a webcam, captura e confirma a foto. */
async function capturarEConfirmar(page: Page) {
  await page.getByTestId('lancamento-webcam-botao').click()
  await expect(page.getByTestId('lancamento-webcam-capturar')).toBeEnabled()
  await page.getByTestId('lancamento-webcam-capturar').click()
  await expect(page.getByTestId('lancamento-webcam-previa')).toBeVisible()
  await page.getByTestId('lancamento-webcam-usar').click()
}

test('o numero de guia lido escolhe a guia, sem ninguem ter escolhido antes', async ({ page }) => {
  const api = await apiAutenticada()
  const guia = await guiaParaLancamento(api)
  await api.dispose()

  await login(page)
  await page.goto('/lancamentos/novo', { waitUntil: 'domcontentloaded' })

  const leitura = await interceptarLeitura(page, {
    guia_numero: guia.numero,
    numero_cartao: '0155 090000 551.330-8',
    profissional_executante: 'Mariana',
  })

  // Nenhuma guia escolhida ao capturar — é a folha que vai dizer qual é.
  await expect(page.getByTestId('lancamento-guia')).toContainText('Selecione uma guia')

  // A leitura só dispara ao confirmar a foto.
  expect(leitura.chamadas()).toBe(0)
  await capturarEConfirmar(page)
  await expect.poll(leitura.chamadas).toBe(1)

  // O número lido resolveu para uma guia só, e ela foi escolhida.
  await expect(page.getByTestId('lancamento-guia')).toContainText(guia.numero)
  await expect(page.getByTestId('lancamento-guia-da-leitura')).toContainText(guia.numero)

  // O executante lido aparece, mas NÃO preenche o campo: a folha é manuscrita
  // e executante errado só aparece como glosa na conciliação.
  await expect(page.getByTestId('lancamento-executante-lido')).toContainText('Mariana')

  // E o anexo continua indo com nome e tipo que a API aceita.
  expect(leitura.corpo()).toContain('registro-sessoes.jpg')
  expect(leitura.corpo()).toContain('image/jpeg')
})

test('numero de guia que nao resolve abre a busca ja preenchida', async ({ page }) => {
  await login(page)
  await page.goto('/lancamentos/novo', { waitUntil: 'domcontentloaded' })

  // Número que não existe: nenhuma guia bate.
  const inexistente = 'GUIA-QUE-NAO-EXISTE-9999'
  await interceptarLeitura(page, { guia_numero: inexistente })

  await capturarEConfirmar(page)

  // Abre a busca com o número já digitado, em vez de deixar o operador
  // redigitar o que a IA acabou de ler.
  await expect(page.getByTestId('selecionar-guia-modal')).toBeVisible()
  await expect(page.getByTestId('selecionar-guia-busca')).toHaveValue(inexistente)

  // E nenhuma guia foi escolhida por conta própria.
  await page.getByTestId('selecionar-guia-modal').getByLabel('Fechar').click()
  await expect(page.getByTestId('lancamento-guia')).toContainText('Selecione uma guia')
  await expect(page.getByTestId('lancamento-guia-da-leitura')).toHaveCount(0)
})

test('registro sem numero de guia legivel nao abre nada nem escolhe por outro dado', async ({
  page,
}) => {
  const api = await apiAutenticada()
  // Existe uma guia disponível, mas o número não foi lido — cair para o nome
  // do paciente casaria com várias guias dele e escolheria a errada calada.
  await guiaParaLancamento(api)
  await api.dispose()

  await login(page)
  await page.goto('/lancamentos/novo', { waitUntil: 'domcontentloaded' })

  await interceptarLeitura(page, { guia_numero: null, paciente: 'Ana Paula Ribeiro' })

  await capturarEConfirmar(page)

  // A grade foi preenchida pela leitura...
  await expect(
    page.getByTestId('lancamento-linha-1').locator('input[type="date"]'),
  ).toHaveValue('2026-04-08')

  // ...e a escolha da guia continua com o operador.
  await expect(page.getByTestId('selecionar-guia-modal')).toHaveCount(0)
  await expect(page.getByTestId('lancamento-guia')).toContainText('Selecione uma guia')
})

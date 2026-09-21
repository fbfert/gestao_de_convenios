import { expect, request, test, type APIRequestContext, type Page } from '@playwright/test'

// Sem o sufixo /api no baseURL: um caminho iniciado por barra ANULA o caminho
// do baseURL (regra de resolução de URL), e o prefixo se perderia em silêncio.
const API = 'http://127.0.0.1:8001'

/**
 * A tela /antecipacoes: confirmar o Ignorar, desfazer a dispensa, buscar no
 * histórico com a busca sobrevivendo a abrir um item, e os dois pontos de
 * contexto (tooltip do histórico, modal de origem dos elegíveis).
 *
 * O cenário nasce pela API, e não clicando: chegar a uma guia ELEGÍVEL pela
 * interface custaria criar solicitação, gerar guia e finalizá-la com senha —
 * três telas que não são o objeto destes testes e que derrubariam a suíte por
 * motivo alheio.
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

function hojeMais(dias: number): string {
  const data = new Date()
  data.setDate(data.getDate() + dias)

  return data.toISOString().slice(0, 10)
}

/**
 * Carteirinha que passa na validação do convênio.
 *
 * Convênio que declara `carteirinha_blocos` exige a contagem exata de dígitos
 * (ver `ValidaCarteirinhaPorConvenio`); o que não declara aceita texto livre.
 * Usa a hora para não colidir com outro paciente da mesma suíte.
 */
function carteirinhaPara(blocos: number[] | null): string {
  const unico = String(Date.now()).slice(-12)

  if (!blocos || blocos.length === 0) {
    return `E2EANT${unico}`
  }

  const digitos = blocos.reduce((total, bloco) => total + bloco, 0)

  return unico.padStart(digitos, '0').slice(-digitos)
}

type Elegivel = {
  solicitacaoId: number
  pacienteNome: string
  numeroGuia: string
}

/**
 * Solicitação com uma guia FINALIZADA cuja antecipação já está devida.
 *
 * A data-alvo padrão é `validade_senha` menos os `antecipacao_dias` globais
 * (20 na semente). Uma validade daqui a 10 dias põe a data-alvo 10 dias no
 * PASSADO, que é o que torna a guia elegível hoje.
 *
 * Cada chamada cria um paciente próprio, com nome único: a busca do histórico
 * é por nome, e um paciente compartilhado com outra spec tornaria a asserção
 * de "esta linha e não a outra" dependente da ordem de execução.
 */
async function cenarioElegivel(api: APIRequestContext, etiqueta: string): Promise<Elegivel> {
  const convenios = (await (await api.get('/api/convenios')).json()).data as Array<{
    id: number
    nome: string
    connector_driver: string | null
    carteirinha_blocos: number[] | null
  }>
  // `GuiaService::criar` recusa criar guia à mão para convênio `unimed_rda` —
  // lá quem gera é o robô.
  const convenio = convenios.find((item) => item.connector_driver !== 'unimed_rda')
  expect(convenio, 'esperava ao menos um convênio sem automação').toBeTruthy()

  const cid = await primeiro(api, '/cids')
  const medico = await primeiro(api, '/medicos')
  const profissional = await primeiro(api, '/profissionais')

  const sufixo = `${etiqueta}-${Date.now()}`
  const pacienteNome = `Antecipacao E2E ${sufixo}`
  const pacienteCriado = await api.post('/api/pacientes', {
    data: {
      nome: pacienteNome,
      convenio_id: convenio!.id,
      carteirinha: carteirinhaPara(convenio!.carteirinha_blocos),
      ativo: true,
    },
  })
  expect(pacienteCriado.status(), await pacienteCriado.text()).toBe(201)
  const paciente = (await pacienteCriado.json()).data

  const criada = await api.post('/api/solicitacoes', {
    data: {
      paciente_id: paciente.id,
      convenio_id: convenio!.id,
      medico_id: medico.id,
      cid_ids: [cid.id],
      solicitado_em: hojeMais(-30),
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

  const numeroGuia = `E2E-ANT-${sufixo}`
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
      data_solicitacao: hojeMais(-30),
      // Sem isso sessoesDisponiveis() fica 0 e o lançamento de sessão abaixo
      // (pré-requisito novo de Finalizar) é rejeitado por falta de cota.
      sessoes_autorizadas: 10,
    },
  })
  expect(guiaCriada.status(), await guiaCriada.text()).toBe(201)
  const guia = (await guiaCriada.json()).data

  // Guia sem automação Unimed sai de under_review pelo Aprovar manual — sem
  // isso ela nunca aceita lançamento (Guia::aceitaLancamento()), e Finalizar
  // passou a exigir ao menos 1 sessão registrada antes de aceitar.
  const aprovada = await api.patch(`/api/guias/${guia.id}/aprovar`)
  expect(aprovada.status(), await aprovada.text()).toBe(200)

  const sessaoRegistrada = await api.post(`/api/guias/${guia.id}/lancamentos`, {
    data: { profissional_id: item.profissional_id, data_sessao: hojeMais(-1) },
  })
  expect(sessaoRegistrada.status(), await sessaoRegistrada.text()).toBe(201)

  // Finalizar é o caminho de status que a API expõe (status não se edita pelo
  // PATCH normal), e é ele que grava a `validade_senha` de onde sai a data-alvo.
  const finalizada = await api.patch(`/api/guias/${guia.id}/finalizar`, {
    data: { senha: `E2E${Date.now()}`.slice(0, 20), validade_senha: hojeMais(10) },
  })
  expect(finalizada.status(), await finalizada.text()).toBe(200)

  return { solicitacaoId: solicitacao.id, pacienteNome, numeroGuia }
}

async function login(page: Page) {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

async function abrirAntecipacoes(page: Page) {
  await page.goto('/antecipacoes', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('antecipacoes-page')).toBeVisible()
}

test('ignorar pede confirmacao, registra o motivo, e o desfazer devolve a guia a fila', async ({
  page,
}) => {
  const api = await apiAutenticada()
  const { solicitacaoId, pacienteNome } = await cenarioElegivel(api, 'ignorar')
  await api.dispose()

  await login(page)
  await abrirAntecipacoes(page)

  const linhaElegivel = page.getByTestId(`antecipacao-elegivel-ignorar-${solicitacaoId}`)
  await expect(linhaElegivel).toBeVisible()

  // 1. Ignorar NÃO age no primeiro clique: abre a confirmação.
  await linhaElegivel.click()
  await expect(page.getByTestId('ignorar-antecipacao-modal')).toBeVisible()

  // 2. Cancelar não registra nada — a entrada continua na fila.
  await page.getByTestId('ignorar-antecipacao-modal').getByRole('button', { name: 'Cancelar' }).click()
  await expect(page.getByTestId('ignorar-antecipacao-modal')).toBeHidden()
  await expect(linhaElegivel).toBeVisible()

  // 3. Confirmar com motivo: sai da fila e entra no histórico.
  await linhaElegivel.click()
  await page.getByTestId('ignorar-antecipacao-motivo').fill('Paciente em alta no e2e.')
  await page.getByTestId('ignorar-antecipacao-confirmar').click()
  await expect(page.getByTestId('ignorar-antecipacao-modal')).toBeHidden()
  await expect(linhaElegivel).toHaveCount(0)

  const historico = page.locator('[data-testid^="antecipacao-historico-item-"]', {
    hasText: pacienteNome,
  })
  await expect(historico).toHaveCount(1)
  await expect(historico).toContainText('Paciente em alta no e2e.')

  // 4. O desfazer também confirma antes, e cancelar não desfaz.
  const desfazer = historico.locator('[data-testid^="antecipacao-desfazer-"]')
  await desfazer.click()
  await expect(page.getByTestId('confirm-dialog')).toBeVisible()
  await page.getByTestId('confirm-dialog-cancelar').click()
  await expect(historico).toHaveCount(1)

  // 5. Confirmado, o registro sai do histórico e a solicitação volta à fila —
  //    que é o desfazer inteiro (a fila exclui pela EXISTÊNCIA do registro).
  await desfazer.click()
  await page.getByTestId('confirm-dialog-confirmar').click()
  await expect(historico).toHaveCount(0)
  await expect(page.getByTestId(`antecipacao-elegivel-ignorar-${solicitacaoId}`)).toBeVisible()
})

test('busca do historico filtra e sobrevive a abrir um item e voltar', async ({ page }) => {
  const api = await apiAutenticada()
  const alvo = await cenarioElegivel(api, 'busca-alvo')
  const ruido = await cenarioElegivel(api, 'busca-ruido')

  // Duas dispensas pela API: o objeto deste teste é a BUSCA, não o modal de
  // ignorar, que o teste acima já cobre.
  for (const solicitacaoId of [alvo.solicitacaoId, ruido.solicitacaoId]) {
    const ignorada = await api.post('/api/antecipacoes/ignorar', {
      data: { solicitacao_origem_id: solicitacaoId },
    })
    expect(ignorada.status(), await ignorada.text()).toBe(201)
  }
  await api.dispose()

  await login(page)
  await abrirAntecipacoes(page)

  const linhaAlvo = page.locator('[data-testid^="antecipacao-historico-item-"]', {
    hasText: alvo.pacienteNome,
  })
  const linhaRuido = page.locator('[data-testid^="antecipacao-historico-item-"]', {
    hasText: ruido.pacienteNome,
  })

  await expect(linhaAlvo).toHaveCount(1)
  await expect(linhaRuido).toHaveCount(1)

  // Busca por paciente separa as duas.
  await page.getByTestId('antecipacoes-filtro-paciente').fill(alvo.pacienteNome)
  await page.getByRole('button', { name: 'Aplicar' }).click()
  await expect(linhaAlvo).toHaveCount(1)
  await expect(linhaRuido).toHaveCount(0)

  // O critério vive na URL — é o que o faz sobreviver ao Voltar.
  await expect(page).toHaveURL(/paciente_nome=/)

  // Busca por número de guia acha um registro IGNORADO, que não gerou guia
  // nenhuma: ela casa com as guias da solicitação de origem.
  await page.getByTestId('antecipacoes-filtro-paciente').fill('')
  await page.getByTestId('antecipacoes-filtro-guia').fill(alvo.numeroGuia)
  await page.getByRole('button', { name: 'Aplicar' }).click()
  await expect(linhaAlvo).toHaveCount(1)
  await expect(linhaRuido).toHaveCount(0)

  // Período que termina ontem não pode conter uma dispensa de hoje.
  await page.getByTestId('antecipacoes-filtro-data-ate').fill(hojeMais(-1))
  await page.getByRole('button', { name: 'Aplicar' }).click()
  await expect(page.locator('[data-testid^="antecipacao-historico-item-"]')).toHaveCount(0)

  // Limpar devolve as duas.
  await page.getByTestId('antecipacoes-filtro-limpar').click()
  await expect(linhaAlvo).toHaveCount(1)
  await expect(linhaRuido).toHaveCount(1)

  // A persistência: filtra, abre a guia da origem pelo modal de elegíveis e
  // volta. Antes desta change a volta trazia a página 1 sem filtro nenhum.
  await page.getByTestId('antecipacoes-filtro-paciente').fill(alvo.pacienteNome)
  await page.getByRole('button', { name: 'Aplicar' }).click()
  const urlFiltrada = page.url()
  expect(urlFiltrada).toContain('paciente_nome=')

  await page.goto(`/guias?paciente_nome=${encodeURIComponent(alvo.pacienteNome)}`)
  await page.goBack()
  await expect(page.getByTestId('antecipacoes-page')).toBeVisible()
  await expect(page).toHaveURL(urlFiltrada)
  await expect(page.getByTestId('antecipacoes-filtro-paciente')).toHaveValue(alvo.pacienteNome)
  await expect(linhaAlvo).toHaveCount(1)
  await expect(linhaRuido).toHaveCount(0)
})

test('elegivel abre a origem, e o historico mostra o detalhe da acao no tooltip', async ({
  page,
}) => {
  const api = await apiAutenticada()
  const { solicitacaoId, pacienteNome, numeroGuia } = await cenarioElegivel(api, 'contexto')
  await api.dispose()

  await login(page)
  await abrirAntecipacoes(page)

  // Clicar em "Paciente · Convênio" nos elegíveis abre a origem — e só
  // consulta: fechar não gera nem dispensa nada.
  await page.getByTestId(`antecipacao-elegivel-origem-${solicitacaoId}`).click()
  const origem = page.getByTestId('origem-elegivel-modal')
  await expect(origem).toBeVisible()
  await expect(origem).toContainText(pacienteNome)
  await expect(origem).toContainText(`#${solicitacaoId}`)
  await expect(origem).toContainText(numeroGuia)
  await expect(origem.getByTestId('origem-elegivel-guia')).toHaveCount(1)
  await expect(origem.getByTestId('origem-elegivel-item')).toHaveCount(1)

  // Pelo testid, e não por nome: o X do cabeçalho tem o mesmo nome acessível
  // ("Fechar") que o botão do rodapé, e o papel casaria com os dois.
  await page.getByTestId('origem-elegivel-fechar').click()
  await expect(origem).toBeHidden()
  await expect(page.getByTestId(`antecipacao-elegivel-ignorar-${solicitacaoId}`)).toBeVisible()

  // Dispensa, para ter uma linha de histórico com tooltip.
  await page.getByTestId(`antecipacao-elegivel-ignorar-${solicitacaoId}`).click()
  await page.getByTestId('ignorar-antecipacao-motivo').fill('Motivo no tooltip.')
  await page.getByTestId('ignorar-antecipacao-confirmar').click()
  await expect(page.getByTestId('ignorar-antecipacao-modal')).toBeHidden()

  const historico = page.locator('[data-testid^="antecipacao-historico-item-"]', {
    hasText: pacienteNome,
  })
  await expect(historico).toHaveCount(1)

  // Hover, e não clique: no mouse o painel já abre no `pointerenter`, e o
  // clique é o TOGGLE — Playwright dispara os dois no mesmo gesto, então um
  // `.click()` abriria e fecharia de novo.
  await historico.getByRole('button', { name: 'Detalhes desta antecipação' }).hover()

  // Seletor CSS, e não `getByRole('tooltip')`: o painel do `Tooltip` é
  // `aria-hidden` de propósito — quem lê por leitor de tela recebe o texto
  // pelo `sr-only` que o `aria-describedby` aponta —, e um elemento fora da
  // árvore de acessibilidade não é encontrável por papel.
  const tooltip = page.locator('[role="tooltip"]')
  await expect(tooltip).toBeVisible()
  await expect(tooltip).toContainText('Antecipação ignorada')
  await expect(tooltip).toContainText(`Solicitação de origem: #${solicitacaoId}`)
  await expect(tooltip).toContainText('Nenhum item ou guia foi criado')
  await expect(tooltip).toContainText('Motivo no tooltip.')
})

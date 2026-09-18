import { expect, test, type Page } from '@playwright/test'

/**
 * A webcam da leitura de carteirinha, em Pacientes.
 *
 * Esta tela funcionava e não tinha teste nenhum. Em 16/09/2026 o `getUserMedia`
 * dela foi extraído para `components/ui/CapturaWebcam`, compartilhado com a
 * leitura do registro de sessões — e passou a haver um jeito novo de quebrá-la:
 * uma mudança feita pensando em Sessões chega aqui de carona.
 *
 * O que estes testes protegem é justamente a DIFERENÇA entre as duas telas. Em
 * Sessões a foto congela para conferência, porque a folha é A4 manuscrita e uma
 * chamada de IA desperdiçada custa caro. Aqui não: a carteirinha é um cartão
 * pequeno e nítido, o resultado volta em campos editáveis, e a foto vai direto.
 * Se alguém ligar a conferência no componente compartilhado sem olhar quem mais
 * o usa, é aqui que tem de estourar.
 *
 * Câmera falsa do Chromium: `--use-fake-device-for-media-stream` dá o vídeo
 * sintético, `--use-fake-ui-for-media-stream` concede a permissão sem diálogo.
 */
test.use({
  launchOptions: {
    args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'],
  },
})

const LEITURA_FALSA = {
  documento_id: 4242,
  expira_em: '2026-10-16T00:00:00.000000Z',
  model: 'teste',
  dados: {
    carteirinha: '0220090000551330',
    nome: 'Zoroastro Buarque de Holanda',
    cpf: '12345678909',
    data_nascimento: '1990-04-08',
    validade_carteirinha: '2027-12-31',
    observacoes: null,
  },
  convenio: { lido: null, id: null, nome: null, similaridade: null, candidatos: [] },
}

/**
 * Responde a leitura sem chamar a IA.
 *
 * O objeto aqui é o caminho do navegador até o formulário — a qualidade da
 * extração depende da chave de produção e de um cartão real.
 */
async function interceptarLeitura(page: Page) {
  let chamadas = 0
  let corpo = ''

  await page.route('**/api/pacientes/ler-carteirinha', async (route) => {
    chamadas += 1
    corpo = route.request().postData() ?? ''

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: LEITURA_FALSA }),
    })
  })

  return { chamadas: () => chamadas, corpo: () => corpo }
}

async function login(page: Page) {
  await page.goto('/login')
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

async function abrirFormulario(page: Page) {
  await page.goto('/pacientes/novo', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('paciente-form')).toBeVisible()
}

test('a foto da webcam vai DIRETO para a leitura, sem tela de conferência', async ({ page }) => {
  await login(page)
  await abrirFormulario(page)

  const leitura = await interceptarLeitura(page)

  await expect(page.getByTestId('paciente-webcam')).toBeVisible()
  await page.getByTestId('paciente-webcam').click()

  await expect(page.getByTestId('paciente-webcam-preview')).toBeVisible()

  // O rótulo diz que o clique já lê — é a promessa que esta tela faz, e ela
  // difere da de Sessões ("Tirar foto").
  const capturar = page.getByTestId('paciente-webcam-capturar')
  await expect(capturar).toHaveText('Tirar foto e ler')
  await expect(capturar).toBeEnabled()

  expect(leitura.chamadas()).toBe(0)
  await capturar.click()

  // Sem passo de conferência: a leitura dispara no mesmo clique.
  await expect.poll(leitura.chamadas).toBe(1)
  await expect(page.getByTestId('paciente-webcam-previa')).toHaveCount(0)

  // A câmera fecha sozinha depois de capturar.
  await expect(page.getByTestId('paciente-webcam-preview')).toHaveCount(0)
  await expect(page.getByTestId('paciente-webcam')).toHaveText('Usar webcam')

  // E o que foi lido cai no formulário.
  await expect(page.getByTestId('paciente-nome')).toHaveValue('Zoroastro Buarque de Holanda')
  await expect(page.getByTestId('paciente-cpf')).toHaveValue(/123\.?456\.?789-?09/)
  await expect(page.getByTestId('paciente-data-nascimento')).toHaveValue('1990-04-08')

  // O anexo vai como JPEG nomeado, que é o que a API valida.
  expect(leitura.corpo()).toContain('carteirinha.jpg')
  expect(leitura.corpo()).toContain('image/jpeg')
})

test('cancelar fecha a camera sem ler nada', async ({ page }) => {
  await login(page)
  await abrirFormulario(page)

  const leitura = await interceptarLeitura(page)

  await page.getByTestId('paciente-webcam').click()
  await expect(page.getByTestId('paciente-webcam-preview')).toBeVisible()

  await page.getByTestId('paciente-webcam-cancelar').click()

  await expect(page.getByTestId('paciente-webcam-preview')).toHaveCount(0)
  expect(leitura.chamadas()).toBe(0)

  // O formulário fica intocado.
  await expect(page.getByTestId('paciente-nome')).toHaveValue('')
})

test('o botao alterna e fechar pela propria acao devolve a tela', async ({ page }) => {
  await login(page)
  await abrirFormulario(page)

  const alternar = page.getByTestId('paciente-webcam')
  await expect(alternar).toHaveText('Usar webcam')

  await alternar.click()
  await expect(alternar).toHaveText('Fechar webcam')
  await expect(page.getByTestId('paciente-webcam-preview')).toBeVisible()

  await alternar.click()
  await expect(alternar).toHaveText('Usar webcam')
  await expect(page.getByTestId('paciente-webcam-preview')).toHaveCount(0)

  // O caminho por arquivo continua disponível o tempo todo.
  await expect(page.getByTestId('paciente-ler-carteirinha')).toBeEnabled()
})

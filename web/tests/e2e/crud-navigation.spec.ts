import { expect, test, type Page } from '@playwright/test'

async function login(page: Page) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.getByTestId('login-email').fill('admin@clinica-exemplo.test')
  await page.getByTestId('login-password').fill('password')
  await page.getByTestId('login-submit').click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

test('formularios de criacao abrem em rotas proprias sem lista inline', async ({ page }) => {
  await login(page)

  const casos = [
    {
      path: '/pacientes/novo',
      formHeading: 'Novo paciente',
      listButtonTestId: 'paciente-novo',
      closeButtonTestId: 'paciente-fechar',
    },
    {
      path: '/solicitacoes/nova',
      formHeading: 'Nova solicitação',
      listButtonTestId: 'solicitacao-novo',
      closeButtonTestId: 'solicitacao-fechar',
    },
    {
      path: '/guias/nova',
      formHeading: 'Nova guia',
      listButtonTestId: 'guia-novo',
      closeButtonTestId: 'guia-fechar',
    },
    {
      path: '/profissionais/novo',
      formHeading: 'Novo profissional',
      listButtonTestId: 'profissional-novo',
      closeButtonTestId: 'profissional-fechar',
    },
    {
      path: '/medicos/novo',
      formHeading: 'Novo médico solicitante',
      listButtonTestId: 'medico-novo',
      closeButtonTestId: 'medico-fechar',
    },
    {
      path: '/especialidades/nova',
      formHeading: 'Nova especialidade',
      listButtonTestId: 'especialidade-novo',
      closeButtonTestId: 'especialidade-fechar',
    },
    {
      path: '/usuarios/novo',
      formHeading: 'Novo usuário',
      listButtonTestId: 'usuario-novo',
      closeButtonTestId: 'usuario-fechar',
    },
    {
      path: '/convenios/novo',
      formHeading: 'Novo convênio',
      listButtonTestId: 'convenio-novo',
      closeButtonTestId: 'convenio-fechar',
    },
    {
      path: '/lancamentos/novo',
      formHeading: 'Novo lançamento manual',
      listButtonTestId: 'lancamento-novo',
      closeButtonTestId: 'lancamento-fechar',
    },
    {
      path: '/permissoes/novo',
      formHeading: 'Novo perfil',
      listButtonTestId: 'papel-novo',
      closeButtonTestId: 'papel-fechar',
    },
  ] as const

  for (const caso of casos) {
    await page.goto(caso.path, { waitUntil: 'domcontentloaded' })

    await expect(page).toHaveURL(new RegExp(`${caso.path.replace(/\//g, '\\/')}$`))
    await expect(page.getByText(caso.formHeading, { exact: true })).toBeVisible()

    if ('listButtonTestId' in caso) {
      await expect(page.getByTestId(caso.listButtonTestId)).toHaveCount(0)
      await expect(page.getByTestId(caso.closeButtonTestId)).toBeVisible()
    } else {
      await expect(page.getByRole('button', { name: caso.listButtonText })).toHaveCount(0)
      await expect(page.getByRole('button', { name: caso.closeButtonText })).toBeVisible()
    }
  }
})

test('editar convenio abre em tela propria e volta para a listagem', async ({ page }) => {
  await login(page)

  await page.goto('/convenios', { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('convenio-novo')).toBeVisible()

  const editar = page.locator('[data-testid^="convenio-editar-"]').first()
  await expect(editar).toBeVisible()
  await editar.click()

  // A listagem sai de cena: so o formulario fica na tela.
  await expect(page).toHaveURL(/\/convenios\/\d+\/editar$/)
  await expect(page.getByText('Editar convênio', { exact: true })).toBeVisible()
  await expect(page.getByTestId('convenio-novo')).toHaveCount(0)
  await expect(page.locator('[data-testid^="convenio-editar-"]')).toHaveCount(0)

  // Recarregar a rota direto pela URL tem de hidratar o formulario do mesmo jeito.
  await page.reload({ waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId('convenio-nome')).not.toHaveValue('')

  await page.getByTestId('convenio-fechar').click()
  await expect(page).toHaveURL(/\/convenios$/)
  await expect(page.getByTestId('convenio-novo')).toBeVisible()
})

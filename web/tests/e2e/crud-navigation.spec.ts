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
      listButtonText: 'Novo convênio',
      closeButtonText: 'Cancelar',
    },
    {
      path: '/lancamentos/novo',
      formHeading: 'Novo lançamento manual',
      listButtonTestId: 'lancamento-novo',
      closeButtonTestId: 'lancamento-fechar',
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

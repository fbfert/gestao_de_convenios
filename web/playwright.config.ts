import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  retries: 0,
  timeout: 120000,
  reporter: 'line',
  use: {
    baseURL: 'http://127.0.0.1:4174',
    trace: 'on-first-retry',
  },
  webServer: [
    {
      command: 'node ./node_modules/vite/bin/vite.js --mode e2e --host 127.0.0.1 --port 4174 --strictPort',
      cwd: '.',
      url: 'http://127.0.0.1:4174',
      reuseExistingServer: false,
      // Aponta o navegador para a API de TESTE (8001), e não para a de
      // desenvolvimento.
      //
      // Sem isto, `VITE_API_URL` fica indefinido — o modo `e2e` esperava um
      // `web/.env.e2e` que o `.gitignore` do projeto exclui (`.env.*` casa em
      // qualquer diretório), então o arquivo nunca chega num clone. O cliente
      // então cai no padrão de `src/api/client.ts`, `http://localhost:8000/api`,
      // e a suíte inteira passa a exercitar o banco de DESENVOLVIMENTO em vez do
      // banco de teste que o `migrate:fresh` acabou de preparar.
      //
      // Definir aqui, e não num dotenv, deixa a suíte autossuficiente: não há
      // segredo nenhum nesta URL e nada precisa ser versionado à parte.
      env: {
        VITE_API_URL: 'http://127.0.0.1:8001/api',
      },
      stdout: 'ignore',
      stderr: 'ignore',
    },
    {
      command: 'php artisan serve --env=testing --host=127.0.0.1 --port=8001',
      cwd: '../api',
      url: 'http://127.0.0.1:8001/up',
      reuseExistingServer: false,
      env: {
        APP_ENV: 'testing',
        // Só para o processo levantado aqui, e não no `api/.env.testing`: o
        // phpunit também carrega aquele arquivo, e afrouxar o limite lá tornaria
        // inútil o AuthApiTest, que existe para provar que a sexta tentativa é
        // barrada. A suíte de navegador faz um login por cenário, oito em menos
        // de um minuto, e precisa do teto alto.
        LOGIN_TENTATIVAS_POR_MINUTO: '100',
      },
      stdout: 'ignore',
      stderr: 'ignore',
    },
  ],
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
})

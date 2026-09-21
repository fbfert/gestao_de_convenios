import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  /*
   * Um worker só, e não o padrão (metade dos núcleos).
   *
   * `fullyParallel: false` serializa os testes DENTRO de um arquivo, mas não
   * os arquivos entre si: com dois workers, dois specs batem no mesmo banco ao
   * mesmo tempo. E a suíte inteira compartilha um banco e os dados da semente
   * — `migrate:fresh --seed` roda uma vez, no começo.
   *
   * O estrago era mudança de dado compartilhado atravessando spec. O caso que
   * apareceu: `adicionar-sessoes` troca o convênio Unimed para
   * `connector_driver: unimed_rda`, porque precisa de um convênio automatizado;
   * enquanto isso vale, `mvp-flow` não consegue criar guia à mão na Unimed,
   * que `GuiaService::criar` recusa para convênio do robô. O `afterEach`
   * daquele arquivo devolve a Unimed a `manual`, e resolve o vazamento
   * SEQUENCIAL — mas não faz nada contra execução concorrente, porque durante
   * o teste o convênio está trocado para todo mundo.
   *
   * O sintoma era uma falha por rodada, em spec diferente a cada vez,
   * passando quando rodada sozinha: dependia de quem calhasse de estar na
   * janela. Medido em 18/09/2026: com 2 workers, 47/1 em duas rodadas; com 1
   * worker, 48/48 em duas rodadas.
   *
   * Não custa tempo: as rodadas levaram 2,7 e 3,3 min com um worker, contra
   * 2,7 e 3,6 min com dois. O paralelismo não ganhava nada — só produzia falha
   * aleatória.
   */
  workers: 1,
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
    /*
     * O worker Unimed de verdade, servindo as fixtures HTML do disco.
     *
     * Sem ele, tudo que depende da automação só podia ser coberto na API com o
     * worker falso — e o caminho API -> worker -> marca -> tela nunca era
     * exercitado inteiro. A alternativa seria abrir uma rota de teste no código
     * de produção para forjar o estado, que provaria o teste e não o produto.
     *
     * `UNIMED_PERMITIR_FIXTURES_LOCAIS` é a mesma flag que a suíte do próprio
     * worker usa (ver `loginUrlFromCredential` em portal.js): ela libera
     * `base_url` com esquema `file:`. Produção nunca a define, e sem ela o
     * worker recusa o desvio.
     */
    {
      command: 'node src/server.js',
      cwd: '../worker-unimed',
      url: 'http://127.0.0.1:8787/health',
      reuseExistingServer: false,
      env: {
        UNIMED_WORKER_HOST: '127.0.0.1',
        UNIMED_WORKER_PORT: '8787',
        UNIMED_WORKER_TOKEN: '',
        UNIMED_PERMITIR_FIXTURES_LOCAIS: '1',
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
        // A API fala com o worker acima. Sem isto ela usaria o padrão de
        // `config/services.php`, que aponta para o mesmo endereço mas não
        // garante que a suíte e o worker concordem sobre a porta.
        UNIMED_WORKER_URL: 'http://127.0.0.1:8787',
        UNIMED_WORKER_TOKEN: '',
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

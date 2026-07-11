import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  retries: 0,
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

import http from 'node:http'
import { executarGerarGuia } from './operations/gerarGuia.js'
import { executarCapturarAutorizacaoBatch, executarConsultarStatusBatch } from './operations/statusSenha.js'

const port = Number(process.env.UNIMED_WORKER_PORT ?? 8787)
// Padrao 127.0.0.1 para execucao local. Em container precisa ser 0.0.0.0,
// senao o gescon-app nao alcanca o worker pela rede interna do Docker.
const host = process.env.UNIMED_WORKER_HOST ?? '127.0.0.1'
const token = process.env.UNIMED_WORKER_TOKEN ?? ''

function sendJson(response, status, body) {
  response.writeHead(status, { 'content-type': 'application/json' })
  response.end(JSON.stringify(body))
}

function isAuthorized(request) {
  if (!token) {
    return true
  }

  return request.headers.authorization === `Bearer ${token}`
}

async function readJson(request) {
  const chunks = []

  for await (const chunk of request) {
    chunks.push(chunk)
  }

  const body = Buffer.concat(chunks).toString('utf8')
  return body ? JSON.parse(body) : {}
}

const server = http.createServer(async (request, response) => {
  if (!isAuthorized(request)) {
    sendJson(response, 401, { status: 'unauthorized' })
    return
  }

  if (request.method === 'GET' && request.url === '/health') {
    sendJson(response, 200, {
      status: 'available',
      browser: 'playwright',
    })
    return
  }

  if (request.method === 'POST' && request.url?.startsWith('/operations/')) {
    try {
      const operation = request.url.split('/').pop()
      const payload = await readJson(request)

      if (operation === 'gerar_guia') {
        const result = await executarGerarGuia({
          executionId: payload.execution_id ?? null,
          idempotencyKey: payload.idempotency_key ?? null,
          payload: payload.payload ?? {},
        })

        sendJson(response, 200, result)
        return
      }

      if (operation === 'consult_status_batch' || operation === 'consultar_status') {
        const result = await executarConsultarStatusBatch({
          executionId: payload.execution_id ?? null,
          idempotencyKey: payload.idempotency_key ?? null,
          payload: payload.payload ?? {},
        })

        sendJson(response, 200, result)
        return
      }

      if (operation === 'capture_authorization_data_batch' || operation === 'capturar_senha_validade') {
        const result = await executarCapturarAutorizacaoBatch({
          executionId: payload.execution_id ?? null,
          idempotencyKey: payload.idempotency_key ?? null,
          payload: payload.payload ?? {},
        })

        sendJson(response, 200, result)
        return
      }

      sendJson(response, 200, {
        status: 'succeeded',
        operation,
        execution_id: payload.execution_id ?? null,
        mock: true,
      })
    } catch (error) {
      sendJson(response, 400, {
        status: 'failed',
        error_code: 'INVALID_JSON',
        message: error instanceof Error ? error.message : 'Invalid request body',
      })
    }

    return
  }

  sendJson(response, 404, { status: 'not_found' })
})

server.listen(port, host, () => {
  console.log(`Unimed worker listening on http://${host}:${port}`)
})

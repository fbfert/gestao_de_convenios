import http from 'node:http'

const port = Number(process.env.UNIMED_WORKER_PORT ?? 8787)
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
      browser: 'mock',
    })
    return
  }

  if (request.method === 'POST' && request.url?.startsWith('/operations/')) {
    try {
      const operation = request.url.split('/').pop()
      const payload = await readJson(request)

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

server.listen(port, '127.0.0.1', () => {
  console.log(`Unimed worker mock listening on http://127.0.0.1:${port}`)
})

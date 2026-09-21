import assert from 'node:assert/strict'
import { test } from 'node:test'
import { once } from 'node:events'

/**
 * A rota da finalização existe e leva à operação de verdade.
 *
 * O que se prova aqui é só o roteamento: uma operação que o servidor não
 * conhece cai no eco `mock: true`, então basta ver que `finalizar_guia` NÃO
 * cai nele — devolve o erro da própria operação. O comportamento do fluxo
 * tem cobertura em finalizarGuia.test.js, contra a fixture.
 */

process.env.UNIMED_WORKER_PORT = '0'
process.env.UNIMED_WORKER_HOST = '127.0.0.1'
process.env.UNIMED_WORKER_TOKEN = ''

const { server } = await import('../src/server.js')

async function post(porta, operacao, payload) {
  const resposta = await fetch(`http://127.0.0.1:${porta}/operations/${operacao}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ execution_id: 1, idempotency_key: 'rota', payload }),
  })

  return resposta.json()
}

test('POST /operations/finalizar_guia chega na operação, e não no eco de mock', async (t) => {
  if (!server.listening) {
    await once(server, 'listening')
  }

  const { port } = server.address()
  t.after(() => server.close())

  // Sem número de guia a operação recusa antes de abrir o portal — é a
  // resposta mais barata que só a operação de verdade sabe dar.
  const resultado = await post(port, 'finalizar_guia', {})

  assert.equal(resultado.status, 'failed')
  assert.equal(resultado.error_code, 'NUMERO_GUIA_AUSENTE')
  assert.equal(resultado.mock, undefined)

  // E o contraste: uma operação desconhecida continua caindo no eco.
  const desconhecida = await post(port, 'operacao_que_nao_existe', {})
  assert.equal(desconhecida.mock, true)
})

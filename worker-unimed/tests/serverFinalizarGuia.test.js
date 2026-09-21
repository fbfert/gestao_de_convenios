import assert from 'node:assert/strict'
import { test } from 'node:test'
import { once } from 'node:events'

/**
 * As rotas das operações novas existem e levam à operação de verdade.
 *
 * O que se prova aqui é só o roteamento: uma operação que o servidor não
 * conhece cai no eco `mock: true`, então basta ver que as nossas NÃO caem nele
 * — devolvem a resposta que só a própria operação sabe dar. O comportamento do
 * fluxo tem cobertura em finalizarGuia.test.js e conferirGuiaFinalizada.test.js,
 * contra as fixtures.
 *
 * Um teste só, e não um por rota: o servidor é um processo compartilhado, e
 * dois testes fechando o mesmo `server` deixariam o segundo sem com quem falar.
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

test('as operações novas são roteadas, e o desconhecido continua caindo no eco', async (t) => {
  if (!server.listening) {
    await once(server, 'listening')
  }

  const { port } = server.address()
  t.after(() => server.close())

  // Sem número de guia a finalização recusa antes de abrir o portal — é a
  // resposta mais barata que só a operação de verdade sabe dar.
  const finalizar = await post(port, 'finalizar_guia', {})
  assert.equal(finalizar.status, 'failed')
  assert.equal(finalizar.error_code, 'NUMERO_GUIA_AUSENTE')
  assert.equal(finalizar.mock, undefined)

  // Sem guia nenhuma a conferência devolve resumo zerado sem abrir o portal.
  const conferir = await post(port, 'conferir_guia_finalizada', { guias: [] })
  assert.equal(conferir.status, 'succeeded')
  assert.deepEqual(conferir.resumo, { finalizadas: 0, nao_finalizadas: 0, falhas: 0 })
  assert.equal(conferir.mock, undefined)

  // E o contraste: uma operação desconhecida continua caindo no eco.
  const desconhecida = await post(port, 'operacao_que_nao_existe', {})
  assert.equal(desconhecida.mock, true)
})

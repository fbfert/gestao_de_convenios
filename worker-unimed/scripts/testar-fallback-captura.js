// Teste avulso, ao vivo, so leitura: roda executarCapturarAutorizacaoBatch
// de verdade (com o fix novo) contra o portal real pra uma guia conhecida
// que so aparece via cadastro, sem passar pelo Laravel — so pra ver o
// resultado antes de decidir sobre deploy.
import { executarCapturarAutorizacaoBatch } from '../src/operations/statusSenha.js'

async function main() {
  const credential = JSON.parse(process.env.UNIMED_CREDENTIAL_JSON)
  const numeroGuia = process.env.UNIMED_NUMERO_GUIA
  const carteirinha = process.env.UNIMED_CARTEIRINHA
  const nome = process.env.UNIMED_PACIENTE_NOME

  const resultado = await executarCapturarAutorizacaoBatch({
    executionId: 0,
    payload: {
      credential,
      guia_id: 0,
      numero_guia: numeroGuia,
      paciente: { nome, carteirinha },
    },
  })

  console.log(JSON.stringify(resultado, null, 2))
}

main()

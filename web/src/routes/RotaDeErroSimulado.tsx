/**
 * Uma rota que lança, para o teste provar que a tela de erro aparece.
 *
 * Só existe no modo `e2e` (`vite --mode e2e`, que só a suíte usa). O
 * `import.meta.env.MODE` é resolvido em tempo de build, então no bundle de
 * produção este componente vira `null` e a rota nunca é registrada.
 *
 * **Por que uma rota inteira e não um `?simular-erro=1` num componente real:**
 * um componente de produção que sabe lançar é um componente de produção que
 * pode lançar. Aqui não há como o caminho ser alcançado em produção — o código
 * simplesmente não está lá.
 */

const ativo = import.meta.env.MODE === 'e2e'

function Explode(): never {
  throw new Error('Erro simulado para o teste da tela de erro')
}

/** `null` fora do modo e2e: quem monta a rota decide pelo valor. */
export const RotaDeErroSimulado = ativo ? Explode : null

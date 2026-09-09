import { useEffect, useRef } from 'react'

/**
 * Modal fecha só por ação explícita: botão Fechar/Cancelar ou Esc. Clique fora
 * não fecha.
 *
 * O `Dialog` do Headless UI chama `onClose` nos DOIS casos — clique no fundo e
 * Esc — e não distingue um do outro nem oferece opção de desligar só um. Então
 * o caminho é neutralizar o `onClose` dele e reimplementar o Esc aqui.
 *
 * Por que isso importa: estas telas têm formulário. Um clique fora do painel
 * enquanto se digita uma solicitação joga fora o que foi preenchido, sem
 * pergunta e sem desfazer — e o clique fora é fácil de dar sem querer, ao mirar
 * um campo e errar a borda. Esc é diferente: ninguém aperta Esc por acidente.
 *
 * A pilha existe por causa dos modais aninhados — `SelecionarMedicoModal` abre
 * dentro do `SolicitacaoGuiaModal`, por exemplo. Sem ela, os dois ouviriam o
 * mesmo `keydown` no documento e um Esc fecharia os dois de uma vez. Só o
 * diálogo do topo responde.
 */
const pilhaDeModais: symbol[] = []

export function useFechamentoExplicito(aberto: boolean, onClose: () => void) {
  // Guardado em ref para o efeito não reassinar o listener a cada render —
  // os call sites passam arrow inline, que muda de identidade toda vez.
  const fechar = useRef(onClose)
  fechar.current = onClose

  useEffect(() => {
    if (!aberto) {
      return
    }

    const identificador = Symbol('modal')
    pilhaDeModais.push(identificador)

    const aoTeclar = (evento: KeyboardEvent) => {
      if (evento.key !== 'Escape') {
        return
      }

      if (pilhaDeModais[pilhaDeModais.length - 1] !== identificador) {
        return
      }

      evento.stopPropagation()
      fechar.current()
    }

    document.addEventListener('keydown', aoTeclar)

    return () => {
      document.removeEventListener('keydown', aoTeclar)
      const posicao = pilhaDeModais.indexOf(identificador)

      if (posicao !== -1) {
        pilhaDeModais.splice(posicao, 1)
      }
    }
  }, [aberto])

  /**
   * Para espalhar no `<Dialog {...props}>`. O `onClose` vazio é o que ignora o
   * clique fora; o Esc vem do efeito acima.
   */
  return { open: aberto, onClose: naoFecharPorCliqueFora }
}

function naoFecharPorCliqueFora() {}

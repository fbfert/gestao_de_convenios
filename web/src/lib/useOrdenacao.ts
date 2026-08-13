import { useState } from 'react'

export type Ordenacao = {
  ordenar_por: string
  direcao: 'asc' | 'desc'
}

/**
 * Estado de ordenação de uma listagem.
 *
 * Clicar na mesma coluna inverte o sentido; coluna nova começa crescente. A
 * ordenação é sempre resolvida no servidor — ordenar só a página visível daria
 * a impressão de listar tudo em ordem quando não é o caso.
 */
export function useOrdenacao(inicial: Ordenacao) {
  const [ordenacao, setOrdenacao] = useState<Ordenacao>(inicial)

  const ordenarPor = (coluna: string) => {
    setOrdenacao((atual) => ({
      ordenar_por: coluna,
      direcao: atual.ordenar_por === coluna && atual.direcao === 'asc' ? 'desc' : 'asc',
    }))
  }

  return { ordenacao, ordenarPor, setOrdenacao }
}

import { create } from 'zustand'

/**
 * Temas visuais do sistema.
 *
 * `claro` é o design system do xiax-agenda, e é o padrão. `contraste` existe
 * por um requisito de acessibilidade concreto: um profissional da clínica tem
 * deficiência de visão de cor e precisa que cada cartão tenha borda marcada.
 *
 * O tema não é o `claro` com borda mais grossa — as cores de feedback foram
 * redesenhadas, porque na paleta padrão "Aprovado" e "Negado" ficam
 * praticamente iguais sob deuteranopia. Ver o bloco correspondente em
 * `index.css` para a medição e o desenho.
 */
export type Tema = 'claro' | 'contraste'

export const temaStorageKey = 'gestao-convenios-tema'

export const temaOpcoes: { value: Tema; label: string; descricao: string }[] = [
  {
    value: 'claro',
    label: 'Padrão',
    descricao: 'A aparência do sistema, compartilhada com a agenda da clínica.',
  },
  {
    value: 'contraste',
    label: 'Alto contraste',
    descricao:
      'Bordas marcadas em cada cartão e cores de status escolhidas para não se confundirem com daltonismo.',
  },
]

export const temaPadrao: Tema = 'claro'

function ehTema(valor: unknown): valor is Tema {
  return valor === 'claro' || valor === 'contraste'
}

export function lerTemaGravado(): Tema {
  try {
    const gravado = window.localStorage.getItem(temaStorageKey)
    return ehTema(gravado) ? gravado : temaPadrao
  } catch {
    return temaPadrao
  }
}

export function aplicarTema(tema: Tema) {
  document.documentElement.dataset.theme = tema
}

type EstadoTema = {
  tema: Tema
  definirTema: (tema: Tema) => void
}

export const useTemaStore = create<EstadoTema>()((set) => ({
  tema: typeof window === 'undefined' ? temaPadrao : lerTemaGravado(),
  definirTema: (tema) => {
    aplicarTema(tema)

    try {
      window.localStorage.setItem(temaStorageKey, tema)
    } catch {
      // Navegador sem localStorage: o tema vale só para esta sessão.
    }

    set({ tema })
  },
}))

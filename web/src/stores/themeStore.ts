import { create } from 'zustand'

export type Theme = 'escuro' | 'claro'

export const themeStorageKey = 'gestao-convenios-tema'

export const themeOptions: { value: Theme; label: string; description: string }[] = [
  {
    value: 'claro',
    label: 'Claro',
    description: 'Fundo claro e texto escuro. Melhor em salas com muita luz natural. Tema padrão.',
  },
  {
    value: 'escuro',
    label: 'Escuro',
    description: 'Visual original, com fundo azul-noite. Melhor em ambientes com pouca luz.',
  },
]

// Tema "Clínicas Claro" (visual do clinica.gestaonossa.com.br) removido em
// 20/08/2026 a pedido do usuário — quem tinha ele salvo no localStorage cai
// aqui automaticamente, porque isTheme() não aceita mais o valor antigo.
export const defaultTheme: Theme = 'claro'

function isTheme(value: unknown): value is Theme {
  return value === 'escuro' || value === 'claro'
}

export function readStoredTheme(): Theme {
  try {
    const stored = window.localStorage.getItem(themeStorageKey)
    return isTheme(stored) ? stored : defaultTheme
  } catch {
    return defaultTheme
  }
}

export function applyTheme(theme: Theme) {
  document.documentElement.dataset.theme = theme
}

type ThemeState = {
  theme: Theme
  setTheme: (theme: Theme) => void
}

export const useThemeStore = create<ThemeState>()((set) => ({
  theme: typeof window === 'undefined' ? defaultTheme : readStoredTheme(),
  setTheme: (theme) => {
    applyTheme(theme)

    try {
      window.localStorage.setItem(themeStorageKey, theme)
    } catch {
      // navegador sem localStorage disponível: o tema vale só para esta sessão
    }

    set({ theme })
  },
}))

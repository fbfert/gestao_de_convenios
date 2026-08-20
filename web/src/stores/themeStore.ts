import { create } from 'zustand'

export type Theme = 'escuro' | 'claro' | 'clinicas-claro'

export const themeStorageKey = 'gestao-convenios-tema'

export const themeOptions: { value: Theme; label: string; description: string }[] = [
  {
    value: 'clinicas-claro',
    label: 'Clínicas Claro',
    description: 'O visual do clinica.gestaonossa.com.br — bege claro e verde-petróleo. Tema padrão.',
  },
  {
    value: 'escuro',
    label: 'Escuro',
    description: 'Visual original, com fundo azul-noite. Melhor em ambientes com pouca luz.',
  },
  {
    value: 'claro',
    label: 'Claro',
    description: 'Fundo claro e texto escuro. Melhor em salas com muita luz natural.',
  },
]

// Vira o padrão em 20/08/2026 — quem já tinha 'escuro'/'claro' salvo no
// localStorage continua exatamente como está (isTheme só valida o que já
// existe lá; nunca reescreve sozinho).
export const defaultTheme: Theme = 'clinicas-claro'

function isTheme(value: unknown): value is Theme {
  return value === 'escuro' || value === 'claro' || value === 'clinicas-claro'
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

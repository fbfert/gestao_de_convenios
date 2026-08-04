import { create } from 'zustand'
import { createJSONStorage, persist } from 'zustand/middleware'

export type AuthTenant = {
  id: number
  nome: string
  slug: string
}

export type AuthUser = {
  id: number
  name: string
  email: string
  role: string
  permissions?: string[]
  tenant: AuthTenant
}

type LoginPayload = {
  token: string
  user: AuthUser
}

type AuthState = {
  token: string | null
  user: AuthUser | null
  tenant: AuthTenant | null
  login: (payload: LoginPayload) => void
  logout: () => void
  isAuthenticated: () => boolean
}

const authStorageKey = 'gestao-convenios-auth'

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      user: null,
      tenant: null,
      login: ({ token, user }) =>
        set({
          token,
          user,
          tenant: user.tenant,
        }),
      logout: () =>
        set({
          token: null,
          user: null,
          tenant: null,
        }),
      isAuthenticated: () => Boolean(get().token),
    }),
    {
      name: authStorageKey,
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({
        token: state.token,
        user: state.user,
        tenant: state.tenant,
      }),
    },
  ),
)

export type { LoginPayload }

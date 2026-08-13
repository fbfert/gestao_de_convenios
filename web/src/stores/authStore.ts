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
  /**
   * Permissões efetivas do papel. Opcional só por causa de sessão antiga
   * gravada no localStorage antes de a API passar a mandar o campo: quem lê
   * trata `undefined` como "ainda não sei", nunca como "não pode".
   */
  permissions?: string[]
  /**
   * Administra clínicas. Controla apenas a exibição do item de menu; quem
   * barra de fato é o middleware `super-admin` da API.
   */
  super_admin?: boolean
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
  /** Reaplica o usuário vindo de GET /user, para mudança de papel valer sem novo login. */
  sincronizarUsuario: (user: AuthUser) => void
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
      sincronizarUsuario: (user) =>
        set({
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

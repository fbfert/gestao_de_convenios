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
  /**
   * Este payload é de um super admin atuando fora da própria clínica (ver
   * TenantController::acessar). Serve pra reconciliar a faixa de aviso após
   * um refresh de página — a fonte de verdade é sempre o que a API manda,
   * não o estado local gravado no acessarComoSuperAdmin().
   */
  acesso_super_admin?: boolean
  tenant: AuthTenant
}

type LoginPayload = {
  token: string
  user: AuthUser
}

type AcessoSuperAdminState = {
  ativo: boolean
  tokenOrigem: string | null
  userOrigem: AuthUser | null
}

const acessoSuperAdminVazio: AcessoSuperAdminState = {
  ativo: false,
  tokenOrigem: null,
  userOrigem: null,
}

type AuthState = {
  token: string | null
  user: AuthUser | null
  tenant: AuthTenant | null
  acessoSuperAdmin: AcessoSuperAdminState
  login: (payload: LoginPayload) => void
  /** Reaplica o usuário vindo de GET /user, para mudança de papel valer sem novo login. */
  sincronizarUsuario: (user: AuthUser) => void
  /**
   * Entra no "acesso de super admin" a outra clínica — guarda o token/user
   * de origem (só na primeira vez, pra trocar de clínica em sequência não
   * perder o caminho de volta) e troca a sessão ativa pro token novo.
   */
  acessarComoSuperAdmin: (payload: LoginPayload) => void
  /** Sai do acesso de super admin, restaurando o token/user de origem. */
  sairAcessoSuperAdmin: () => void
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
      acessoSuperAdmin: acessoSuperAdminVazio,
      login: ({ token, user }) =>
        set({
          token,
          user,
          tenant: user.tenant,
          acessoSuperAdmin: acessoSuperAdminVazio,
        }),
      sincronizarUsuario: (user) =>
        set({
          user,
          tenant: user.tenant,
        }),
      acessarComoSuperAdmin: ({ token, user }) => {
        const atual = get()
        const jaEmAcesso = atual.acessoSuperAdmin.ativo

        set({
          token,
          user,
          tenant: user.tenant,
          acessoSuperAdmin: {
            ativo: true,
            // Trocando de clínica em sequência (acesso -> acesso de novo),
            // a origem continua sendo a clínica de verdade do super admin,
            // não a que ele estava visitando antes de trocar.
            tokenOrigem: jaEmAcesso ? atual.acessoSuperAdmin.tokenOrigem : atual.token,
            userOrigem: jaEmAcesso ? atual.acessoSuperAdmin.userOrigem : atual.user,
          },
        })
      },
      sairAcessoSuperAdmin: () => {
        const { acessoSuperAdmin } = get()

        set({
          token: acessoSuperAdmin.tokenOrigem,
          user: acessoSuperAdmin.userOrigem,
          tenant: acessoSuperAdmin.userOrigem?.tenant ?? null,
          acessoSuperAdmin: acessoSuperAdminVazio,
        })
      },
      logout: () =>
        set({
          token: null,
          user: null,
          tenant: null,
          acessoSuperAdmin: acessoSuperAdminVazio,
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
        acessoSuperAdmin: state.acessoSuperAdmin,
      }),
    },
  ),
)

export type { LoginPayload }

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'

export type Tenant = {
  id: number
  nome: string
  slug: string
  cnpj: string | null
  ativo: boolean
  usuarios_count: number
  created_at: string | null
}

export type TenantForm = {
  nome: string
  slug: string
  cnpj: string
  ativo: boolean
  admin: {
    name: string
    email: string
    password: string
  }
}

export type TenantEdicaoForm = {
  nome: string
  cnpj: string
  ativo: boolean
}

export const tenantVazio: TenantForm = {
  nome: '',
  slug: '',
  cnpj: '',
  ativo: true,
  admin: { name: '', email: '', password: '' },
}

const chaveQuery = ['tenants']

/**
 * Deriva o identificador a partir do nome: minúsculas, sem acento, hífen no
 * lugar de espaço. É só uma sugestão — o campo continua editável, e o backend
 * é quem valida o formato e a unicidade.
 */
export function sugerirSlug(nome: string): string {
  return nome
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 60)
}

export function useTenants() {
  return useQuery({
    queryKey: chaveQuery,
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Tenant[] }>('/tenants')
      return data.data
    },
  })
}

export function useCriarTenant() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (form: TenantForm) => {
      const { data } = await apiClient.post<{ data: Tenant }>('/tenants', {
        nome: form.nome.trim(),
        slug: form.slug.trim(),
        cnpj: form.cnpj.trim() || null,
        ativo: form.ativo,
        admin: {
          name: form.admin.name.trim(),
          email: form.admin.email.trim(),
          password: form.admin.password,
        },
      })
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chaveQuery })
    },
  })
}

export function useAtualizarTenant() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, form }: { id: number; form: TenantEdicaoForm }) => {
      const { data } = await apiClient.put<{ data: Tenant }>(`/tenants/${id}`, {
        nome: form.nome.trim(),
        cnpj: form.cnpj.trim() || null,
        ativo: form.ativo,
      })
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chaveQuery })
    },
  })
}

export { getHttpErrorMessage }

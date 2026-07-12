import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { PaginatedResponse, Usuario, UsuarioFilters, UsuarioForm, UsuarioRoleRef } from './types'
import type { ProfissionalRef } from '../../lib/queries/useReferenceData'

type ListResponse<T> = {
  data: T[]
}

export function useUsuarios(filters: UsuarioFilters, page: number) {
  return useQuery({
    queryKey: ['usuarios', filters, page],
    queryFn: async () => {
      const { data } = await apiClient.get<PaginatedResponse<Usuario>>('/usuarios', {
        params: {
          busca: filters.busca || undefined,
          page,
          per_page: 10,
        },
      })

      return data
    },
  })
}

export function useRolesDoTenant() {
  return useQuery({
    queryKey: ['roles'],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<UsuarioRoleRef>>('/roles')
      return data.data
    },
  })
}

export function useProfissionaisDoTenant() {
  return useQuery({
    queryKey: ['profissionais'],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<ProfissionalRef>>('/profissionais')
      return data.data
    },
  })
}

export function useCriarUsuario() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: UsuarioForm) => {
      const { data } = await apiClient.post<{ data: Usuario }>('/usuarios', {
        ...payload,
        role: payload.role,
        ativo: payload.ativo,
        profissional_id:
          payload.role === 'profissional' && payload.profissional_id
            ? Number(payload.profissional_id)
            : null,
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['usuarios'] })
    },
  })
}

export function useAtualizarUsuario() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      id,
      payload,
    }: {
      id: number
      payload: Partial<UsuarioForm>
    }) => {
      const body: Record<string, unknown> = {
        ...payload,
      }

      if (typeof payload.profissional_id !== 'undefined') {
        body.profissional_id =
          payload.role === 'profissional' && payload.profissional_id
            ? Number(payload.profissional_id)
            : payload.role === 'profissional'
              ? null
              : null
      }

      if (typeof payload.password === 'string' && payload.password.trim() === '') {
        delete body.password
      }

      const { data } = await apiClient.patch<{ data: Usuario }>(`/usuarios/${id}`, body)
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['usuarios'] })
    },
  })
}

export { getHttpErrorMessage }

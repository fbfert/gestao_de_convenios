import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'
import type { PermissionRef, RolePermissionsResponse, RoleRef } from './types'

type ListResponse<T> = {
  data: T[]
}

export function useRoles() {
  return useQuery({
    queryKey: ['roles'],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<RoleRef>>('/roles')
      return data.data
    },
  })
}

export function usePermissions() {
  return useQuery({
    queryKey: ['permissions'],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<PermissionRef>>('/permissions')
      return data.data
    },
  })
}

export function useRolePermissions(roleName: string) {
  return useQuery({
    queryKey: ['role-permissions', roleName],
    queryFn: async () => {
      const { data } = await apiClient.get<RolePermissionsResponse>(`/roles/${roleName}/permissions`)
      return data.data
    },
    enabled: roleName !== '',
  })
}

export function useUpdateRolePermissions() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ roleName, permissions }: { roleName: string; permissions: string[] }) => {
      const { data } = await apiClient.put<RolePermissionsResponse>(`/roles/${roleName}/permissions`, {
        permissions,
      })
      return data.data
    },
    onSuccess: async (_, variables) => {
      await queryClient.invalidateQueries({ queryKey: ['roles'] })
      await queryClient.invalidateQueries({ queryKey: ['role-permissions', variables.roleName] })
    },
  })
}

export { getHttpErrorMessage }

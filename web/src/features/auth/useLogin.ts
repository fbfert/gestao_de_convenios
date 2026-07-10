import { useMutation } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { useAuthStore, type LoginPayload } from '../../stores/authStore'

export type LoginCredentials = {
  email: string
  password: string
}

export function useLogin() {
  return useMutation({
    mutationFn: async (credentials: LoginCredentials) => {
      const { data } = await apiClient.post<LoginPayload>('/login', credentials)
      return data
    },
    onSuccess: (data) => {
      useAuthStore.getState().login(data)
    },
  })
}

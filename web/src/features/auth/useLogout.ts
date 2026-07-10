import { useMutation } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { useAuthStore } from '../../stores/authStore'

export function useLogout() {
  return useMutation({
    mutationFn: async () => {
      await apiClient.post('/logout')
    },
    onSettled: () => {
      useAuthStore.getState().logout()
    },
  })
}

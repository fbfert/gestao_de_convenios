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

/**
 * Sai do "acesso de super admin" a outra clínica. Reusa POST /logout de
 * propósito: o backend só derruba o token usado NA requisição (o de acesso),
 * nunca todos os tokens do usuário — então o token de origem, guardado no
 * authStore, continua válido pra restaurar a sessão.
 */
export function useSairAcessoSuperAdmin() {
  return useMutation({
    mutationFn: async () => {
      await apiClient.post('/logout')
    },
    onSettled: () => {
      useAuthStore.getState().sairAcessoSuperAdmin()
    },
  })
}

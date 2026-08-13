import { useQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { apiClient } from '../../api/client'
import { useAuthStore, type AuthUser } from '../../stores/authStore'

/**
 * Reconsulta papel e permissões do usuário logado a cada abertura do app.
 *
 * Sem isso, a única fonte seria o payload do login, guardado no localStorage:
 * um administrador que mexesse nas permissões de um papel só veria efeito
 * depois que cada pessoa daquele papel saísse e entrasse de novo — e ninguém
 * faz isso porque ninguém sabe que precisa.
 */
export function useSessaoAtual() {
  const token = useAuthStore((state) => state.token)
  const sincronizarUsuario = useAuthStore((state) => state.sincronizarUsuario)

  const query = useQuery({
    queryKey: ['sessao-atual'],
    enabled: Boolean(token),
    queryFn: async () => (await apiClient.get<AuthUser>('/user')).data,
  })

  useEffect(() => {
    if (query.data) {
      sincronizarUsuario(query.data)
    }
  }, [query.data, sincronizarUsuario])

  return query
}

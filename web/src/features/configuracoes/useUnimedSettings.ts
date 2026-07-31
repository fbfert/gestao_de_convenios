import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'

export type UnimedConvenioOption = {
  id: number
  nome: string
  connector_type: string | null
  connector_driver: 'unimed_rda' | null
  ativo: boolean
}

export type UnimedCredentialSettings = {
  id: number
  login: string
  base_url: string | null
  ativo: boolean
  senha_configurada: boolean
  automation_paused_at: string | null
  automation_paused_reason: string | null
}

export type UnimedSettings = {
  credential: UnimedCredentialSettings | null
  convenio_id: number | null
  convenios: UnimedConvenioOption[]
}

export type UnimedCredentialForm = {
  login: string
  password: string
  base_url: string
  ativo: boolean
}

export type UnimedSettingsForm = {
  convenio_id: string
  credential: UnimedCredentialForm
}

export function useUnimedSettings() {
  return useQuery({
    queryKey: ['configuracoes', 'unimed'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: UnimedSettings }>('/configuracoes/unimed')
      return data.data
    },
  })
}

export function useSalvarUnimedSettings() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: UnimedSettingsForm) => {
      const { data } = await apiClient.put<{ data: UnimedSettings }>('/configuracoes/unimed', {
        convenio_id: payload.convenio_id ? Number(payload.convenio_id) : null,
        credential: {
          login: payload.credential.login,
          password: payload.credential.password,
          base_url: payload.credential.base_url.trim() || null,
          ativo: payload.credential.ativo,
        },
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'unimed'] })
    },
  })
}

export function useUnimedWorkerHealth() {
  return useQuery({
    queryKey: ['configuracoes', 'unimed', 'worker-health'],
    enabled: false,
    queryFn: async () => {
      const { data } = await apiClient.get<{
        data: {
          status: 'available' | 'unavailable'
          worker: Record<string, unknown> | null
        }
      }>('/configuracoes/unimed/worker-health')
      return data.data
    },
    retry: false,
  })
}

export function useReativarUnimed() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post<{ data: UnimedSettings }>(
        '/configuracoes/unimed/reativar',
      )
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'unimed'] })
    },
  })
}

export { getHttpErrorMessage }

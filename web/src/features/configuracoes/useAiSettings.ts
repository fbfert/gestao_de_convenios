import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'

export type AiOpenaiSettings = {
  id: number | null
  base_url: string
  organization_id: string | null
  project_id: string | null
  ativo: boolean
  api_key_configurada: boolean
}

export type AiPromptTemplate = {
  id: number | null
  chave: 'ler_solicitacao_medica' | 'ler_sessoes_escaneadas'
  nome: string
  descricao: string | null
  model_id: string | null
  system_prompt: string
  user_prompt: string
  ativo: boolean
}

export type AiSettings = {
  openai: AiOpenaiSettings | null
  prompts: AiPromptTemplate[]
}

export type AiOpenaiForm = {
  api_key: string
  base_url: string
  organization_id: string
  project_id: string
  ativo: boolean
}

export type AiSettingsForm = {
  openai: AiOpenaiForm
  prompts: AiPromptTemplate[]
}

export type AiModelOption = {
  id: string
  owned_by: string | null
}

export function useAiSettings() {
  return useQuery({
    queryKey: ['configuracoes', 'ia'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AiSettings }>('/configuracoes/ia')
      return data.data
    },
  })
}

export function useAiModels(enabled: boolean) {
  return useQuery({
    queryKey: ['configuracoes', 'ia', 'modelos'],
    enabled,
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AiModelOption[] }>(
        '/configuracoes/ia/modelos',
      )
      return data.data
    },
    retry: false,
  })
}

export function useSalvarAiSettings() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: AiSettingsForm) => {
      const { data } = await apiClient.put<{ data: AiSettings }>('/configuracoes/ia', {
        openai: {
          api_key: payload.openai.api_key,
          base_url: payload.openai.base_url,
          organization_id: payload.openai.organization_id.trim() || null,
          project_id: payload.openai.project_id.trim() || null,
          ativo: payload.openai.ativo,
        },
        prompts: payload.prompts,
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'ia'] })
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'ia', 'modelos'] })
    },
  })
}

export { getHttpErrorMessage }

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'

export type AiPrompt = {
  id: number
  chave: string
  nome: string
  descricao: string | null
  model_id: string | null
  system_prompt: string
  user_prompt: string
  ativo: boolean
  /**
   * Prompt que o backend procura pela chave. A tela trava a chave e esconde o
   * botao de excluir; a API recusa as duas operacoes de qualquer forma.
   */
  sistema: boolean
}

export type AiPromptForm = {
  chave: string
  nome: string
  descricao: string
  model_id: string
  system_prompt: string
  user_prompt: string
  ativo: boolean
}

export const promptVazio: AiPromptForm = {
  chave: '',
  nome: '',
  descricao: '',
  model_id: '',
  system_prompt: '',
  user_prompt: '',
  ativo: true,
}

const chaveQuery = ['configuracoes', 'ia', 'prompts']

function paraPayload(form: AiPromptForm) {
  return {
    chave: form.chave.trim(),
    nome: form.nome.trim(),
    descricao: form.descricao.trim() || null,
    model_id: form.model_id.trim() || null,
    system_prompt: form.system_prompt,
    user_prompt: form.user_prompt,
    ativo: form.ativo,
  }
}

export function useAiPrompts() {
  return useQuery({
    queryKey: chaveQuery,
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: AiPrompt[] }>('/configuracoes/ia/prompts')
      return data.data
    },
  })
}

export function useCriarAiPrompt() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (form: AiPromptForm) => {
      const { data } = await apiClient.post<{ data: AiPrompt }>(
        '/configuracoes/ia/prompts',
        paraPayload(form),
      )
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chaveQuery })
    },
  })
}

export function useAtualizarAiPrompt() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, form }: { id: number; form: AiPromptForm }) => {
      const { data } = await apiClient.put<{ data: AiPrompt }>(
        `/configuracoes/ia/prompts/${id}`,
        paraPayload(form),
      )
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chaveQuery })
    },
  })
}

export function useExcluirAiPrompt() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/configuracoes/ia/prompts/${id}`)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chaveQuery })
    },
  })
}

export { getHttpErrorMessage }

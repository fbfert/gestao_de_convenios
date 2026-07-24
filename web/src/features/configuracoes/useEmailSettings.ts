import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'

export type EmailSmtpSettings = {
  id: number | null
  host: string
  port: number
  username: string | null
  encryption: 'tls' | 'ssl' | null
  from_email: string
  from_name: string | null
  ativo: boolean
  senha_configurada: boolean
}

export type EmailTemplateSettings = {
  id: number | null
  chave: string
  nome: string
  assunto: string
  corpo: string
  ativo: boolean
}

export type EmailSettings = {
  smtp: EmailSmtpSettings | null
  templates: EmailTemplateSettings[]
}

export type EmailSmtpForm = {
  host: string
  port: string
  username: string
  password: string
  encryption: '' | 'tls' | 'ssl'
  from_email: string
  from_name: string
  ativo: boolean
}

export type EmailSettingsForm = {
  smtp: EmailSmtpForm
}

export type EmailTemplateForm = {
  chave: string
  nome: string
  assunto: string
  corpo: string
  ativo: boolean
}

export function useEmailSettings() {
  return useQuery({
    queryKey: ['configuracoes', 'emails'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: EmailSettings }>('/configuracoes/emails')
      return data.data
    },
  })
}

export function useSalvarEmailSettings() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: EmailSettingsForm) => {
      const { data } = await apiClient.put<{ data: EmailSettings }>('/configuracoes/emails', {
        smtp: {
          host: payload.smtp.host,
          port: Number(payload.smtp.port),
          username: payload.smtp.username.trim() || null,
          password: payload.smtp.password,
          encryption: payload.smtp.encryption || null,
          from_email: payload.smtp.from_email,
          from_name: payload.smtp.from_name.trim() || null,
          ativo: payload.smtp.ativo,
        },
      })

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'emails'] })
    },
  })
}

export function useEmailTemplates() {
  return useQuery({
    queryKey: ['configuracoes', 'emails', 'templates'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: EmailTemplateSettings[] }>(
        '/configuracoes/emails/templates',
      )
      return data.data
    },
  })
}

export function useCriarEmailTemplate() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (payload: EmailTemplateForm) => {
      const { data } = await apiClient.post<{ data: EmailTemplateSettings }>(
        '/configuracoes/emails/templates',
        payload,
      )
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'emails'] })
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'emails', 'templates'] })
    },
  })
}

export function useAtualizarEmailTemplate() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: EmailTemplateForm }) => {
      const { data } = await apiClient.put<{ data: EmailTemplateSettings }>(
        `/configuracoes/emails/templates/${id}`,
        payload,
      )
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'emails'] })
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'emails', 'templates'] })
    },
  })
}

export function useExcluirEmailTemplate() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/configuracoes/emails/templates/${id}`)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'emails'] })
      await queryClient.invalidateQueries({ queryKey: ['configuracoes', 'emails', 'templates'] })
    },
  })
}

export { getHttpErrorMessage }

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
  nome_contratado: string | null
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
  nome_contratado: string
  ativo: boolean
}

export type UnimedSettingsForm = {
  convenio_id: string
  credential: UnimedCredentialForm
}

export type UnimedEspecialidadeMapeamento = {
  id: number
  convenio_id: number
  especialidade_id: number
  codigo_procedimento: string
  descricao_operadora: string | null
  quantidade_padrao: number
  usa_descricao_generica: boolean
  valor_generico: string | null
  ativo: boolean
  convenio?: { id: number; nome: string }
  especialidade?: { id: number; nome: string }
}

export type UnimedProfissionalMapeamento = {
  id: number
  convenio_id: number
  profissional_id: number
  codigo_operadora: string
  nome_operadora: string | null
  ativo: boolean
  convenio?: { id: number; nome: string }
  profissional?: { id: number; nome: string }
}

export type UnimedEspecialidadeMapeamentoForm = {
  convenio_id: string
  especialidade_id: string
  codigo_procedimento: string
  descricao_operadora: string
  quantidade_padrao: string
  usa_descricao_generica: boolean
  valor_generico: string
  ativo: boolean
}

export type UnimedProfissionalMapeamentoForm = {
  convenio_id: string
  profissional_id: string
  codigo_operadora: string
  nome_operadora: string
  ativo: boolean
}

export function useUnimedSettings(options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ['configuracoes', 'unimed'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: UnimedSettings }>('/configuracoes/unimed')
      return data.data
    },
    enabled: options?.enabled ?? true,
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
          nome_contratado: payload.credential.nome_contratado.trim() || null,
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

export function useUnimedEspecialidadeMapeamentos(convenioId?: string) {
  return useQuery({
    queryKey: ['configuracoes', 'unimed', 'mapeamentos', 'especialidades', convenioId ?? ''],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: UnimedEspecialidadeMapeamento[] }>(
        '/configuracoes/unimed/mapeamentos/especialidades',
        { params: { convenio_id: convenioId || undefined } },
      )
      return data.data
    },
  })
}

export function useSalvarUnimedEspecialidadeMapeamento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      id,
      payload,
    }: {
      id?: number
      payload: UnimedEspecialidadeMapeamentoForm
    }) => {
      const body = {
        convenio_id: Number(payload.convenio_id),
        especialidade_id: Number(payload.especialidade_id),
        codigo_procedimento: payload.codigo_procedimento,
        descricao_operadora: payload.descricao_operadora.trim() || null,
        quantidade_padrao: payload.quantidade_padrao ? Number(payload.quantidade_padrao) : 10,
        usa_descricao_generica: payload.usa_descricao_generica,
        valor_generico: payload.valor_generico.trim() || null,
        ativo: payload.ativo,
      }
      const request = id
        ? apiClient.patch<{ data: UnimedEspecialidadeMapeamento }>(
            `/configuracoes/unimed/mapeamentos/especialidades/${id}`,
            body,
          )
        : apiClient.post<{ data: UnimedEspecialidadeMapeamento }>(
            '/configuracoes/unimed/mapeamentos/especialidades',
            body,
          )
      const { data } = await request
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ['configuracoes', 'unimed', 'mapeamentos', 'especialidades'],
      })
      await queryClient.invalidateQueries({ queryKey: ['solicitacoes'] })
    },
  })
}

export function useUnimedProfissionalMapeamentos(convenioId?: string) {
  return useQuery({
    queryKey: ['configuracoes', 'unimed', 'mapeamentos', 'profissionais', convenioId ?? ''],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: UnimedProfissionalMapeamento[] }>(
        '/configuracoes/unimed/mapeamentos/profissionais',
        { params: { convenio_id: convenioId || undefined } },
      )
      return data.data
    },
  })
}

export function useSalvarUnimedProfissionalMapeamento() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({
      id,
      payload,
    }: {
      id?: number
      payload: UnimedProfissionalMapeamentoForm
    }) => {
      const body = {
        convenio_id: Number(payload.convenio_id),
        profissional_id: Number(payload.profissional_id),
        codigo_operadora: payload.codigo_operadora,
        nome_operadora: payload.nome_operadora.trim() || null,
        ativo: payload.ativo,
      }
      const request = id
        ? apiClient.patch<{ data: UnimedProfissionalMapeamento }>(
            `/configuracoes/unimed/mapeamentos/profissionais/${id}`,
            body,
          )
        : apiClient.post<{ data: UnimedProfissionalMapeamento }>(
            '/configuracoes/unimed/mapeamentos/profissionais',
            body,
          )
      const { data } = await request
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ['configuracoes', 'unimed', 'mapeamentos', 'profissionais'],
      })
    },
  })
}

export { getHttpErrorMessage }

import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'

export type ConvenioRef = {
  id: number
  nome: string
  connector_type: string
  connector_driver?: 'unimed_rda' | null
  carteirinha_blocos?: number[] | null
}

export type EspecialidadeRef = {
  id: number
  nome: string
  /** Preenchido apenas quando a listagem é pedida com convenio_id. */
  codigo_procedimento?: string | null
}

export type ProfissionalRef = {
  id: number
  nome: string
  /** Especialidade principal. Para saber onde ele atende, use especialidade_ids. */
  especialidade_id: number
  /** Todas em que atende, principal incluída. */
  especialidade_ids?: number[]
  conselho_registro: string | null
  percentual_repasse?: string | null
  ativo: boolean
  especialidade?: {
    id: number
    nome: string
  }
}

export type PacienteRef = {
  id: number
  nome: string
  carteirinha: string
  /** Vem calculado da API: a validade cadastrada já passou. */
  carteirinha_vencida?: boolean
  validade_carteirinha?: string | null
  convenio_id: number
  convenio?: {
    id: number
    nome: string
    connector_driver?: 'unimed_rda' | null
    carteirinha_blocos?: number[] | null
  }
}

export type MedicoRef = {
  id: number
  nome: string
  crm: string
  especialidade_medica: string
  telefone: string
  email: string | null
  ativo: boolean
}

export type CidRef = {
  id: number
  codigo: string
  descricao: string
  ativo: boolean
}

type ListResponse<T> = {
  data: T[]
}

export function useConvenios() {
  return useQuery({
    queryKey: ['convenios'],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<ConvenioRef>>('/convenios')
      return data.data
    },
  })
}

export function useEspecialidades(filtros?: { convenio_id?: string | number }) {
  return useQuery({
    queryKey: ['especialidades', filtros?.convenio_id ?? ''],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<EspecialidadeRef>>('/especialidades', {
        params: { convenio_id: filtros?.convenio_id || undefined },
      })
      return data.data
    },
  })
}

export function useCids() {
  return useQuery({
    queryKey: ['cids'],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<CidRef>>('/cids')
      return data.data
    },
  })
}

export function useProfissionais(filtros?: {
  busca?: string
  especialidade_id?: string | number
  incluir_inativos?: boolean
}) {
  return useQuery({
    queryKey: [
      'profissionais',
      filtros?.busca ?? '',
      filtros?.especialidade_id ?? '',
      filtros?.incluir_inativos ? '1' : '0',
    ],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<ProfissionalRef>>('/profissionais', {
        params: {
          busca: filtros?.busca || undefined,
          especialidade_id: filtros?.especialidade_id || undefined,
          incluir_inativos: filtros?.incluir_inativos || undefined,
        },
      })

      return data.data
    },
  })
}

export function usePacientes(filtros?: { busca?: string; convenio_id?: string | number }) {
  return useQuery({
    queryKey: ['pacientes', filtros?.busca ?? '', filtros?.convenio_id ?? ''],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<PacienteRef>>('/pacientes', {
        params: {
          busca: filtros?.busca || undefined,
          convenio_id: filtros?.convenio_id || undefined,
        },
      })

      return data.data
    },
  })
}

export function useMedicos() {
  return useQuery({
    queryKey: ['medicos'],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<MedicoRef>>('/medicos')
      return data.data
    },
  })
}

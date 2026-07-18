import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'

export type ConvenioRef = {
  id: number
  nome: string
  connector_type: string
}

export type EspecialidadeRef = {
  id: number
  nome: string
}

export type ProfissionalRef = {
  id: number
  nome: string
  especialidade_id: number
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
  convenio_id: number
  convenio?: {
    id: number
    nome: string
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

export function useEspecialidades() {
  return useQuery({
    queryKey: ['especialidades'],
    queryFn: async () => {
      const { data } = await apiClient.get<ListResponse<EspecialidadeRef>>('/especialidades')
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

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'

type PacienteDuplicadoResumo = {
  id: number
  nome: string
  cpf: string | null
  carteirinha: string | null
  convenio: string | null
  clinica_id: number | null
  vinculado_clinica: boolean
  ativo: boolean
}

export type PacienteDuplicado = {
  paciente_a: PacienteDuplicadoResumo
  paciente_b: PacienteDuplicadoResumo
  similaridade: number
}

export type PreviewMesclagem = {
  solicitacoes: number
  guias: number
  antecipacoes: number
  telefones: number
  documentos: number
  arquivos: number
  conflito_clinica_id: boolean
}

export type MesclarInput = {
  vencedor_id: number
  perdedor_id: number
  clinica_id_escolhido?: number
}

const chaveDuplicados = ['pacientes', 'duplicados']

export function usePacientesDuplicados(habilitado: boolean) {
  return useQuery({
    queryKey: chaveDuplicados,
    queryFn: async () => {
      const { data } = await apiClient.get<PacienteDuplicado[]>('/pacientes/duplicados')
      return data
    },
    enabled: habilitado,
  })
}

export function usePreviewMesclagem() {
  return useMutation({
    mutationFn: async (input: MesclarInput) => {
      const { data } = await apiClient.post<PreviewMesclagem>('/pacientes/duplicados/preview', input)
      return data
    },
  })
}

export function useMesclarPacientes() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (input: MesclarInput) => {
      const { data } = await apiClient.post('/pacientes/duplicados/mesclar', input)
      return data
    },
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: chaveDuplicados }),
        queryClient.invalidateQueries({ queryKey: ['pacientes'] }),
      ])
    },
  })
}

export { getHttpErrorMessage }

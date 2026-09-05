import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'

export type ClinicaPacienteCandidato = {
  id: number
  nome: string
  cpf: string | null
  carteirinha: string | null
  convenio: string | null
  similaridade: number
}

export type ClinicaPacientePendencia = {
  id: number
  clinica_id: number
  nome_remoto: string | null
  cpf_remoto: string | null
  candidatos: ClinicaPacienteCandidato[]
}

const chavePendencias = ['configuracoes', 'clinica-sync', 'pendencias']

export function useClinicaSyncPendencias() {
  return useQuery({
    queryKey: chavePendencias,
    queryFn: async () => {
      const { data } = await apiClient.get<ClinicaPacientePendencia[]>('/configuracoes/clinica-sync/pendencias')
      return data
    },
  })
}

export function useConfirmarPendencia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ pendenciaId, pacienteId }: { pendenciaId: number; pacienteId: number }) => {
      const { data } = await apiClient.post(`/configuracoes/clinica-sync/pendencias/${pendenciaId}/confirmar`, {
        paciente_id: pacienteId,
      })
      return data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chavePendencias })
    },
  })
}

export function useRejeitarPendencia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (pendenciaId: number) => {
      const { data } = await apiClient.post(`/configuracoes/clinica-sync/pendencias/${pendenciaId}/rejeitar`)
      return data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chavePendencias })
    },
  })
}

export { getHttpErrorMessage }

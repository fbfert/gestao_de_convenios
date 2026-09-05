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

export type ClinicaPushCandidato = {
  clinica_id: number
  nome: string
  similaridade: number
}

export type ClinicaPushPendencia = {
  id: number
  tipo: 'paciente' | 'profissional'
  local_id: number
  nome_local: string | null
  candidatos: ClinicaPushCandidato[]
}

const chavePendencias = ['configuracoes', 'clinica-sync', 'pendencias']
const chavePushPendencias = ['configuracoes', 'clinica-sync', 'push-pendencias']

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

export function useClinicaSyncPushPendencias() {
  return useQuery({
    queryKey: chavePushPendencias,
    queryFn: async () => {
      const { data } = await apiClient.get<ClinicaPushPendencia[]>('/configuracoes/clinica-sync/push-pendencias')
      return data
    },
  })
}

export function useConfirmarPushPendencia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ pendenciaId, clinicaIdEscolhido }: { pendenciaId: number; clinicaIdEscolhido: number }) => {
      const { data } = await apiClient.post(`/configuracoes/clinica-sync/push-pendencias/${pendenciaId}/confirmar`, {
        clinica_id_escolhido: clinicaIdEscolhido,
      })
      return data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chavePushPendencias })
    },
  })
}

export function useRejeitarPushPendencia() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (pendenciaId: number) => {
      const { data } = await apiClient.post(`/configuracoes/clinica-sync/push-pendencias/${pendenciaId}/rejeitar`)
      return data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chavePushPendencias })
    },
  })
}

export { getHttpErrorMessage }

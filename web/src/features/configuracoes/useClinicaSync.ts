import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'

export type ClinicaSyncResumoEntidade = {
  criados: number
  atualizados: number
  ignorados?: number
  pendentes: string[]
}

export type ClinicaSyncExecucao = {
  origem: 'agendado' | 'manual'
  status: 'ok' | 'error'
  iniciado_em: string
  finalizado_em: string | null
  resumo: {
    profissionais: { pull: ClinicaSyncResumoEntidade; push: ClinicaSyncResumoEntidade }
    pacientes: { pull: ClinicaSyncResumoEntidade; push: ClinicaSyncResumoEntidade }
  } | null
  erro_mensagem: string | null
}

export type ClinicaSyncStatus = {
  configurado: boolean
  base_url: string | null
  ultima_execucao: ClinicaSyncExecucao | null
}

const chaveQuery = ['configuracoes', 'clinica-sync']

export function useClinicaSyncStatus() {
  return useQuery({
    queryKey: chaveQuery,
    queryFn: async () => {
      const { data } = await apiClient.get<ClinicaSyncStatus>('/configuracoes/clinica-sync')
      return data
    },
    // O botão já dá feedback imediato do resultado; refetch em intervalo mantém a
    // tela atual se alguém mais rodou (ou o agendamento de 5 em 5 min disparou).
    refetchInterval: 60_000,
  })
}

export function useSincronizarClinicaAgora() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post<ClinicaSyncExecucao>('/configuracoes/clinica-sync/sincronizar')
      return data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chaveQuery })
    },
  })
}

export { getHttpErrorMessage }

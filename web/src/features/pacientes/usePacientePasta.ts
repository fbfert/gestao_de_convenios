import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import type { Paciente } from './types'
import type { DocumentoTipo } from '../../lib/documentoTipos'

export type PastaSolicitacaoItem = {
  id: number
  especialidade: string | null
  profissional: string | null
  quantidade: number | null
  status_operacional: string | null
}

export type PastaSolicitacao = {
  id: number
  status: string
  solicitado_em: string | null
  convenio: string | null
  medico: string | null
  itens: PastaSolicitacaoItem[]
}

export type PastaGuia = {
  id: number
  numero_guia: string | null
  status: string
  convenio: string | null
  especialidade: string | null
  profissional: string | null
  senha: string | null
  validade_senha: string | null
  sessoes_autorizadas: number | null
  lancamentos_count: number
  sessoes_disponiveis: number
}

export type PastaSessao = {
  id: number
  data_sessao: string | null
  hora_inicio: string | null
  hora_fim: string | null
  profissional: string | null
  guia_id: number
  numero_guia: string | null
  status: string | null
}

export type PastaAntecipacao = {
  id: number
  status: string
  solicitacao_origem_id: number
  convenio: string | null
  itens_gerados: number
  criado_por: string | null
  created_at: string | null
}

export type PastaArquivo = {
  id: number
  tipo: DocumentoTipo | string
  nome_original: string
  mime: string | null
  metadata: Record<string, unknown> | null
  created_at: string | null
}

export type PacientePasta = {
  paciente: Paciente
  solicitacoes: PastaSolicitacao[]
  guias: PastaGuia[]
  sessoes: PastaSessao[]
  antecipacoes: PastaAntecipacao[]
  arquivos: PastaArquivo[]
}

/**
 * A pasta inteira numa requisição.
 *
 * As seções abrem recolhidas mostrando a contagem, então a tela precisa dos
 * totais antes de qualquer expansão — buscar cada lista sob demanda deixaria
 * os cabeçalhos sem número até alguém clicar.
 */
export function usePacientePasta(id: number | null) {
  return useQuery({
    queryKey: ['paciente-pasta', id],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: PacientePasta }>(`/pacientes/${id}/pasta`)
      return data.data
    },
    enabled: id !== null,
  })
}

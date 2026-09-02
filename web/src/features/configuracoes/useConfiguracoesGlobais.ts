import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { getHttpErrorMessage } from '../../lib/httpError'

export type ConfiguracoesGlobais = {
  /** Minutos que um login vale, contados da emissão do token. 0 = sem expirar. */
  sessao_minutos: number
  senha_alerta_dias: number
  sessoes_padrao: number
  itens_por_pagina: number
  /** Meses que a trilha de auditoria e mantida antes do expurgo diario. */
  auditoria_retencao_meses: number
  /** Dias que a imagem da carteirinha fica no servidor antes do expurgo. */
  carteirinha_retencao_dias: number
  /** Horas até a próxima consulta de status Unimed quando a anterior teve sucesso (sem novidade). */
  unimed_recheck_horas_sucesso: number
  /** Horas até a próxima consulta de status Unimed quando a anterior falhou por erro técnico. */
  unimed_recheck_horas_falha: number
  /** Minutos entre tentativas de confirmar uma guia incerta pós-submit (busca por paciente). */
  unimed_verificacao_incerta_intervalo_minutos: number
  /** Horário (HH:MM) de início da janela em que a confirmação de guia incerta pode rodar. */
  unimed_verificacao_incerta_horario_inicio: string
  /** Horário (HH:MM) de fim da janela em que a confirmação de guia incerta pode rodar. */
  unimed_verificacao_incerta_horario_fim: string
  /** Liga/desliga a reconsulta automática de status Unimed (o tick de 30 min continua rodando, só pula esta automação). */
  automacao_reconsulta_status_ativo: boolean
  /** Liga/desliga a busca automática de senha/validade para guias Unimed aprovadas. */
  automacao_captura_senha_validade_ativo: boolean
  /** Liga/desliga a confirmação automática de guia incerta pós-submit. */
  automacao_verificacao_incerta_ativo: boolean
  /** Liga/desliga a sincronização agendada com a clínica ("Sincronizar Agora" continua disponível). */
  automacao_sincronizacao_clinica_ativo: boolean
  /** Minutos entre sincronizações automáticas com a clínica. */
  automacao_sincronizacao_clinica_intervalo_minutos: number
  /** Liga/desliga o expurgo diário da trilha de auditoria. */
  automacao_expurgo_auditoria_ativo: boolean
  /** Liga/desliga o expurgo diário de imagens de carteirinha vencidas. */
  automacao_expurgo_carteirinhas_ativo: boolean
  /** Liga/desliga a verificação diária de guias em análise em convênios não-Unimed. */
  automacao_verificacao_guias_diaria_ativo: boolean
}

export type ConfiguracoesGlobaisForm = {
  sessao_minutos: string
  senha_alerta_dias: string
  sessoes_padrao: string
  itens_por_pagina: string
  auditoria_retencao_meses: string
  carteirinha_retencao_dias: string
  unimed_recheck_horas_sucesso: string
  unimed_recheck_horas_falha: string
  unimed_verificacao_incerta_intervalo_minutos: string
  unimed_verificacao_incerta_horario_inicio: string
  unimed_verificacao_incerta_horario_fim: string
  automacao_reconsulta_status_ativo: boolean
  automacao_captura_senha_validade_ativo: boolean
  automacao_verificacao_incerta_ativo: boolean
  automacao_sincronizacao_clinica_ativo: boolean
  automacao_sincronizacao_clinica_intervalo_minutos: string
  automacao_expurgo_auditoria_ativo: boolean
  automacao_expurgo_carteirinhas_ativo: boolean
  automacao_verificacao_guias_diaria_ativo: boolean
}

const chaveQuery = ['configuracoes', 'globais']

export function paraFormulario(dados: ConfiguracoesGlobais): ConfiguracoesGlobaisForm {
  return {
    sessao_minutos: String(dados.sessao_minutos),
    senha_alerta_dias: String(dados.senha_alerta_dias),
    sessoes_padrao: String(dados.sessoes_padrao),
    itens_por_pagina: String(dados.itens_por_pagina),
    auditoria_retencao_meses: String(dados.auditoria_retencao_meses),
    carteirinha_retencao_dias: String(dados.carteirinha_retencao_dias),
    unimed_recheck_horas_sucesso: String(dados.unimed_recheck_horas_sucesso),
    unimed_recheck_horas_falha: String(dados.unimed_recheck_horas_falha),
    unimed_verificacao_incerta_intervalo_minutos: String(dados.unimed_verificacao_incerta_intervalo_minutos),
    unimed_verificacao_incerta_horario_inicio: dados.unimed_verificacao_incerta_horario_inicio,
    unimed_verificacao_incerta_horario_fim: dados.unimed_verificacao_incerta_horario_fim,
    automacao_reconsulta_status_ativo: dados.automacao_reconsulta_status_ativo,
    automacao_captura_senha_validade_ativo: dados.automacao_captura_senha_validade_ativo,
    automacao_verificacao_incerta_ativo: dados.automacao_verificacao_incerta_ativo,
    automacao_sincronizacao_clinica_ativo: dados.automacao_sincronizacao_clinica_ativo,
    automacao_sincronizacao_clinica_intervalo_minutos: String(dados.automacao_sincronizacao_clinica_intervalo_minutos),
    automacao_expurgo_auditoria_ativo: dados.automacao_expurgo_auditoria_ativo,
    automacao_expurgo_carteirinhas_ativo: dados.automacao_expurgo_carteirinhas_ativo,
    automacao_verificacao_guias_diaria_ativo: dados.automacao_verificacao_guias_diaria_ativo,
  }
}

/** Ex.: 480 → "8 h". Ajuda a conferir um número em minutos sem fazer conta. */
export function descreverMinutos(minutos: number): string {
  if (!Number.isFinite(minutos) || minutos <= 0) {
    return 'sem expiração'
  }

  if (minutos < 60) {
    return `${minutos} min`
  }

  const horas = Math.floor(minutos / 60)
  const resto = minutos % 60

  return resto === 0 ? `${horas} h` : `${horas} h ${resto} min`
}

export function useConfiguracoesGlobais() {
  return useQuery({
    queryKey: chaveQuery,
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: ConfiguracoesGlobais }>('/configuracoes/globais')
      return data.data
    },
  })
}

export function useSalvarConfiguracoesGlobais() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (form: ConfiguracoesGlobaisForm) => {
      const { data } = await apiClient.put<{ data: ConfiguracoesGlobais }>('/configuracoes/globais', {
        sessao_minutos: Number(form.sessao_minutos),
        senha_alerta_dias: Number(form.senha_alerta_dias),
        sessoes_padrao: Number(form.sessoes_padrao),
        itens_por_pagina: Number(form.itens_por_pagina),
        auditoria_retencao_meses: Number(form.auditoria_retencao_meses),
        carteirinha_retencao_dias: Number(form.carteirinha_retencao_dias),
        unimed_recheck_horas_sucesso: Number(form.unimed_recheck_horas_sucesso),
        unimed_recheck_horas_falha: Number(form.unimed_recheck_horas_falha),
        unimed_verificacao_incerta_intervalo_minutos: Number(form.unimed_verificacao_incerta_intervalo_minutos),
        unimed_verificacao_incerta_horario_inicio: form.unimed_verificacao_incerta_horario_inicio,
        unimed_verificacao_incerta_horario_fim: form.unimed_verificacao_incerta_horario_fim,
        automacao_reconsulta_status_ativo: form.automacao_reconsulta_status_ativo,
        automacao_captura_senha_validade_ativo: form.automacao_captura_senha_validade_ativo,
        automacao_verificacao_incerta_ativo: form.automacao_verificacao_incerta_ativo,
        automacao_sincronizacao_clinica_ativo: form.automacao_sincronizacao_clinica_ativo,
        automacao_sincronizacao_clinica_intervalo_minutos: Number(form.automacao_sincronizacao_clinica_intervalo_minutos),
        automacao_expurgo_auditoria_ativo: form.automacao_expurgo_auditoria_ativo,
        automacao_expurgo_carteirinhas_ativo: form.automacao_expurgo_carteirinhas_ativo,
        automacao_verificacao_guias_diaria_ativo: form.automacao_verificacao_guias_diaria_ativo,
      })
      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chaveQuery })
    },
  })
}

export { getHttpErrorMessage }

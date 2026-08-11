import type { EspecialidadeRef } from '../../lib/queries/useReferenceData'
import type { SolicitacaoFormItem } from './types'

export const emptyItem: SolicitacaoFormItem = {
  especialidade_id: '',
  profissional_id: '',
  quantidade: '10',
}

export function itensEstaoCompletos(itens: SolicitacaoFormItem[]): boolean {
  return (
    itens.length > 0 &&
    itens.every(
      (item) =>
        item.especialidade_id !== '' && item.profissional_id !== '' && item.quantidade !== '',
    )
  )
}

/** Especialidades repetidas gerariam duas guias iguais na operadora — avisa sem bloquear. */
export function especialidadesRepetidas(itens: SolicitacaoFormItem[]): boolean {
  const preenchidas = itens.map((item) => item.especialidade_id).filter(Boolean)

  return new Set(preenchidas).size !== preenchidas.length
}

export function rotuloEspecialidade(especialidade: EspecialidadeRef): string {
  return especialidade.codigo_procedimento
    ? `${especialidade.nome} · ${especialidade.codigo_procedimento}`
    : especialidade.nome
}

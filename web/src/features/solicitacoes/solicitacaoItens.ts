import type { EspecialidadeRef } from '../../lib/queries/useReferenceData'
import type { SolicitacaoFormItem } from './types'

/**
 * Item em branco — inclusive a quantidade.
 *
 * Era `'10'`, que é o limite da Unimed escrito num arquivo de front: regra de
 * convênio é dado configurável (`openspec/config.yaml`), e o número vem de
 * `convenio.sessoes_por_guia` da regra vigente. Sem regra cadastrada o campo
 * fica vazio mesmo, e quem preenche é a pessoa — a API recusa em vez de
 * arbitrar, então mostrar um número aqui daria erro no salvamento.
 */
export const emptyItem: SolicitacaoFormItem = {
  especialidade_id: '',
  profissional_id: '',
  quantidade: '',
}

/** Item novo já com a quantidade da regra do convênio, quando ela existe. */
export function itemComPadraoDoConvenio(sessoesPorGuia: number | null | undefined): SolicitacaoFormItem {
  return { ...emptyItem, quantidade: sessoesPorGuia ? String(sessoesPorGuia) : '' }
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

/**
 * "2ª remessa" para item de renovação; vazio para a origem.
 *
 * Sem isto a tela mostra duas linhas idênticas — mesma especialidade, mesmo
 * profissional — e ninguém entende por que são duas. A posição vem do
 * `posicao_na_cadeia` do resource, calculado no servidor sobre o vínculo, e não
 * de contar linhas parecidas aqui.
 */
export function rotuloDaRemessa(item: {
  posicao_na_cadeia?: number
  renovacao_de_item_id?: number | null
}): string | null {
  const posicao = item.posicao_na_cadeia ?? 1

  if (posicao <= 1 && !item.renovacao_de_item_id) {
    return null
  }

  return `${posicao}ª remessa`
}

export function rotuloEspecialidade(especialidade: EspecialidadeRef): string {
  return especialidade.codigo_procedimento
    ? `${especialidade.nome} · ${especialidade.codigo_procedimento}`
    : especialidade.nome
}

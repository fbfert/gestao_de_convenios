import { expect, test } from '@playwright/test'
import {
  conferirPacienteDaFolha,
  descreverDivergencia,
} from '../../src/features/lancamentos/conferenciaDaFolha'

/**
 * A comparação entre a folha lida e a guia escolhida, sem navegador.
 *
 * Vive aqui, e não num runner de unidade, porque o projeto não tem um — e o
 * Playwright roda um teste que não toca em `page` como qualquer outro. O que
 * importa é a regra estar coberta: ela decide se as sessões vão para a cota
 * certa, e o alarme falso é tão danoso quanto o silêncio (um aviso que dispara
 * à toa ensina a clicar sem ler).
 */

const guiaDe = (nome: string, carteirinha: string | null) => ({
  paciente: { nome, carteirinha },
})

test.describe('o que NÃO é divergência', () => {
  test('cartão idêntico, com formatação diferente', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: null, numero_cartao: '0220 090000 551.330-8' },
      guiaDe('Ana Paula Ribeiro', '02200900005513308'),
    )

    expect(resultado.confere).toBe(true)
  })

  test('cartão lido pela metade — leitura parcial não é contradição', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: null, numero_cartao: '090000 551330' },
      guiaDe('Ana Paula Ribeiro', '02200900005513308'),
    )

    expect(resultado.confere).toBe(true)
  })

  test('nome abreviado e sem o nome do meio', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: 'ANA P. RIBEIRO', numero_cartao: null },
      guiaDe('Ana Paula Ribeiro', null),
    )

    expect(resultado.confere).toBe(true)
  })

  /**
   * O caso que quebrou o `mvp-flow` e motivou a regra atual.
   *
   * A folha traz o cartão impresso da operadora; o cadastro tem uma
   * carteirinha de outro formato — paciente migrado de sistema antigo, número
   * reemitido, ou placeholder herdado. O nome conferindo é confirmação
   * suficiente: exigir que o cartão também feche acusaria metade da base.
   */
  test('nome confere e cartao nao — qualidade de dado, nao troca de paciente', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: 'Ana Paula Ribeiro', numero_cartao: '0220 090000 551.330-8' },
      guiaDe('Ana Paula Ribeiro', 'UNI-2026-0001'),
    )

    expect(resultado.confere).toBe(true)
  })

  test('acento e caixa diferentes', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: 'jose antonio de souza', numero_cartao: null },
      guiaDe('José Antônio de Souza', null),
    )

    expect(resultado.confere).toBe(true)
  })

  test('sobrenome de casada acrescentado — dois pedaços ainda coincidem', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: 'Maria Silva', numero_cartao: null },
      guiaDe('Maria Silva Andrade', null),
    )

    expect(resultado.confere).toBe(true)
  })

  test('o cartao confere e o nome nao — paciente que trocou de nome', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: 'Nome Totalmente Diferente', numero_cartao: '02200900005513308' },
      guiaDe('Ana Paula Ribeiro', '02200900005513308'),
    )

    expect(resultado.confere).toBe(true)
  })

  test('folha sem nome e sem cartão: nada a conferir', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: null, numero_cartao: null },
      guiaDe('Ana Paula Ribeiro', '02200900005513308'),
    )

    expect(resultado.confere).toBe(true)
    expect(resultado.campo).toBeNull()
  })

  test('sem guia escolhida ainda: nada a conferir', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: 'Ana Paula Ribeiro', numero_cartao: '02200900005513308' },
      null,
    )

    expect(resultado.confere).toBe(true)
  })

  test('cartão curto demais não decide nada, nem para acusar', () => {
    // Menos de 6 dígitos: bater ou não bater é coincidência, então cai no nome.
    const resultado = conferirPacienteDaFolha(
      { paciente: 'Ana Ribeiro', numero_cartao: '1234' },
      guiaDe('Ana Paula Ribeiro', '9999'),
    )

    expect(resultado.confere).toBe(true)
    expect(resultado.campo).toBeNull()
  })
})

test.describe('o que É divergência', () => {
  test('cartões diferentes, ambos completos', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: null, numero_cartao: '0220 090000 551.330-8' },
      guiaDe('Bruno Henrique Lima', '02200900007778889'),
    )

    expect(resultado.confere).toBe(false)
    expect(resultado.campo).toBe('carteirinha')
  })

  test('nomes sem nada em comum', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: 'Ana Paula Ribeiro', numero_cartao: null },
      guiaDe('Bruno Henrique Lima', null),
    )

    expect(resultado.confere).toBe(false)
    expect(resultado.campo).toBe('nome')
  })

  test('só o primeiro nome em comum não confirma', () => {
    // Uma clínica tem muitas Marias: um pedaço coincidindo é coincidência, não
    // confirmação. É o piso que separa "mesma pessoa" de "nome parecido".
    const resultado = conferirPacienteDaFolha(
      { paciente: 'Maria Silva', numero_cartao: null },
      guiaDe('Maria Santos', null),
    )

    expect(resultado.confere).toBe(false)
    expect(resultado.campo).toBe('nome')
  })

  test('o caso perigoso: guia de outra pessoa, nada confirma', () => {
    // A assinatura de um dígito lido errado no número da guia — cai numa guia
    // real, de outro paciente, e nem nome nem cartão confirmam.
    const resultado = conferirPacienteDaFolha(
      { paciente: 'Zoroastro Buarque de Holanda', numero_cartao: '99999999999999999' },
      guiaDe('Ana Paula Ribeiro', '02200900005513308'),
    )

    expect(resultado.confere).toBe(false)
    expect(resultado.campo).toBe('carteirinha')
  })

  /**
   * O preço consciente da regra "qualquer confirmação basta".
   *
   * Dois pacientes de nome igual e cartões diferentes passam sem aviso. É mais
   * raro do que o ruído de cadastro que a regra evita, e a alternativa era
   * pior: um aviso que dispara toda hora ensina a clicar sem ler. Este teste
   * existe para que a escolha fique explícita — se um dia ela mudar, que seja
   * de propósito.
   */
  test('homonimo com cartoes diferentes NAO e acusado — limitacao conhecida', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: 'Ana Paula Ribeiro', numero_cartao: '02200900005513308' },
      guiaDe('Ana Paula Ribeiro', '02200900001112223'),
    )

    expect(resultado.confere).toBe(true)
  })

  test('a descrição nomeia os dois lados', () => {
    const resultado = conferirPacienteDaFolha(
      { paciente: 'Ana Paula Ribeiro', numero_cartao: null },
      guiaDe('Bruno Henrique Lima', null),
    )

    const frase = descreverDivergencia(resultado)

    expect(frase).toContain('Ana Paula Ribeiro')
    expect(frase).toContain('Bruno Henrique Lima')
  })
})

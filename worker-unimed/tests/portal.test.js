import assert from 'node:assert/strict'
import { test } from 'node:test'
import { compararNomes, loginUrlFromCredential, stripPrefixoDr } from '../src/portal.js'

test('stripPrefixoDr remove prefixo em todas as variacoes pedidas', () => {
  assert.equal(stripPrefixoDr('Dr. Carlos Almeida'), 'Carlos Almeida')
  assert.equal(stripPrefixoDr('Dr Carlos Almeida'), 'Carlos Almeida')
  assert.equal(stripPrefixoDr('Dra. Helena Soares'), 'Helena Soares')
  assert.equal(stripPrefixoDr('Dra Helena Soares'), 'Helena Soares')
  assert.equal(stripPrefixoDr('DRA. HELENA SOARES'), 'HELENA SOARES')
})

test('stripPrefixoDr nao corta nome que comeca com essas letras por coincidencia', () => {
  assert.equal(stripPrefixoDr('Drica Fernandes'), 'Drica Fernandes')
})

test('compararNomes: nome identico pontua 100', () => {
  assert.equal(compararNomes('Carlos Almeida', 'CARLOS ALMEIDA'), 100)
})

test('compararNomes: nome abreviado com iniciais do meio pontua acima do limiar de auto-aceite', () => {
  const score = compararNomes('Edison T. F. A. Westarb', 'EDISON TEODORO FERREIRA DE ANDRADE WESTARB')
  assert.ok(score >= 90, `esperava >= 90, recebeu ${score}`)
})

test('compararNomes: sobrenome diferente nunca pontua alto', () => {
  const score = compararNomes('Carlos Almeida', 'CARLOS PEREIRA')
  assert.equal(score, 0)
})

test('compararNomes: nome do meio nao verificavel fica na faixa ambigua', () => {
  const score = compararNomes('Carlos Eduardo Almeida', 'CARLOS ALMEIDA')
  assert.ok(score >= 60 && score < 90, `esperava entre 60 e 89, recebeu ${score}`)
})

test('compararNomes: sem tokens do meio no nome lido pontua 100 direto', () => {
  assert.equal(compararNomes('Edison Westarb', 'EDISON TEODORO WESTARB'), 100)
})

test('compararNomes: conector ("da") no meio do nome lido nao derruba o score quando so difere no acento', () => {
  // Achado ao vivo em 14/09/2026, item 2414: score vinha 80 em vez de 100
  // porque so o "DA" do candidato era filtrado como conector, nao o do
  // nome lido — sobrava um "DA" sem par no lado lido.
  assert.equal(compararNomes('Volnei Corrêa da Silva', 'VOLNEI CORREA DA SILVA'), 100)
})

/*
 * `base_url` decide para onde o Playwright navega ANTES de digitar login e
 * senha reais do portal. Sem allowlist, quem grava esse campo na tela de
 * configuracoes faz o worker digitar a senha da clinica num formulario
 * hospedado pelo atacante — contornando o fato de a senha ser write-only na
 * API, sem nunca precisar le-la.
 */
test('loginUrlFromCredential recusa host fora da allowlist', () => {
  assert.throws(
    () => loginUrlFromCredential({ base_url: 'https://evil.tld/cmagnet/Login.do' }),
    /fora da lista permitida/,
  )
})

test('loginUrlFromCredential recusa http sem tls', () => {
  assert.throws(
    () => loginUrlFromCredential({ base_url: 'http://rda.unimedsc.com.br/cmagnet/Login.do' }),
    /exige https/,
  )
})

test('loginUrlFromCredential recusa file: sem a flag de fixtures', () => {
  const anterior = process.env.UNIMED_PERMITIR_FIXTURES_LOCAIS
  delete process.env.UNIMED_PERMITIR_FIXTURES_LOCAIS

  try {
    assert.throws(
      () => loginUrlFromCredential({ base_url: 'file:///tmp/fake-portal.html' }),
      /esquema file:/,
    )
  } finally {
    if (anterior !== undefined) {
      process.env.UNIMED_PERMITIR_FIXTURES_LOCAIS = anterior
    }
  }
})

test('loginUrlFromCredential aceita o portal real e completa o caminho', () => {
  assert.equal(
    loginUrlFromCredential({ base_url: 'https://rda.unimedsc.com.br' }),
    'https://rda.unimedsc.com.br/cmagnet/Login.do',
  )
})

test('loginUrlFromCredential sem base_url cai no portal padrao', () => {
  assert.equal(loginUrlFromCredential({}), 'https://rda.unimedsc.com.br/cmagnet/Login.do')
})

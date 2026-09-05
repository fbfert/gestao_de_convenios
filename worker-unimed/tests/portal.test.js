import assert from 'node:assert/strict'
import { test } from 'node:test'
import { compararNomes, stripPrefixoDr } from '../src/portal.js'

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

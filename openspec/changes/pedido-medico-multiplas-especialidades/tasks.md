## 1. Contrato da leitura

- [x] 1.1 Trocar `especialidade_nome` por `especialidades` (lista) no contrato de saída.
- [x] 1.2 Aceitar a chave antiga no singular, para prompt editado e modelo que ignore a instrução.
- [x] 1.3 Deduplicar os termos lidos, sem diferenciar maiúsculas.

## 2. Sugestões por termo

- [x] 2.1 Agrupar as sugestões por termo lido, com os cadastros parecidos de cada um.
- [x] 2.2 Marcar `sugere_cadastro` quando nenhum cadastro atingir o corte de confiança.
- [x] 2.3 Documentar o corte no código, com o caso Psicopedagogia/Psicologia.

## 3. Tela

- [x] 3.1 Criar uma linha de item por especialidade que casou com confiança.
- [x] 3.2 Mostrar um bloco por termo lido, com os palpites e o botão de cadastrar.
- [x] 3.3 Acrescentar a especialidade criada em vez de substituir a primeira linha.
- [x] 3.4 Mover o bloco do médico solicitante para antes das especialidades.
- [x] 3.5 Carregar os modelos disponíveis quando o editor de prompt abre — o datalist nunca era preenchido.

## 4. Validação

- [x] 4.1 Teste de leitura com três especialidades, uma delas sem cadastro.
- [x] 4.2 Teste de compatibilidade com a chave antiga no singular.
- [x] 4.3 Suíte da API e `tsc`/`oxlint` do web.
- [ ] 4.4 Conferência com um pedido real multidisciplinar, ponta a ponta.

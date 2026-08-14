## 1. API

- [x] 1.1 `EspecialidadeResource` devolve a lista de códigos por convênio quando pedida.
- [x] 1.2 `GET /especialidades?com_codigos=1` carrega os mapeamentos de todos os convênios.
- [x] 1.3 Criação e edição aceitam `codigos[]`, com o convênio validado por tenant.
- [x] 1.4 Sincronização atualiza só o `codigo_procedimento`, preservando o que a tela da Unimed configura no mesmo registro.
- [x] 1.5 Código em branco remove o mapeamento — a coluna não aceita nulo.

## 2. Tela

- [x] 2.1 Um campo por convênio no formulário da especialidade.
- [x] 2.2 Listagem mostra os códigos já preenchidos.

## 3. Carga inicial

- [x] 3.1 Códigos da Unimed (7 especialidades) e da SC Saúde (4) carregados em produção, conferindo nome a nome.
- [ ] 3.2 Preencher o que ficou em branco: Fonoaudiologia (sem ABA) em qualquer convênio; Celos e Particular em todas; Psicopedagogia ABA, Fisioterapia ABA e Nutricionista na SC Saúde.

## 4. Validação

- [x] 4.1 Testes de API: gravação, leitura com `com_codigos`, remoção por código em branco e preservação do ajuste da Unimed.
- [x] 4.2 `openspec validate`, `tsc -b`, `oxlint`, `vite build` e `php artisan test`.

## 1. Armazenamento

- [x] 1.1 Migration `2026_08_12_220000`: tabela `configuracoes_globais`, uma linha por tenant, com os padrões atuais.
- [x] 1.2 Model com `doTenant()`, que cria a linha na primeira leitura.

## 2. Tempo de sessão

- [x] 2.1 Middleware `EncerrarSessaoExpirada`, aplicado logo após o `auth:sanctum`.
- [x] 2.2 Contagem a partir da emissão do token, com o motivo documentado no código.
- [x] 2.3 Apagar o token expirado e responder 401 com mensagem própria.
- [x] 2.4 Tratar `0` como expiração desligada e tolerar o token falso de teste.

## 3. API e tela

- [x] 3.1 `GET` e `PUT /configuracoes/globais` sob `configuracoes.manage`.
- [x] 3.2 Validação com faixas por parâmetro.
- [x] 3.3 Tela com o tempo de sessão em destaque, atalhos de tempo e a conversão para horas.
- [x] 3.4 Entrada no submenu de Configurações e rota; o card na tela de entrada sai da mesma lista.

## 4. Validação

- [x] 4.1 Teste dos padrões devolvidos sem registro prévio.
- [x] 4.2 Teste das faixas de validação.
- [x] 4.3 Teste da expiração real do token, com o relógio adiantado, e da opção `0`.
- [x] 4.4 Verificação dos endpoints em produção.
- [ ] 4.5 Fazer as telas lerem `senha_alerta_dias`, `sessoes_padrao` e `itens_por_pagina` em vez dos valores fixos.
- [ ] 4.6 Avisar na tela quando a sessão estiver perto de expirar.

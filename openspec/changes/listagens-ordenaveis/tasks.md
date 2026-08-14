## 1. Base

- [x] 1.1 `App\Support\OrdenaListagem`: mapa fechado de colunas, entrada podendo ser coluna real ou função para o caso com `join`, e desempate por coluna estável.
- [x] 1.2 `ColunaOrdenavel` no frontend, com seta de sentido; sem coluna, vira `th` comum.
- [x] 1.3 `useOrdenacao` em arquivo próprio — hook e componente no mesmo módulo quebram o fast refresh.

## 2. Listagens

- [x] 2.1 Guias: nº da guia, paciente, especialidade, profissional, status, sessões solicitadas e autorizadas, senha e validade.
- [x] 2.2 Solicitações: id, paciente, convênio, status e médico solicitante.
- [x] 2.3 Sessões: id, antecipação, profissional, data, acompanhante e status.
- [x] 2.4 Antecipações: id, paciente, convênio, cota e status.
- [x] 2.5 Conciliações: id, profissional, quantidade, valores e status.
- [x] 2.6 Médicos, Profissionais, Especialidades e Usuários.
- [x] 2.7 Analíticos: arquivo, importado em, linhas e status.

## 3. Validação

- [x] 3.1 Teste passando por todas as listagens nos dois sentidos.
- [x] 3.2 Teste enviando `(select 1)` e `nome; drop table users` como coluna, confirmando que caem no padrão.
- [x] 3.3 `openspec validate`, `tsc -b`, `oxlint`, `vite build` e `php artisan test`.

## 4. Pendências conhecidas

- [ ] 4.1 Convênio em Conciliações e Especialidade em Profissionais ainda não ordenam: exigem `join` que não foi feito.

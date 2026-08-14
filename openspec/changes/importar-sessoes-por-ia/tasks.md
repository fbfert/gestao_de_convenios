## 1. API

- [x] 1.1 `RegistroSessoesAiService` lendo foto ou PDF com o prompt `ler_sessoes_escaneadas`, devolvendo o mesmo formato do parser de texto.
- [x] 1.2 `POST /antecipacoes/{antecipacao}/lancamentos/ler-registro`, sem gravar nada.
- [x] 1.3 Descartar linha sem data e sem horário.
- [x] 1.4 `AntecipacaoResource` com nome do paciente, convênio e especialidade; `AntecipacaoService` carregando `guia.especialidade`.

## 2. Tela

- [x] 2.1 `ImportarSessoesPage` em `/lancamentos/importar`, com botão ao lado de Templates.
- [x] 2.2 Painel de importação sai da listagem.
- [x] 2.3 Seletor de antecipação com paciente, especialidade e saldo.
- [x] 2.4 Executante filtrado pela especialidade e pré-selecionado quando único.
- [x] 2.5 Aviso de leitura em andamento, com botões desabilitados.
- [x] 2.6 Contadores da listagem deixam de mostrar estado da importação.

## 3. Validação

- [x] 3.1 Teste da leitura por IA, com resposta simulada, cobrindo descarte de linha ruidosa e ausência de gravação.
- [x] 3.2 Teste do recurso de antecipação expondo nome e especialidade.
- [x] 3.3 `openspec validate`, `tsc -b`, `oxlint`, `vite build` e `php artisan test`.
- [ ] 3.4 Conferir no navegador uma leitura real, com a chave OpenAI de produção — os testes usam resposta simulada e provam o caminho do código, não a qualidade da extração.

# Tasks

Cada bloco corresponde a um defeito que produção encontrou. Um commit por bloco.

## 1. Um código só, igual na tela e no log

- [x] 1.1 Trocar `ErroClienteController::codigoDoErro` por FNV-1a de 32 bits sobre unidades UTF-16, espelhando o JS; verificar com teste que os valores batem com os de referência gerados pela implementação JS, incluindo acento e emoji fora do BMP
- [x] 1.2 Expor `codigoDoRelato()` em `reportClientError.ts`, aplicando a mesma normalização (corte de mensagem e pilha) que o envio aplica; verificar com teste que pilha acima do limite dá o mesmo código do relato enviado
- [x] 1.3 `AppErrorBoundary` passa a mostrar o código do relato em vez de calcular o seu; verificar com teste e2e que o código da tela é igual ao código derivado do payload enviado
- [x] 1.4 Corrigir o comentário de `codigoDoErro` que afirmava que o log guarda os dois códigos

## 2. O relato identifica a clínica

- [x] 2.1 Extrair a chave do localStorage da sessão para `web/src/stores/authStorageKey.ts`, sem dependências, e passar o `authStore` a usá-la de lá; verificar que a autenticação continua funcionando
- [x] 2.2 `reportClientError` passa a ler o token dessa chave e a enviar `Authorization: Bearer` quando houver; verificar com teste que o cabeçalho vai quando há sessão e não vai quando não há
- [x] 2.3 Garantir que falha na leitura da credencial não impede o envio; verificar com teste que localStorage inacessível ainda relata
- [x] 2.4 Teste de feature que envia um Bearer DE VERDADE (não `Sanctum::actingAs`) e afirma tenant e usuário no registro; verificar que ele falha se o cabeçalho não for enviado — é o teste que faltava
- [x] 2.5 Teste e2e que afirma o cabeçalho `Authorization` na requisição de relato com usuário logado

## 3. Recusa deixa rastro

- [x] 3.1 `failedValidation` em `RegistrarErroClienteRequest` registrando `erro-cliente-recusado` em `Log::error` com os motivos e o TAMANHO de cada campo, nunca o conteúdo; verificar com teste que payload sem mensagem gera a linha e que o conteúdo não aparece nela
- [x] 3.2 Verificar com teste que a etiqueta nova não é contada pelo `grep` de `erro-cliente`

## 4. Fechamento

- [x] 4.1 Rodar `cd api && ./vendor/bin/pint --test` nos arquivos tocados e `php artisan test` inteiro, verde
- [x] 4.2 Rodar `cd web && npm run lint && npm run build` sem erro
- [x] 4.3 Rodar `cd web && npm run test:e2e`: os testes desta change passam (`tela-de-erro.spec.ts` 5/5, `report-client-error.spec.ts` 17/17). A SUÍTE INTEIRA ESTÁ VERMELHA POR MOTIVO ANTERIOR A ESTA CHANGE: 8 falhas medidas no código limpo, com as mudanças guardadas em stash — ver o resumo
- [x] 4.4 Rodar `openspec validate codigo-de-erro-que-encontra-o-log --type change --strict` sem erro
- [x] 4.5 Novidade e prompt de deploy, registrando que os códigos gravados de 22 a 24/09 seguem no formato antigo

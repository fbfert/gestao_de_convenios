## Why

A tela de erro subiu em produção em 22/09 e em 24/09 registrou o primeiro erro real da clínica, na tela de Solicitações. Aí os três defeitos apareceram de uma vez.

**O código na tela não encontra a linha do log.** A clínica leu `836920`; o log gravou `5F6052` para o mesmo erro. São dois hashes diferentes — a tela usa FNV-1a, o servidor usa sha256 —, o cliente nunca envia o código que mostrou, e o servidor não o guarda. O código existe exatamente para casar o telefonema com a linha do log, e não casa com nada. Pior: o comentário no código afirma que "o log guarda os dois", o que é falso.

**O registro sai anônimo.** Os três relatos de 24/09 vieram de `/solicitacoes` — usuário logado — e gravaram `tenant_id: null, user_id: null`. O envio usa `fetch` cru para não depender do axios, e o token nunca foi anexado. A spec exige tenant e usuário quando autenticado, e isso nunca funcionou em produção. O teste de PHPUnit passava porque usa `Sanctum::actingAs`, que injeta o usuário direto no guard: provou que o controller lê o usuário, nunca que o navegador manda o token.

**Payload recusado não deixa rastro.** Quando a validação recusa um relato, a resposta é 422 e nada é registrado. Foi o que me fez apostar num diagnóstico errado antes de olhar o log do nginx: um erro que a clínica viu pode desaparecer sem sinal algum, e é a situação em que menos se pode ficar sem sinal.

## What Changes

- O código do erro passa a ser **um só**: o mesmo algoritmo nos dois lados, calculado sobre os mesmos valores, para o que a tela mostra ser o que o log grava
- O relato passa a levar o token quando houver sessão, para `tenant_id` e `user_id` deixarem de ser nulos
- Relato recusado pela validação passa a ser registrado, com o motivo
- **BREAKING (só no log):** os códigos gravados antes desta change seguem em sha256 e não voltam a ser reproduzíveis pela tela. `5F6052` continua no log de 24/09; a tela de hoje calcularia `836920` para o mesmo erro

## Capabilities

### Modified Capabilities
- `resiliencia-da-interface`: o código do erro passa a ser o mesmo na tela e no log; o relato autenticado identifica a clínica; relato recusado é registrado

## Impact

- `api/app/Http/Controllers/ErroClienteController.php`: `codigoDoErro` passa a FNV-1a
- `api/app/Http/Requests/RegistrarErroClienteRequest.php`: registra a recusa
- `web/src/lib/reportClientError.ts`: anexa o token; expõe o código do relato normalizado
- `web/src/routes/AppErrorBoundary.tsx`: mostra o código do relato, não um calculado à parte
- `web/src/stores/authStorageKey.ts` (novo): a chave do localStorage num lugar sem dependências
- Sem migration. Deploy é código e bundle

## Context

Os três defeitos foram encontrados por produção, não por teste — vale registrar por que cada teste passou.

**O hash.** O comentário em `reportClientError.ts` dizia: "o backend usa sha256, mas o que importa é que cada lado seja estável consigo mesmo; o log guarda os dois". A primeira metade é verdadeira e irrelevante; a segunda é falsa. Nenhum teste comparava os dois lados, porque cada lado tinha seu próprio teste de estabilidade — e os dois passavam.

**O token.** `ErroClienteApiTest` autentica com `Sanctum::actingAs`, que injeta o usuário no guard sem passar por cabeçalho HTTP. O teste afirmava que o controller lê `user('sanctum')`, o que é verdade; o que ninguém verificou é se o navegador manda algo para ele ler. A prova disso só aparece num teste que envia um Bearer de verdade, ou num e2e que olhe o cabeçalho da requisição.

**A recusa silenciosa.** A spec já dizia "SHALL recusá-lo", e o teste afirmava 422. Recusar era o comportamento pedido; o que faltava era o 422 deixar rastro.

Em produção, `LOG_LEVEL=error`. Qualquer registro em nível menor que `error` não é escrito — o que torna `Log::warning` inútil para este caso.

## Goals / Non-Goals

**Goals:**

- Um código só, igual na tela e no log, calculado sobre os mesmos bytes
- Relato de usuário logado identificando clínica e usuário
- Recusa de validação visível no log
- Testes que falhem pelos três motivos reais, não pelos que já estavam cobertos

**Non-Goals:**

- Reprocessar os códigos já gravados. Os registros de 22 a 24/09 seguem em sha256; a change não reescreve log
- Tabela para os erros. Continua log, como decidido na change anterior
- Cobrir `QueryClientProvider` e `ConfirmDialogProvider` com um segundo boundary. Limite conhecido, registrado no arquivo

## Decisions

### FNV-1a nos dois lados, e o PHP segue o JavaScript

O cliente não pode usar sha256: `crypto.subtle.digest` é **assíncrono**, e o código do erro tem de existir no instante em que a tela renderiza. Poderia entrar uma implementação de sha256 em JS, mas é muito mais código para o mesmo resultado — o código não precisa ser criptográfico, precisa ser estável e curto.

Então o PHP passa a fazer FNV-1a de 32 bits como o JS faz. Um detalhe importa: `charCodeAt` devolve **unidades UTF-16**, não bytes. Em ASCII dá no mesmo; em `"Não foi possível"` não dá — o PHP leria dois bytes onde o JS lê um caractere. O PHP converte para UTF-16BE antes de percorrer, o que também faz par com surrogates de emoji cair igual nos dois lados.

O teste embute valores de referência **gerados pela implementação JS** (incluindo acento e emoji fora do BMP), em vez de dois testes independentes de estabilidade. É a única forma de o teste falhar se os lados divergirem.

### O código é calculado sobre o valor ENVIADO

`reportClientError` corta mensagem em 1000 e pilha em 4000 antes de enviar; a tela calculava sobre o valor inteiro. Para pilha acima de 4000 caracteres — comum em erro de React — os dois já divergiriam mesmo com o mesmo algoritmo.

A correção não é duplicar o corte na tela: é a tela **mostrar o código que o relator devolveu**. `reportClientError` já devolve o código; passa a haver também `codigoDoRelato()`, que aplica a mesma normalização sem enviar nada, para quem só precisa do número. Um lugar só decide o que entra no hash.

### O token sai do localStorage, não do store

Anexar o token não pode reintroduzir a dependência que o `fetch` cru existe para evitar. Importar `authStore` traria zustand e seu grafo de módulos para dentro do caminho que roda quando tudo já quebrou.

Então a chave do localStorage vira um módulo próprio, sem dependência alguma (`authStorageKey.ts`), importado pelo store e pelo relator. O relator lê o JSON, tira o token, e **qualquer falha nessa leitura é ignorada**: relato sem token é melhor do que relato nenhum. É o que o cenário "credencial ilegível" prende.

### A recusa é registrada em `Log::error`, com etiqueta própria

`failedValidation` no FormRequest, registrando antes de lançar. Nível `error` porque produção está com `LOG_LEVEL=error` e um `warning` não seria escrito — o registro existiria no código e não no arquivo.

Etiqueta `erro-cliente-recusado`, distinta de `erro-cliente`, para o `grep` de sempre continuar contando só os erros de verdade e a recusa aparecer quando alguém a procurar.

O registro leva os motivos e o **tamanho** de cada campo recebido, não o conteúdo: se a recusa foi por pilha grande demais, o que interessa é saber quanto veio — e gravar 30 mil caracteres num log por causa de um payload recusado seria o próprio problema.

## Risks / Trade-offs

**Os códigos antigos não voltam.** Quem tiver anotado um código de 22 a 24/09 não vai encontrá-lo depois desta change. São três dias e um erro conhecido; reescrever log seria pior.

**FNV-1a tem colisão mais fácil que sha256.** São 32 bits truncados a 24 pelo corte em 6 caracteres — colisão é plausível num volume grande. Aceitável: o código é dica de busca, não identidade. Quem investiga confirma pela mensagem e pela pilha, que estão na mesma linha.

**O token no corpo do relato aumenta o que um log pode revelar.** O token vai no cabeçalho, não no corpo, e o controller não o registra — só o `tenant_id` e o `user_id` que ele resolve. Vale manter assim: relato de erro é um lugar tentador para gravar "o contexto todo".

**`failedValidation` registra o que um atacante manda.** A rota é pública e com `throttle:30,1`. Trinta linhas por minuto por IP é o teto do estrago, e o registro guarda tamanhos em vez de conteúdo — então nem o conteúdo entra no arquivo.

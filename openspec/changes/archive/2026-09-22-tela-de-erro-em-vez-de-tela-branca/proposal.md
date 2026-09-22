## Why

A clínica vê a tela ficar branca "do nada", às vezes no meio de um processo. Não há mensagem, não há recarregamento, e quem estava lançando sessão ou finalizando guia perde o que estava fazendo na tela — sem saber se algo chegou a ser gravado.

São duas falhas empilhadas, e a de baixo é a que importa:

**O app não tem nenhuma rede de proteção.** `main.tsx`, `App.tsx` e `AppRoutes.tsx` montam a árvore inteira sem `ErrorBoundary`. Qualquer exceção durante uma renderização faz o React desmontar o `#root` — a página fica literalmente em branco. E como também não existe captura de erro do navegador (`window.onerror`, `unhandledrejection`), **produção nunca registrou nenhuma dessas quedas**: não há um único log para consultar depois que a clínica liga.

**O gatilho reproduzido é o tradutor do navegador.** Ele embrulha cada texto em `<font><font>…</font></font>`. O `Botao` renderiza o spinner **antes** do rótulo (`{carregando ? <LoaderCircle/> : null}{children}`), então no clique seguinte em qualquer botão com estado de carregamento — Sair, Salvar, Finalizar, Enviar, Importar — o React chama `insertBefore(spinner, textoDoRótulo)`, o texto já não é filho do botão, e o DOM lança `NotFoundError`. Tela branca.

O commit `138ba6c` (15/09, em produção desde 16/09) já pôs `lang="pt-BR"`, `translate="no"` e `<meta name="google" content="notranslate">` no `index.html`. Isso barra o tradutor nativo do Chrome e do Edge — **mas não extensões**, e não fecha o buraco estrutural: qualquer outro erro de renderização continua apagando a tela.

## What Changes

**A tela de erro no lugar da tela branca**

- `AppErrorBoundary` envolvendo as rotas, com tela em português: título, a garantia de que os dados no servidor não se perderam, um código curto do erro para o suporte achar no log, e dois caminhos de saída — recarregar a página e voltar ao início.
- O boundary se recupera ao mudar de rota, para que "Voltar ao início" funcione sem recarregar.

**Erro do navegador vira log no servidor**

- Toda queda — a capturada pelo boundary e a que escapa pelos listeners globais — é enviada para a API e registrada no log, com tenant e usuário quando houver.
- O envio é limitado: o mesmo erro não é enviado duas vezes na mesma sessão, e há teto por sessão. Falha de envio nunca vira um segundo erro.
- A rota fica **fora** do grupo autenticado: erro na tela de login também precisa chegar.

**O botão para de quebrar com o DOM reescrito**

- O rótulo do `Botao` passa a ser envolvido num elemento. O spinner passa a ser inserido antes de um elemento, não de um nó de texto solto — que é o que o tradutor destrói.

**Não faz parte desta change**

- Tabela própria para os erros. Vai para o log; se virar volume, vira tabela depois.
- Tela de administração dos erros no produto.
- Perseguir o tradutor: o app declara que não deve ser traduzido e sobrevive quando for mesmo assim. Não tenta detectar nem desfazer a tradução.
- Reescrever outros componentes que possam ter o mesmo padrão do `Botao`. A proteção estrutural cobre o resto; o `Botao` é corrigido porque é o gatilho reproduzido.

## Capabilities

### New Capabilities

- `resiliencia-da-interface`: o que a tela faz quando uma renderização falha, o que é registrado sobre isso, e a garantia de que o app sobrevive ao DOM ser reescrito por fora.

### Modified Capabilities

Nenhuma. As specs existentes descrevem fluxos de negócio; esta change não altera requisito nenhum deles.

## Impact

**Código**

- `web/src/routes/AppErrorBoundary.tsx` (novo) e `web/src/App.tsx`: o boundary dentro do `BrowserRouter`, para o fallback poder navegar.
- `web/src/lib/reportClientError.ts` (novo) e `web/src/main.tsx`: os listeners globais.
- `web/src/components/ui/Botao.tsx`: o rótulo envolvido.
- `api/app/Http/Controllers/ErroClienteController.php` (novo) e `api/routes/api.php`: `POST /erros-cliente`, sem autenticação e com throttle.

**Dependências**

- `react-error-boundary` no frontend. React exige componente de classe para boundary, e a biblioteca entrega `resetKeys`, `onError` e `FallbackComponent` prontos — que é exatamente o que esta change precisa. Entra pelo build da imagem; não há passo manual no deploy.

**Dados**

Nenhum. Sem migration.

**Conflito entre spec e código**

Nenhum identificado.

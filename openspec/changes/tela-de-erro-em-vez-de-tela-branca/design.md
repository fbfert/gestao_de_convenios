## Context

Ver `proposal.md` — Why. O que condiciona a solução:

- `web/src/App.tsx` monta `QueryClientProvider` → `ConfirmDialogProvider` → `BrowserRouter` → `AuthNavigationBridge` + `AppRoutes`. Nenhuma proteção em lugar nenhum.
- `web/src/components/ui/Botao.tsx` (~linha 75) renderiza `{carregando ? <LoaderCircle/> : null}{children}`. O caminho `asChild` usa `Slot.Root` e não passa por aí.
- O design system é semântico e cobre três temas (claro, escuro, alto contraste). `npm run lint` roda `scripts/verificar-design-system.mjs`, que reprova hex e valor mágico no CSS.
- `api/routes/api.php` tem tudo dentro de `auth:sanctum` + `EncerrarSessaoExpirada`, com uma exceção precedente: `GET /health`, público de propósito e documentado como tal. `POST /login` fica fora com `throttle:login`.
- O middleware `ExigeAutorizacaoDeclarada` exige que toda rota do grupo `api` declare uma guarda — rota nova nasce fechada.
- ADR-06: status em inglês no banco/API, tradução só na apresentação.

## Goals / Non-Goals

**Goals**

- Nenhum caminho de renderização pode terminar em página em branco.
- Toda queda gera um registro consultável, inclusive as de hoje que somem sem deixar rastro.
- O relator de erro é o código mais defensivo do app: ele roda justamente quando as coisas já deram errado.

**Non-Goals**

- Recuperar o estado da tela que caiu. O boundary devolve uma saída, não desfaz o erro.
- Classificar ou agrupar erros no produto.
- Detectar tradutor.

## Decisions

### 1. `react-error-boundary` em vez de classe própria

**Escolhido**: a biblioteca.

O prompt que originou esta change justificava isso com uma regra de `CLAUDE.md` — arquivo que **não existe neste repositório**; o que existe é `AGENTS.md`, que trata só do fluxo OpenSpec. A justificativa cai, mas a escolha se sustenta por outro motivo: o que a spec pede — recuperar ao mudar de rota, chamar o relator no erro, um componente de fallback — é exatamente `resetKeys`, `onError` e `FallbackComponent`. Escrever isso à mão são ~40 linhas de uma classe cujo único trabalho é reimplementar uma API estável e conhecida.

**Custo**: dependência nova no frontend. Entra pelo build da imagem (o `Dockerfile` já faz `npm ci`), então não há passo manual no deploy — mas o prompt de deploy precisa dizer que ela existe.

### 2. O boundary fica DENTRO do `BrowserRouter`

Para o fallback poder chamar `navigate('/dashboard')`. Fora do router, "Voltar ao início" só poderia trocar `window.location`, o que recarrega a aplicação inteira — e recarregar já é o *outro* botão.

`resetKeys={[location.pathname]}` faz o boundary se rearmar quando a rota muda. Sem isso, depois de um erro a tela de erro ficaria colada mesmo navegando.

**Ficará fora do `QueryClientProvider` e do `ConfirmDialogProvider`**: um erro dentro deles derrubaria o app antes do boundary. É um limite conhecido e aceito — esses dois são providers estáveis, e protegê-los exigiria um segundo boundary acima do router, sem acesso a `navigate`.

### 3. O código do erro é derivado, não sorteado

O fallback mostra um código curto para o suporte casar com o log. Ele é um **hash curto da mensagem + pilha**, e não um identificador aleatório por ocorrência.

**Por quê**: o mesmo erro em dois usuários gera o mesmo código, o que transforma "três pessoas ligaram com o código A3F2" numa informação útil em vez de três investigações. E o servidor consegue recomputá-lo a partir do que recebeu, sem precisar que o cliente mande um id que poderia não chegar.

### 4. O relator nunca lança, e nunca insiste

Três travas, todas pelo mesmo motivo — este código roda quando algo já quebrou:

- **`try/catch` em volta de tudo**, inclusive da serialização. Falha ao relatar é silêncio, não uma segunda exceção.
- **Deduplicação por mensagem+pilha na sessão.** Um erro em laço de renderização dispararia centenas de requisições idênticas.
- **Teto de dez envios por sessão.** Depois disso, para. Dez erros distintos numa sessão já dizem o que precisa ser dito.

A pilha é truncada em 4000 caracteres: pilha maior que isso não acrescenta nada e só engorda o log.

### 5. A rota de registro fica fora da autenticação

`POST /erros-cliente` precisa aceitar erro da tela de login, que por definição não tem sessão. Segue o precedente de `GET /health`, que já mora fora do grupo autenticado e está documentado como tal.

Em troca, a rota é a mais defendida do sistema: `throttle:30,1`, validação estrita do payload, e nada do corpo é interpolado em lugar nenhum — vai inteiro para o log estruturado. Tenant e usuário entram quando `$request->user('sanctum')` resolve, o que acontece quando o token vier junto.

**Sem tabela**: vai para `Log::error('erro-cliente', [...])`. Uma tabela pediria tela de administração, expurgo e migration — e hoje não sabemos sequer o volume. Se virar volume, vira tabela depois.

### 6. O `Botao` ganha um `<span>`, e só no caminho normal

`{children}` passa a viver dentro de um `<span>`. O spinner passa a ser inserido antes de um **elemento**, e é isso que sobrevive à reescrita: o tradutor troca nós de texto, não a caixa que os contém.

O caminho `asChild` fica intocado — ali o `Botao` não renderiza o botão, e envolver o filho quebraria o contrato do `Slot`.

O layout já é `inline-flex items-center gap-2`, então um `<span>` a mais não muda nada visualmente.

### 7. O erro para o teste e2e é uma rota, não um componente disfarçado

O teste precisa de um erro de renderização de verdade. A porta é uma rota que só existe quando `import.meta.env.MODE === 'e2e'` — modo que só a suíte usa (`vite --mode e2e` no `playwright.config.ts`).

**Por quê uma rota e não um `?simular-erro=1` num componente real**: um componente de produção que sabe lançar é um componente de produção que pode lançar. Uma rota inteira que o build de produção não contém não tem como ser alcançada — o `import.meta.env.MODE` é resolvido em tempo de build, e o código sai do bundle.

## Risks / Trade-offs

- **O boundary não cobre os providers acima dele** → erro em `QueryClientProvider`/`ConfirmDialogProvider` ainda apaga a tela. Aceito: são providers estáveis, e cobri-los custaria um segundo boundary sem `navigate`. Se acontecer, os listeners globais ao menos registram.
- **Dependência nova no frontend** → uma a mais para manter atualizada. Mitigado por ser pequena, estável e amplamente usada; e o `npm ci` do build já cuida da instalação.
- **A rota pública pode receber lixo** → throttle, validação e nenhum uso do conteúdo além do log. O pior caso é ruído no log, não execução de nada.
- **Deduplicação pode esconder recorrência** → o mesmo erro acontecendo cinquenta vezes numa sessão chega uma vez só. É deliberado: a alternativa é a enxurrada. A recorrência entre sessões diferentes continua visível.
- **O código curto pode colidir** → hash curto colide, em tese. Na prática o log guarda mensagem e pilha inteiras; o código é atalho de conversa, não chave.
- **A correção do `Botao` não vale para outros componentes** com o mesmo padrão. O boundary cobre o estrago; corrigir todos exigiria varrer a base sem um gatilho conhecido.

## Migration Plan

1. Sem migration. Só código e bundle.
2. Deploy fora do horário de uso da clínica — é bundle novo, e quem estiver com a tela aberta vai receber a versão nova no próximo carregamento.
3. Depois de subir, conferir no log que a rota responde: forçar um erro conhecido pela tela e ver o `erro-cliente` aparecer.

**Rollback**: voltar o código. Nada no banco muda, então não há o que desfazer.

## Open Questions

Nenhuma.

# Tasks

Ordem deliberada: o registro de erros primeiro (o boundary depende dele), depois a rede de proteção, depois o gatilho conhecido, e os testes ao lado de cada um. Um commit por bloco.

## 1. Registro de erros no servidor

- [x] 1.1 Criar `ErroClienteController` com `POST /erros-cliente`, gravando em `Log::error('erro-cliente', [...])` com tenant e usuário quando `$request->user('sanctum')` resolver; verificar com teste de feature que responde 204 e que o log recebe os campos
- [x] 1.2 Registrar a rota FORA do grupo autenticado, com `throttle:30,1`, no precedente de `GET /health`; verificar com teste de feature que funciona sem token e que a 31ª chamada no minuto responde 429
- [x] 1.3 Validar o payload (mensagem obrigatória, pilha até 4000 caracteres, endereço, navegador, momento); verificar com teste de feature que payload sem mensagem responde 422

## 2. O relator no navegador

- [x] 2.1 Criar `web/src/lib/reportClientError.ts` que monta o payload e envia, sem nunca lançar; verificar que um `fetch` que rejeita não propaga exceção
- [x] 2.2 Deduplicar por mensagem+pilha na sessão e limitar a dez envios; verificar com teste que o mesmo erro só vai uma vez e que o 11º distinto não vai
- [x] 2.3 Derivar o código curto do erro de mensagem+pilha, para o mesmo erro gerar o mesmo código em usuários diferentes; verificar com teste que dois erros iguais dão o mesmo código e dois diferentes não
- [x] 2.4 Registrar em `web/src/main.tsx` os listeners `error` e `unhandledrejection` chamando o relator; verificar que a página carrega normalmente com eles instalados

## 3. A tela de erro

- [x] 3.1 Adicionar `react-error-boundary` ao `web/package.json`; verificar com `npm ci` e `npm run build`
- [x] 3.2 Criar `web/src/routes/AppErrorBoundary.tsx` com o fallback em português — título, a garantia sobre os dados no servidor, o código do erro e os dois botões — usando só tokens do design system; verificar com `npm run lint` (que inclui o `ds:check`)
- [x] 3.3 Envolver `<AppRoutes />` em `App.tsx`, dentro do `BrowserRouter`, com `resetKeys={[location.pathname]}` e `onError` chamando o relator; verificar que a navegação normal segue funcionando
- [x] 3.4 Rota de erro simulado que só existe em `import.meta.env.MODE === 'e2e'`; verificar que ela não aparece no bundle de produção (`npm run build` e busca pelo identificador no `dist/`)

## 4. O botão à prova de tradutor

- [x] 4.1 Envolver `{children}` num `<span>` no caminho normal de `Botao.tsx`, deixando o `asChild` intocado; verificar com `npm run build` e conferindo que nenhum teste existente de botão quebra

## 5. Testes de ponta a ponta

- [x] 5.1 e2e: logar, abrir o dashboard, reescrever o DOM como o tradutor faz, clicar em `shell-logout` e afirmar que o `#root` NÃO ficou vazio; verificar que o teste falha sem a correção do `Botao` e passa com ela
- [x] 5.2 e2e: forçar erro de renderização pela rota de simulação e afirmar que aparece "Algo deu errado nesta tela" e que "Voltar ao início" recupera sem recarregar
- [x] 5.3 e2e: afirmar que o erro simulado chega ao servidor (interceptar a chamada a `/erros-cliente`)

## 6. Documentação e fechamento

- [x] 6.1 Novidade `api/resources/novidades/2026-09-22-tela-de-erro-em-vez-de-tela-branca.md`, `tipo: correcao`, no formato dos vizinhos; verificar que aparece na tela de novidades
- [x] 6.2 Prompt de deploy `docs/prompt-deploy-tela-de-erro.md` no padrão dos existentes: sem migration, só código e bundle, dependência nova no frontend, deploy fora do horário de uso; verificar que o documento existe e diz como confirmar que a rota de erro está no ar
- [x] 6.3 Rodar `cd web && npm run lint && npm run build` sem erro
- [x] 6.4 Rodar `cd web && npm run test:e2e` e `cd api && php artisan test`, ambos verdes
- [x] 6.5 Rodar `openspec validate tela-de-erro-em-vez-de-tela-branca --type change --strict` sem erro

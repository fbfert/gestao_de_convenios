## 1. Modelo padrão

- [x] 1.1 Migration `2026_08_12_190000`: coluna `ai_openai_settings.model_id`.
- [x] 1.2 Ordem de resolução no `PedidoMedicoAiService`, com o literal como último recurso.
- [x] 1.3 Expor e aceitar o campo no resource e no request da conexão.

## 2. Tela

- [x] 2.1 Campo **Modelo padrão** com `datalist` alimentado pela listagem.
- [x] 2.2 Lista de modelos clicável, marcando o escolhido.
- [x] 2.3 Texto de apoio em Organização e Projeto, avisando que pedem identificador e não nome.
- [x] 2.4 Corrigir o `datalist` da tela de Prompts, alimentado por uma busca que nunca disparava.

## 3. Diagnóstico

- [x] 3.1 Levar o status e a mensagem da OpenAI para a tela quando a listagem for recusada.

## 4. Validação

- [x] 4.1 `tsc`/`oxlint` e suíte da API.
- [x] 4.2 Verificação em produção: gravar o modelo sem apagar a chave, e a listagem respondendo.

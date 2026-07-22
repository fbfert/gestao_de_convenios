## Context

Configurações já possui subabas e endpoints protegidos por `configuracoes.manage`. A integração com OpenAI precisa ficar no backend para não expor a API key no navegador. A listagem de modelos deve usar a API oficial `GET /v1/models`, com autenticação Bearer.

## Goals / Non-Goals

**Goals:**
- Persistir uma configuração OpenAI por tenant.
- Persistir prompts operacionais por tenant.
- Listar modelos disponíveis com a chave configurada.
- Preservar a API key existente quando o formulário for salvo sem nova chave.

**Non-Goals:**
- Executar OCR ou chamadas de inferência nesta mudança.
- Processar anexos reais de solicitações ou sessões.
- Definir o schema final de extração para gravação automática no banco.

## Decisions

- Usar `ai_openai_settings` para conexão e `ai_prompt_templates` para prompts. Racional: separa credencial única dos vários prompts operacionais.
- Armazenar API key com cast encrypted e nunca retorná-la no resource. Racional: credencial não deve sair do backend.
- Expor endpoint dedicado para listar modelos. Racional: o frontend precisa de modelos, mas não deve chamar OpenAI diretamente.
- Criar prompts padrão para os dois casos de uso solicitados. Racional: entrega a tela pronta para edição, mesmo antes de chamadas reais de IA.

## Risks / Trade-offs

- [A chave OpenAI fica em banco] -> usar criptografia via cast do Laravel e não retornar a chave em responses.
- [Listagem de modelos depende de rede externa] -> retornar erro tratado quando a conexão falhar.
- [Prompts sem execução real ainda não garantem qualidade de extração] -> manter esta mudança focada na configuração e preparar integração posterior.

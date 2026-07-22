## Context

Solicitações já possui criação manual e a configuração de IA já permite salvar conexão OpenAI e prompts por tenant. O novo fluxo deve reutilizar esses cadastros, preservar o anexo enviado e nunca criar solicitação automaticamente sem revisão.

## Goals / Non-Goals

**Goals:**
- Ler PDF/JPG/PNG de pedido médico via IA e retornar dados estruturados.
- Exigir que operador confirme convênio, paciente, profissional, especialidade, médico e data antes de criar.
- Permitir criação rápida de paciente por nome, especialidade por nome e médico por nome.
- Manter o pedido anexado acessível no modal da solicitação.

**Non-Goals:**
- Garantir extração perfeita ou criação automática sem intervenção humana.
- Criar cadastro completo de paciente com CPF/carteirinha nesta etapa.
- Integrar o anexo com armazenamento externo.

## Decisions

- Salvar o arquivo localmente em `storage/app/pedidos-medicos` e gravar caminho/metadados na solicitação criada. Racional: atende rastreabilidade sem expor arquivos publicamente.
- Usar endpoint backend para análise com OpenAI. Racional: a API key fica protegida no servidor.
- Retornar sugestões por similaridade simples no backend. Racional: mantém a mesma lógica para todos os clientes e evita expor listas completas além das referências já usadas.
- Passar o identificador temporário do upload para a criação da solicitação. Racional: evita duplicar upload depois da revisão.
- Manter criação rápida de entidades em endpoints existentes quando possível. Racional: reaproveita validações atuais e reduz novas superfícies.

## Risks / Trade-offs

- [OCR pode identificar dados incorretos] -> operador revisa antes de salvar.
- [Arquivos médicos contêm dados sensíveis] -> arquivo fica atrás de rota autenticada e vinculado ao tenant.
- [IA pode retornar JSON inválido] -> parser deve tratar falha e colocar conteúdo não entendido em observações.
- [Paciente criado só com nome fica incompleto] -> o fluxo permite completar cadastro depois, conforme decisão desta etapa.

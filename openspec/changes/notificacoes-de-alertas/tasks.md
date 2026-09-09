## 1. Modelo

- [ ] 1.1 Migration de `alerta_destinatarios` (do tenant).
- [ ] 1.2 Migration de `alerta_destinatarios_globais`, sem `tenant_id`.
- [ ] 1.3 Colunas `alertas.notificado_em` e `alerta_regras.janela_silencio_horas`.
- [ ] 1.4 Models, sendo que o global NÃO usa `BelongsToTenant`.

## 2. Envio

- [ ] 2.1 Serviço de notificação: monta o corpo a partir de `email_templates`, com texto padrão de reserva.
- [ ] 2.2 Filtro por níveis, chaves e canal de cada destinatário.
- [ ] 2.3 `EnviarDigestAlertasJob` de hora em hora, disparando a quem tem o horário naquela hora.
- [ ] 2.4 Envio imediato acionado pelo avaliador, com janela de silêncio.
- [ ] 2.5 Digest global do suporte, um e-mail com todos os tenants, pelo mailer da aplicação.
- [ ] 2.6 Desativação após falhas seguidas, abrindo `email.destinatario_falhando`.
- [ ] 2.7 Seed dos modelos padrão em `email_templates`.

## 3. Web

- [ ] 3.1 Bloco de destinatários em `/alertas/configuracoes`.
- [ ] 3.2 Link para os modelos de e-mail existentes — não uma cópia.

## 4. Testes

- [ ] 4.1 Digest não envia quando não há alerta aberto.
- [ ] 4.2 Destinatário inscrito em uma chave não recebe alerta de outra.
- [ ] 4.3 Imediato não reenvia dentro da janela de silêncio.
- [ ] 4.4 Alertas repetidos da mesma chave viram um e-mail.
- [ ] 4.5 Destinatário global recebe todos os tenants num e-mail só.
- [ ] 4.6 Destinatário global sobrevive à consulta sem tenant resolvido.

## 5. Validação

- [ ] 5.1 `openspec validate notificacoes-de-alertas --type change --strict`.
- [ ] 5.2 `php artisan test` e `npm run lint`.

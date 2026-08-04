# Automacao Unimed v2 - Etapa 5: Homologacao Real

Data: 2026-08-04
Responsavel presente: Pendente de confirmacao
Ambiente: Pendente de confirmacao
Credencial: Deve estar configurada fora do chat e fora de arquivos versionados
Decisao final: Pendente

## Regras da sessao

- Acesso real somente com o responsavel presente.
- Nao registrar senha, carteirinha completa, documentos de pacientes ou screenshots sensiveis.
- Parar o caso ao observar estrutura inesperada, resultado incerto ou risco de duplicidade.
- Alteracoes de codigo nesta etapa ficam limitadas a correcoes pontuais de defeitos das Etapas 1 a 4.

## Roteiro de casos

| # | Caso | Resultado esperado | Resultado obtido | Evidencia sanitizada | Status |
|---|------|--------------------|------------------|----------------------|--------|
| 1 | Guia aprovada diretamente | Situacao real "Em execucao" ou "Autorizado" mapeada para `approved`; numero/protocolo/sessoes registrados quando retornados. | Pendente | Pendente | Pendente |
| 2 | Guia em analise | Situacao real "Em estudo" ou "Em Analise" mapeada para `under_review`, preservando texto original. | Pendente | Pendente | Pendente |
| 3 | Guia negada | Situacao real "Negado" mapeada para `denied`, sem capturar senha/validade. | Pendente | Pendente | Pendente |
| 4 | Restricao administrativa / pendencia administrativa | Guia local sem numero fica `needs_verification`, com texto da pendencia sanitizado. | Pendente | Pendente | Pendente |
| 5 | Beneficiario exigindo atualizacao cadastral | Worker clica em atualizar sem alterar dados e segue o fluxo. | Pendente | Pendente | Pendente |
| 6 | Medico encontrado por CRM | Prestador ativo selecionado por CRM e estrategia registrada como CRM. | Pendente | Pendente | Pendente |
| 7 | Medico encontrado apenas por nome | Busca por CRM falha, busca por nome seleciona prestador ativo e registra estrategia nome. | Pendente | Pendente | Pendente |
| 8 | Fallback "MEDICO NAO COOPERADO" | CRM e nome falham, fallback seleciona "MEDICO NAO COOPERADO" ativo e registra estrategia fallback. | Pendente | Pendente | Pendente |
| 9 | Procedimento com campos genericos | Campos DS/VL_ITEM_GENERICO preenchidos corretamente quando exibidos pelo portal. | Pendente | Pendente | Pendente |
| 10 | Upload de documentos geral e por especialidade | Pedido medico obrigatorio e anexos opcionais aparecem na listagem do portal apos upload. | Pendente | Pendente | Pendente |
| 11 | Script 2 atualizando status de guia existente | Consulta localiza guia real e atualiza status sem alterar campos indevidos. | Pendente | Pendente | Pendente |
| 12 | Script 3 capturando senha e validade | Guia aprovada em exames abertos tem senha e validade capturadas sem sobrescrever valores validos com vazio. | Pendente | Pendente | Pendente |
| 13 | Timeout simulado antes do submit final | Timeout antes de enviar permite nova tentativa segura, sem guia criada. | Pendente | Pendente | Pendente |
| 14 | Resultado incerto apos submit | Resultado incerto nao reenvia automaticamente; confirmacao idempotente ocorre antes de liberar novo envio. | Pendente | Pendente | Pendente |
| 15 | Mudanca seletor/estrutura simulada ou observada | Conector pausa com erro estrutural e operador consegue identificar motivo. | Pendente | Pendente | Pendente |

## Pendencias e riscos residuais

- Pendente de execucao assistida no portal real.
- Pendente de confirmar quais casos reais estao disponiveis na janela de homologacao.

## Checklist GO/NO-GO

- [ ] Casos 1, 2, 4, 10, 11, 12, 14 e 15 aprovados ou com pendencia aceita.
- [ ] Nenhum segredo ou dado sensivel registrado em log, evidencia ou commit.
- [ ] Credencial Unimed com permissao adequada e sem pausa indevida.
- [ ] Botao manual "Enviar para Unimed" permanece controlado por permissao e decisao operacional.
- [ ] Plano de rollback aceito pelo responsavel.

## Plano de rollback

1. Pausar a credencial Unimed em Configuracoes se houver comportamento inseguro apos entrada em uso.
2. Desabilitar uso operacional do botao manual "Enviar para Unimed" ate nova correcao/homologacao.
3. Manter guias incertas sem reenvio automatico e revisar manualmente no portal.
4. Reverter ou corrigir apenas commits da Etapa 5 que tenham sido criados para defeitos pontuais.

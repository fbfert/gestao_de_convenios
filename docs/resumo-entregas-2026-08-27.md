# Entregas de 27/08/2026 — mapeamentos Unimed de Terapia Ocupacional e Fonoaudiologia

## Automação Unimed — mapeamento Especialidade x Convênio resolvido

Ao enviar guia pra Unimed, `GerarGuiaUnimedService::avaliar()` bloqueava com a mensagem
"Mapeamento Especialidade x Convênio não configurado." — faltavam linhas em
`convenio_especialidade_mapeamentos` pra duas especialidades não-ABA: **Terapia Ocupacional** e
**Fonoaudiologia**. Isso já tinha sido registrado como pendência em 26/08 (ver
`docs/resumo-entregas-2026-08-26.md`, seção "Mapeamentos Unimed pendentes"), bloqueando na época dois
itens da Solicitação #5 (Paula de Lima Passos, Thais dos Santos Paz). Hoje o mesmo mapeamento ausente
também travava dois itens novos da Solicitação #6 — 4 itens no total.

**Correção do beco sem saída registrado em 26/08.** O ícone de lupa "Localizar" ao lado do campo de
código de procedimento (`CD_ITEM_1`) tinha sido descartado como inacessível pra automação, no mesmo
padrão de elemento com área zero de `#cadastro_biometria`. Não era o caso: o ícone é `#procura_servico_1`,
um `<a>` de verdade (id muda por linha: `_1`, `_2`, ...) — só precisa de `.click({force: true})` em vez
do clique padrão do Playwright. Ele abre um popup real
(`cmagnet/modal/busca_procedimento/localizar.do`) com busca por palavra-chave (`#s_DS_ITEM` +
`[name="Button_DoSearch"]`) ou por código exato (`#s_CD_ITEM`) — confirmado ao vivo contra
`rda.unimedsc.com.br`, reaproveitando o fluxo já existente até `selecionarContratado()` (não precisou
de `selecionarPrestador()`).

**Códigos encontrados e validados por comparação de padrão** com os mapeamentos já existentes:
especialidades ABA usam códigos `2250005xxx` ("TERAPIA ABA - X - PEDIATRICAS ESPECIAIS"); terapia
recorrente não-ABA usa código de **sessão** CBHPM (ex.: Psicoterapia individual = `50000470`,
"SESSAO DE PSICOTERAPIA INDIVIDUAL POR PSICOLOGO"), diferente de atendimento avulso (Nutricionista =
`50000560`, "CONSULTA..."). Buscando "TERAPIA OCUPACIONAL" e "FONOAUDIOLOGIA" no portal, os candidatos
que seguem o mesmo padrão de sessão individual ambulatorial:

| Especialidade | Código | Descrição no portal |
|---|---|---|
| Terapia Ocupacional | `50000080` | SESSAO INDIVIDUAL AMBULATORIAL, EM TERAPIA OCUPACIONAL |
| Fonoaudiologia | `50000616` | SESSAO INDIVIDUAL AMBULATORIAL DE FONOAUDIOLOGIA |

Inseridos em `convenio_especialidade_mapeamentos` (convênio Unimed, tenant NeuroKids) com
`quantidade_padrao = 10` e `ativo = true`, mesmo padrão das demais linhas. Nenhuma alteração de código
foi necessária — é puramente dado de configuração.

**Verificado:** `GerarGuiaUnimedService::avaliar()` reavaliado pros 4 itens (8, 10, 11, 12) — todos
`eligible = true`, sem motivos de bloqueio.

**Pendente:** nenhum desses 4 itens foi de fato enviado pro portal ainda — a verificação acima é só de
elegibilidade local. Falta disparar `enviar()`/o job do worker pra cada um e confirmar ao vivo que a
guia é criada de verdade na Unimed, como já foi feito antes pros casos da Greice e do Wilian (ver
26/08).

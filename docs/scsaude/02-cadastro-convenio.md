# SC Saúde — roteiro de cadastro do convênio no Gescon

> Destino sugerido no repo: `docs/scsaude/02-cadastro-convenio.md`.
> Pré-requisito de leitura: `docs/scsaude/01-descoberta.md`.
>
> Objetivo: deixar a NeuroKids operando o SC Saúde **no fluxo manual**, sem depender da fila do
> WebService nem do acesso ao Portal do Prestador. Nada aqui liga automação.

## 1. Convênio (`convenios`)

| Campo | Valor | Por quê |
|---|---|---|
| `nome` | `SC Saúde` | Como aparece nas telas |
| `descricao` | Plano de saúde do servidor público de SC (autogestão SEA/SC). Registro ANS 757444. | O registro ANS é campo obrigatório do XML de produção; guardar aqui evita procurar depois |
| `connector_type` | `manual` | `API` e `Scraping` não têm conector implementado e **quebram a verificação diária** — é o que a dica do campo já avisa |
| `connector_driver` | **deixar vazio** | Ver §4. Este é o ponto onde é fácil errar |
| `connector_config` | vazio | Nada a configurar sem automação |
| `carteirinha_blocos` | `[17]` | Ver §2 |
| `antecipacao_dias` / `antecipacao_referencia` | **deixar vazio** | Nulo faz o convênio herdar as configurações globais. O prazo próprio do SC Saúde ainda não é conhecido; um palpite aqui vira data de antecipação errada em guia real |
| `ativo` | sim | |

## 2. Formato da carteirinha

A FAQ do Portal do Prestador especifica **17 dígitos, com zero à esquerda**, no formato
`0306XXXXXXXXXXXXX`.

**Cadastrar como um único bloco de 17.** A validação do Gescon confere quantidade de dígitos por
bloco, e nada mais — não há dígito verificador nem validação de conteúdo. Dividir em blocos que
não existem no cartão só atrapalha quem digita conferindo o cartão na mão.

A alternativa `[4, 13]`, separando o prefixo `0306`, só vale se o CAS confirmar que esse prefixo é
fixo para todos os beneficiários. Enquanto isso não for confirmado, um bloco só é o que não
inventa estrutura.

## 3. Regras do convênio (`convenio_regras`)

Uma linha por tipo de terapia atendida. Para as três especialidades ABA da NeuroKids:

| Campo | Valor | Observação |
|---|---|---|
| `tipo_terapia` | `especializada` | |
| `frequencia_lancamento` | `semanal` | A diretriz do SC Saúde é por semana — ver `03-codigos-e-regras.md` §3 |
| `qtd_autorizada_por_ciclo` | `5` | "Cobertura obrigatória de até 05 atendimentos ou sessões por semana". É taxa emparelhada com a frequência, que é o que este campo significa |
| `sessoes_por_guia` | **deixar vazio** | Nulo significa "não sabemos", e é diferente de zero. O teto semanal não diz quantas sessões a operadora libera por autorização — são coisas diferentes. Segue pendente com o CAS |
| `validade_senha_dias` | **deixar vazio** | Mesma razão |
| `vigente_desde` | `2026-05-01` | Vigência declarada no cabeçalho da tabela de honorários |
| `observacoes` | "Cobertura de até 5 sessões/semana por diretriz. Sessões por autorização e validade de senha pendentes com o CAS." | Deixa o buraco visível para quem abrir a tela depois |

## 4. O erro que custa caro: `connector_driver`

`convenios.connector_driver` **não é um rótulo de fornecedor** — é o interruptor de toda a
automação, com doze consumidores, entre eles `GuiaService`, `SolicitacaoService`, o job que
enfileira consultas ao portal e o `VerificarGuiasDiarioJob`.

Preencher `connector_driver` com qualquer coisa no SC Saúde hoje faria duas coisas ao mesmo tempo:
toda guia nova tentaria falar com um portal que não temos credencial para acessar, falhando; e o
`VerificarGuiasDiarioJob` **pararia de conferir manualmente** as guias desse convênio, por assumir
que o robô cuida delas. Guia parada e ninguém olhando.

Esse acoplamento já foi documentado no change `carteirinha-formato-por-convenio`, que precisou
desamarrar a máscara de carteirinha exatamente por causa dele. O campo fica vazio até existir
automação de verdade — e a credencial, quando houver, entra pela tabela própria do change
`credenciais-por-convenio`, não por aqui.

## 5. Códigos de procedimento por especialidade

Depois do change `codigo-por-convenio-na-especialidade`, o código vive em
`convenio_especialidade_mapeamentos` e é editado no **cadastro da especialidade**, com um campo por
convênio. O convênio novo aparece sozinho em todas as especialidades, sem migração.

| Especialidade | Código no SC Saúde | Tabela | Valor |
|---|---|---|---|
| Psicologia ABA | `13107208` | 00 | R$ 150,00 |
| Fonoaudiologia ABA | `50005189` | 22 | R$ 150,00 |
| Fisioterapia ABA | **não existe no rol** | — | — |

Extraído da Tabela de Honorários não médicos — detalhes, valores e diretrizes em
`03-codigos-e-regras.md`.

- O `13107208` confere com o que já estava registrado no proposal do change
  `codigo-por-convenio-na-especialidade`, e todos os códigos têm os 8 dígitos que a FAQ exige.
- **Fisioterapia ABA não existe no rol do SC Saúde.** A seção de fisioterapia cobre Bobath, Cuevas
  Medek, TheraSuit/PediaSuit, Kinesio Taping e estimulação visual. Existe, sim, "Terapia
  Ocupacional pelo método ABA" (`50005170`). Deixar o campo da fisioterapia em branco aqui é a
  informação correta — o convênio realmente não tem esse serviço — mas a pendência é de negócio,
  não de cadastro: ver `03-codigos-e-regras.md` §2, onde a diferença entre as leituras possíveis
  vai de R$ 150,00 a R$ 20,58 por sessão.

## 6. Valores (`tabela_valores`)

Cascata de especificidade já existente (ADR-07): convênio + especialidade + profissional →
convênio + especialidade → convênio.

Os valores do SC Saúde estão na planilha **Tabela de Honorários não médicos** do rol publicado.
Enquanto não forem carregados, a conciliação financeira do convênio não fecha — o que é aceitável
para começar a operar guias, mas não para faturar.

## 7. Ordem sugerida

1. Cadastrar o convênio (§1 e §2).
2. Cadastrar a regra (§3), com a observação do que está pendente.
3. Preencher o código da Psicologia ABA na especialidade (§5).
4. Cadastrar um paciente de teste com carteirinha de 17 dígitos e conferir que a máscara aceita.
5. Criar uma solicitação de teste e confirmar que ela segue no fluxo manual — e que a guia aparece
   na verificação diária, em vez de sumir dela.
6. Só então buscar os dois códigos que faltam e os valores.

O passo 5 é o que prova que o §4 foi respeitado. Vale fazer mesmo parecendo burocrático: é barato
agora e caro de descobrir depois, com guia real parada.

## 8. O que continua pendente de terceiros

- Sessões por guia e validade de senha — pergunta 5 ao CAS.
- Acesso ao Portal do Prestador — pedido ainda não respondido.
- Documentação do WebService — NeuroKids na fila, sem previsão.
- Versão do padrão TISS aceita hoje — pergunta 1 ao CAS.

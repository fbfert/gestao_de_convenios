# SC Saúde — códigos, valores e regras de cobertura das terapias

> Destino no repo: `docs/scsaude/03-codigos-e-regras.md`.
> Fonte: planilha **Tabela de Honorários não médicos** do rol publicado
> (`Tabela-de-Honorarios-nao-medicos-01.06.2026.xlsx`), abas *Honorário Ambulatorial* e
> *Diretrizes Prof da Saúde*. A planilha de **Pacotes** foi conferida e não contém nada de terapia —
> é toda cirúrgica e hospitalar.
>
> Atenção à data: o arquivo se chama `01.06.2026`, mas o cabeçalho interno diz
> **"Vigência 01/05/2026"**. Usar a data interna e confirmar com o CAS.

## 1. Códigos das terapias por método

| Terapia / método | Código | Tabela domínio | Valor |
|---|---|---|---|
| Sessão de Psicologia — **método ABA** | `13107208` | **00** | R$ 150,00 |
| Sessão de Psicologia — método Denver | `50000470` | 22 | R$ 150,00 |
| Atendimento fonoaudiológico — **métodos ABA** | `50005189` | **22** | R$ 150,00 |
| Atendimento fonoaudiológico — métodos Denver | `13107186` | 00 | R$ 150,00 |
| Atendimento fonoaudiológico — métodos PECS | `13107178` | 00 | R$ 150,00 |
| Atendimento fonoaudiológico — métodos Bobath | `13107070` | 00 | R$ 150,00 |
| Terapia Ocupacional — **método ABA** | `50005170` | 22 | R$ 150,00 |
| Terapia Ocupacional — método Denver | `13107275` | 00 | R$ 150,00 |
| Terapia Ocupacional — método Bobath | `13107267` | 00 | R$ 150,00 |
| Terapia Ocupacional — integração sensorial | `50000055` | 22 | R$ 150,00 |

O `13107208` que já estava no proposal do `codigo-por-convenio-na-especialidade` **confere** com a
planilha, o que dá confiança de que a leitura foi da tabela certa. Todos os códigos têm 8 dígitos,
como a FAQ do portal exige.

## 2. O problema: não existe fisioterapia por método ABA

A seção **Honorário de Fisioterapia** lista cinco métodos, e ABA não é um deles:

| Fisioterapia por método | Código | Tabela | Valor |
|---|---|---|---|
| Método Bobath | `13107135` | 00 | R$ 150,00 |
| Método Cuevas Medek Exercises | `13107127` | 00 | R$ 150,00 |
| Método TheraSuit / PediaSuit | `13107100` | 00 | R$ 150,00 |
| Método Kinesio Taping | `13107097` | 00 | R$ 150,00 |
| Estimulação visual | `13107080` | 00 | R$ 150,00 |

A NeuroKids se declarou credenciada em **fisioterapia ABA**. No rol do SC Saúde esse serviço não
existe com esse nome. Há três leituras possíveis, e elas levam a valores muito diferentes:

1. **É terapia ocupacional na prática** → `50005170`, R$ 150,00. É o código que mais se parece com
   o que a clínica descreve.
2. **É fisioterapia por outro método** (Bobath, por exemplo) → R$ 150,00, mas exige que o método
   praticado seja de fato aquele.
3. **É fisioterapia neurofuncional comum** → `13106708` (nível I, R$ 20,58) ou `13106716`
   (nível II, R$ 32,41), tabela 22. **Sete vezes menos por sessão.**

Isso não é detalhe de cadastro: é a diferença entre R$ 150 e R$ 20,58 por atendimento. Precisa ser
resolvido com o CAS antes de faturar, não depois.

## 3. Regra de cobertura: 5 sessões por semana

As três diretrizes que cobrem as terapias da clínica dizem a mesma coisa:

> "Cobertura obrigatória, de até 05 (cinco) atendimentos ou sessões **por semana**, quando
> preenchido pelo menos um dos seguintes critérios"

Isso é um modelo diferente do da Unimed, que libera 10 sessões por guia. Mapeando para
`convenio_regras`:

| Campo | Valor | Por quê |
|---|---|---|
| `frequencia_lancamento` | `semanal` | A diretriz é por semana |
| `qtd_autorizada_por_ciclo` | `5` | É exatamente uma taxa emparelhada com a frequência, que é o que esse campo significa |
| `sessoes_por_guia` | **continua vazio** | A diretriz diz o teto semanal de cobertura, não quantas sessões a operadora libera por autorização. São coisas diferentes, e a segunda ainda não sabemos |
| `tipo_terapia` | `especializada` | |
| `vigente_desde` | `2026-05-01` | Vigência declarada no cabeçalho da planilha |

## 4. Elegibilidade por CID — e o segundo problema da fisioterapia

A cobertura das 5 sessões semanais só vale quando o paciente se enquadra num CID da lista de cada
diretriz.

- **Psicólogo e/ou Terapeuta Ocupacional**: inclui transtornos globais do desenvolvimento
  (**CID F84**) e transtornos específicos do desenvolvimento (F82, F83).
- **Fonoaudiólogo**: inclui autismo explicitamente (**CID F84.0, F84.1, F84.3, F84.5, F84.9**),
  além de F80, F90 e outros.
- **Fisioterapeuta**: espinha bífida (Q05), traumatismo intracraniano, paralisia cerebral,
  hemiplegia, paraplegia e tetraplegia, outras síndromes paralíticas. **F84 não está na lista.**

Ou seja: pelo texto da diretriz, um paciente com autismo não se enquadra nos critérios de cobertura
da fisioterapia por método. Isso reforça a dúvida da §2 — e sugere que a leitura 1 (é terapia
ocupacional) é a mais provável.

O Gescon já tem CID em solicitações (`cid_solicitacao` desde 25/08), então essa regra é conferível
na esteira, não só no papel.

## 5. Inconsistência na tabela de domínio

Os métodos ABA não estão todos na mesma tabela de domínio:

- Psicologia ABA → **00**
- Fonoaudiologia ABA → **22**
- Terapia Ocupacional ABA → **22**

Pela FAQ do portal, a tabela 00 é a dos itens que **exigem autorização prévia** e a 22 é a de
serviços. Se isso valer ao pé da letra, psicologia ABA precisa de autorização e fonoaudiologia ABA
não — o que é estranho para dois serviços do mesmo tratamento, no mesmo paciente.

Pode ser erro de digitação na planilha, ou pode ser real. Enquanto não for confirmado, **gravar o
que a planilha diz, por código**, e não uniformizar por conta própria — uniformizar esconderia o
problema em vez de resolvê-lo.

## 6. O que cadastrar agora

Com confiança:

- Psicologia ABA → `13107208`, tabela 00, R$ 150,00
- Fonoaudiologia ABA → `50005189`, tabela 22, R$ 150,00
- Regra do convênio: semanal, 5 por ciclo, `sessoes_por_guia` vazio

Pendente de decisão:

- Fisioterapia ABA → não cadastrar código até resolver a §2. Deixar em branco aqui **é** a
  informação correta, porque de fato não existe esse serviço no rol com esse nome.

## 7. Perguntas a acrescentar ao CAS

1. A clínica atende fisioterapia pelo método ABA. O rol não traz esse serviço na seção de
   fisioterapia, mas traz "Atendimento em Terapia Ocupacional pelo método ABA" (`50005170`). Qual
   código deve ser usado?
2. As sessões de método ABA para paciente com CID F84 são cobertas na fisioterapia? A diretriz 14
   não lista F84 entre os critérios.
3. A tabela de domínio de `13107208` (psicologia ABA) é 00 e a de `50005189` (fonoaudiologia ABA) é
   22. As duas estão corretas? Só a primeira exige autorização prévia?
4. O teto de 5 sessões por semana é por profissional, por especialidade ou por paciente somando
   todas as terapias?
5. Quantas sessões vêm em cada autorização, e qual a validade da senha?
6. A planilha vigente é a de 01/05/2026 (cabeçalho) ou 01/06/2026 (nome do arquivo)?

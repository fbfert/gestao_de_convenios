# SC Saúde — descoberta técnica para integração com o Gescon

> Levantamento iniciado em 05/09/2026 nas fontes públicas do plano.
> **Revisado em 15/09/2026** com a resposta do credenciamento e com a extração das tabelas.
> Continua em `02-cadastro-convenio.md` (como cadastrar) e `03-codigos-e-regras.md`
> (códigos, valores e diretrizes de cobertura).

## 1. Estado atual

| Frente | Situação |
|---|---|
| **WebService do prestador** | **Existe**, confirmado pelo credenciamento em 09/09/2026, mas há fila de implementação e a NeuroKids está na lista de espera, sem previsão. Em 17/09/2026 o credenciamento confirmou que **a documentação só é liberada quando a implementação for autorizada** — não há como adiantar o desenvolvimento durante a espera |
| Acesso ao Portal do Prestador | Pedido não respondido |
| Versão do padrão TISS aceita | Não confirmada. A FAQ cita 3.02.00 / 3.02.01, que são de 2015 e 2016 |
| Rol, códigos e valores das terapias | **Levantados** — ver `03-codigos-e-regras.md` |
| Regra de cobertura | **Levantada**: até 5 sessões por semana, com critério por CID |

O que mudou desde a primeira versão deste documento: em 05/09 a conclusão era "não existe API nem
webservice documentado, então automação significa robô". Isso está **superado**. Existe integração
oficial; o obstáculo é fila, não ausência de caminho.

Consequência prática: **não vale começar um robô de portal agora.** Ele nasceria com prazo de
validade — no dia em que a fila chamar, vira código a descartar. O trabalho que vale é o que serve
nos dois cenários, e é o que está descrito em `02` e `03`.

## 2. Documentação pública encontrada

Não há manual técnico, XSD próprio, swagger ou área do desenvolvedor. A única fonte com valor
técnico é a **FAQ do Portal do Prestador** (<https://portal.scsaude.sc.gov.br/duvidasFrequentes>):

| Item | Valor |
|---|---|
| Envio de produção | XML **ou** digitação no sistema — só um dos dois por competência |
| Versão TISS aceita | 3.02.00 ou 3.02.01 (anteriores não processam) |
| Registro ANS da operadora | `757444` |
| Número da carteira | 17 dígitos, com zero à esquerda: `0306XXXXXXXXXXXXX` |
| Código de procedimento | 8 dígitos, incluindo o DV |
| Tabelas de domínio | 18 diárias/taxas · 19 materiais/OPME · 20 medicamentos · **22 serviços** · 98 pacotes isentos de autorização prévia · **00 itens que exigem autorização** |
| Profissional solicitante | código 999 foi extinto — buscar o médico na base ou informar CRM + nome |
| Sessão do portal | expira com 20 min de inatividade |
| Legado | autorizações do SGP/AUTSC migradas preservando o número da guia |

Todos os códigos levantados em `03` têm 8 dígitos, o que confirma a regra acima.

Outras fontes: [rol de procedimentos](https://scsaude.sea.sc.gov.br/rol-de-procedimentos/) (7
planilhas, das quais a **Tabela de Honorários não médicos** é a que interessa),
[orientações para autorização](https://scsaude.sea.sc.gov.br/orientacoes-para-autorizacao/) (rotina
imediata, procedimento especial em até 5 dias úteis) e a
[FAQ do prestador](https://scsaude.sea.sc.gov.br/perguntas-frequentes-prestador/).

## 3. Cenário técnico — quatro sistemas, não um

| Sistema | URL | Stack verificada | Papel |
|---|---|---|---|
| Portal do Prestador | `portal.scsaude.sc.gov.br` | Java/JSP 2.2 atrás de nginx, jQuery + Bootstrap, `POST /login`, render server-side | operacional |
| Área do Segurado / login prestador | `segurado.scsaude.sc.gov.br/ords/...` | Oracle APEX/ORDS, app 109, login por CPF/CNPJ | portal novo |
| Credenciamento | `credencia.scsaude.sc.gov.br/login.seam` | JBoss Seam / JSF | credenciamento, autocadastro |
| Ocorrências | `portal.rbeneficiario.scsaude.sc.gov.br/sistemas` | — | chamados |

Coexistem **dois** "Portal do Prestador" e não dá para saber qual a clínica usa sem credencial. O
e-mail do contact center redireciona para `qualirede.com.br`, o que indica operação terceirizada.

Isso só volta a importar se a fila do WebService demorar a ponto de justificar um robô. Enquanto
isso, é contexto, não plano.

## 4. Como isso entra no Gescon

O que o sistema **já tem** e serve:

- `pacientes`: nome, cpf, data de nascimento, carteirinha, validade
- `guias`: número, senha, validade da senha, datas e histórico de status
- `lancamentos`: data da sessão, hora de início e fim
- `medicos`: nome, CRM e UF
- `convenios.carteirinha_blocos`: resolve a máscara de 17 dígitos, e é independente do conector
- `convenio_especialidade_mapeamentos`: é **aqui** que mora o código do procedimento por par
  convênio × especialidade, editado no cadastro da especialidade — não em `tabela_valores`
- `cid_solicitacao`: permite conferir na esteira o critério de elegibilidade por CID que as
  diretrizes do SC Saúde exigem

O que **falta**, e só vira bloqueante quando o faturamento entrar em cena:

| Gap | Onde | Quando pesa |
|---|---|---|
| Dados do prestador na operadora — código do contratado, CNES, CNPJ do executante | nova config por tenant × convênio | ao gerar o primeiro arquivo de produção |
| Lote de faturamento — número, competência, status, itens, hash | novas tabelas | idem |
| CBO e número/UF do conselho do profissional executante | `profissionais` só tem `conselho_registro` | idem |
| CNS do paciente | `pacientes` | se o SC Saúde exigir — pergunta em aberto |
| Retorno de glosa | reaproveitar o padrão de `AnaliticoUnimedImportService` | depois do primeiro envio |

Credencial de automação **não** é gap deste documento: é o change `credenciais-por-convenio`, que
generaliza a credencial da Unimed para qualquer convênio.

## 5. O que fazer, em ordem

1. **Cadastrar o convênio e operar no manual** — `02-cadastro-convenio.md`. Não depende de
   ninguém e já deixa a NeuroKids trabalhando o SC Saúde no Gescon.
2. **Resolver a pendência da fisioterapia ABA** — `03-codigos-e-regras.md` §2. É decisão de
   negócio com o CAS, e a diferença por sessão vai de R$ 150,00 a R$ 20,58.
3. **Implementar `credenciais-por-convenio`** — destrava o segundo convênio sem duplicar a
   estrutura da Unimed, e conserta a pausa do disjuntor que hoje alcança o tenant inteiro.
4. **Faturamento**, quando a versão do TISS estiver confirmada ou o WebService liberado. As
   estruturas de dados são as mesmas nos dois caminhos: o WebService TISS transporta as mesmas
   mensagens XML dentro de um envelope SOAP. Muda o transporte, não o conteúdo.
5. **Robô de portal** — só se a fila do WebService se arrastar **e** o volume manual estiver
   doendo. Escopo mínimo: autorização e captura de senha. Nunca faturamento.

## 6. Pendente de terceiros

- Documentação do WebService (WSDL, manual, versão TISS, autenticação, homologação) — **recusada
  por ora**: só sai com a autorização de início da implementação (resposta de 17/09/2026). Não
  reinsistir; o assunto volta quando a fila chamar.
- Acesso ao Portal do Prestador — solicitado, **nunca respondido**. É pedido de operação, não de
  integração, e não depende da fila do WebService: vale cobrar em separado.
- Código correto da fisioterapia para paciente com CID F84.
- Tabela de domínio divergente entre psicologia ABA (00) e fonoaudiologia ABA (22).
- Quantas sessões vêm por autorização e qual a validade da senha.

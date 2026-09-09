## Why

Hoje a guia guarda apenas o status atual. Não há como responder "quando esta guia foi negada?" — só "ela está negada". Isso já dói em duas frentes concretas: o card do dashboard precisa contar negações por data da transição (uma guia criada semana passada e negada hoje conta em "hoje"), e a `updated_at` não serve, porque qualquer edição a move.

O risco que mata a feature não é o modelo, é a dispersão da escrita. Levantamento feito neste repositório: **oito** pontos escrevem `guias.status` hoje.

| Onde | Quando |
|---|---|
| `GuiaService::criar` | criação manual |
| `GuiaService::finalizar` | → `finalized` |
| `GuiaService::negar` | → `denied` |
| `SolicitacaoService` (sincronização com guia) | criação/atualização a partir da solicitação |
| `GerarGuiaUnimedService::criarOuAtualizarGuia` | resultado da automação |
| `ConfirmarGuiaIncertaUnimedService::criarOuAtualizarGuia` | resultado da confirmação |
| `ConsultarStatusUnimedService::aplicarResultado` | consulta ao portal |
| `GuiaImportService::gravarGuia` | importação de planilha |

Se cada um continuar gravando por conta própria, o histórico nasce furado e o defeito só aparece meses depois, quando alguém for ler a série e ela não bater com a realidade.

## What Changes

- Criar a tabela `guia_status_historico`, com a transição, quem fez, de onde veio e por quê.
- Criar as colunas `negada_em` e `aprovada_em` em `guias`, como cache desnormalizado do último evento de cada tipo.
- Concentrar toda transição em `GuiaService::registrarTransicao()`, e migrar os oito pontos acima para ele.
- Travar a escrita de status fora desse método, com teste que comprova a trava.
- Entregar `guias:backfill-status-historico --dry-run`, best-effort, lendo `audit_logs`.

## Capabilities

### New Capabilities
- `guia-status-historico`: histórico de transições de status da guia e ponto único de escrita.

### Modified Capabilities

## Impact

- API Laravel: tabela nova, duas colunas em `guias`, um método novo em `GuiaService`, oito call sites migrados, um observer de trava e um command.
- Banco de dados: `guia_status_historico` e duas colunas em `guias`.
- Frontend: nenhum. O card que consome isto é a fase seguinte.

## Não-objetivos

- **Solicitação, Antecipação e Conciliação.** O mesmo padrão vale para as três depois, mas especificá-las agora triplicaria a superfície sem provar nada a mais.
- **Tabela polimórfica única** (`entidade`/`entidade_id`): mata chave estrangeira e índice. Cada entidade terá a sua.
- **Gráfico e métrica agregada.** Não há dado para isso — menos de um mês de operação. Fica para outro change.
- **Backfill completo e auditado.** É best-effort, com `origem='migracao'`, e não bloqueia a entrega se sair incompleto.

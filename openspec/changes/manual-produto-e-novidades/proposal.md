## Why

O manual é editável por tenant, e isso impede o que se quer construir: "atualizou o manual, virou novidade" só faz sentido se a novidade for release note do **produto**, e não a edição de texto de uma clínica. Enquanto o conteúdo pertence ao tenant, cada clínica tem um manual diferente e não há o que anunciar.

## Conferência feita antes de tocar em código

O plano exige verificar o conteúdo de `manuais` antes de remover a tabela — é o único ponto de todo o plano onde dá para perder dado de cliente. A consulta contra a base de produção restaurada:

| tenant | tipo | criado | alterado | bytes |
|---|---|---|---|---|
| 1 | `manual` | 12/08/2026 | **01/09/2026** | 90.872 |
| 1 | `mapa-mental` | 12/08/2026 | **31/08/2026** | 10.408 |

**A clínica editou os dois**, semanas depois da criação. A comparação com os arquivos semente do repositório confirma: o manual difere em ~5,7 KB e o mapa mental em ~208 bytes.

Portanto a remoção **não** é limpa, e o texto foi preservado antes de qualquer alteração: o conteúdo dos dois registros foi exportado para `api/resources/manual/manual.html` e `api/resources/manual/mapa-mental.html`, que passam a ser o que o produto serve. As sementes originais do repositório continuam versionadas como `default.html` e `mapa-mental-default.html`.

## What Changes

- Servir manual e mapa mental direto de `resources/manual/*.html`, versionados no git.
- Remover a edição pela interface: `ManualController::update`, `UpdateManualRequest`, a permissão `manual.manage`, os botões e o campo de edição.
- Dropar a tabela `manuais`, com `down()` que a recria.
- Novidades como arquivos markdown em `resources/novidades/`, com frontmatter.
- `novidade_leituras` para o card mostrar "não lidas" e parar de aparecer depois.
- Tela `/novidades` e card no dashboard.

## Capabilities

### New Capabilities
- `manual-produto-e-novidades`: manual como conteúdo do produto, somente leitura, e novidades versionadas com controle de leitura por usuário.

### Modified Capabilities

## Impact

- API Laravel: leitura de arquivo em vez de tabela, remoção de um endpoint, de um request, de uma permissão e de uma tabela; endpoints novos de novidades; uma tabela nova.
- Frontend React: `ManualPage` perde a edição; tela e card de novidades entram.
- Banco de dados: `manuais` sai, `novidade_leituras` entra.

## Não-objetivos

- CRUD de novidades. São arquivos no repositório; quando publicar sem deploy virar dor real, aí vira tabela.
- Gerar a novidade automaticamente a partir do diff do manual. Diff de HTML não vira texto legível, e novidade boa é escrita por gente.

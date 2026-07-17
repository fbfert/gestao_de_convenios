# Fluxo operacional de convênio

Documento-resumo da implementação da primeira fatia do fluxo operacional unificado de convênios.

## O que já foi entregue

- Solicitações e guias passaram a representar a mesma jornada operacional na interface.
- Os status operacionais foram traduzidos para o vocabulário do negócio na UI, mantendo a persistência técnica em inglês.
- O módulo de sessões foi consolidado sobre `lancamentos`, com:
  - cadastro manual da sessão
  - impressão de modelo em branco
  - importação por transcrição OCR/textual
  - pré-visualização antes da gravação
- A importação de transcrição agora funciona em duas etapas:
  - análise do texto
  - confirmação manual antes do envio
- Para carteirinhas/regiões com prefixo `0220`, a confirmação exige o PDF do registro de sessões.
- O painel de antecipações exibe alerta quando uma antecipação ativa não possui próximos agendamentos/sessões futuras.

## Regras aplicadas

- A primeira autorização continua sendo a guia aprovada.
- A continuidade do atendimento passa a ocorrer por antecipações.
- Antecipações só são consumidas quando uma sessão é registrada.
- A confirmação de envio do registro de sessões depende de revisão humana.
- O PDF do registro de sessões é obrigatório para a regional `0220`, detectada pelo número do cartão/carteirinha.

## Limitações atuais

- O sistema ainda não possui um módulo separado de agendamento.
- O alerta de continuidade é derivado das sessões registradas na antecipação.
- A importação do analítico da Unimed em Excel ainda não foi implementada.
- A conciliação financeira e o repasse por profissional ainda estão pendentes.

## Validações recentes

- `php artisan test --filter=LancamentosApiTest`
- `php artisan test --filter=AntecipacoesApiTest`
- `npm run lint`
- `npm run build`
- `openspec validate "fluxo-operacional-convenio" --type change --no-interactive`

import { useState } from 'react'
import { Botao } from '../components/ui/Botao'
import { Tooltip } from '../components/ui/Tooltip'

/**
 * Andaimes que existem SÓ no modo `e2e`.
 *
 * `import.meta.env.MODE` é resolvido em tempo de build, então no bundle de
 * produção estes componentes viram `null` e as rotas nunca são registradas —
 * verificado por teste (a string de erro não aparece em `dist/`).
 *
 * **Por que rotas próprias e não um `?simular-erro=1` em tela real:** um
 * componente de produção que sabe lançar é um componente de produção que pode
 * lançar. Aqui o caminho simplesmente não existe fora da suíte.
 */

const ativo = import.meta.env.MODE === 'e2e'

function Explode(): never {
  throw new Error('Erro simulado para o teste da tela de erro')
}

/**
 * Um `Botao` de verdade cujo carregamento fica ligado e não volta.
 *
 * Existe porque o gatilho da tela branca depende de o spinner ser INSERIDO
 * enquanto o rótulo já está na tela: é aí que o React chama
 * `insertBefore(spinner, rótulo)`. Nas telas reais isso é difícil de provocar
 * de forma estável — o "Sair", por exemplo, desmonta a tela antes de
 * re-renderizar com `carregando`, e foi por isso que a primeira versão deste
 * teste passava mesmo SEM a correção do `Botao`.
 *
 * Aqui o estado é ligado pelo clique e nunca desligado, então o momento do
 * `insertBefore` é garantido.
 */
function BotaoQueCarrega() {
  const [carregando, setCarregando] = useState(false)

  return (
    <Botao
      variante="primario"
      carregando={carregando}
      onClick={() => setCarregando(true)}
      data-testid="botao-que-carrega"
    >
      Salvar alterações
    </Botao>
  )
}

/**
 * Um `Tooltip` de verdade encostado na borda direita, numa coordenada
 * fracionária.
 *
 * Reproduz o erro 185 do React ("Maximum update depth exceeded") que derrubou
 * a tela de Solicitações em 24 e 25/09/2026: o tooltip media o próprio painel
 * para caber na viewport e gravava o deslocamento no estado — e, quando o
 * painel precisa se deslocar e a posição tem fração de pixel (zoom do
 * navegador, Windows a 125%), a medição seguinte não devolve exatamente o
 * mesmo número, o efeito grava de novo, e assim para sempre.
 *
 * O `paddingLeft` em `calc(100vw - 40.4px)` põe o gatilho a 40,4px da borda:
 * o painel de 288px tem de se deslocar, e a fração é o que faz a conta
 * oscilar. O teste roda com `deviceScaleFactor` fracionário para garantir que
 * a coordenada não caia num inteiro por acaso.
 */
function TooltipNaBorda() {
  return (
    <div style={{ paddingLeft: 'calc(100vw - 40.4px)' }} data-testid="tooltip-na-borda">
      <Tooltip rotulo="Dica na borda">
        Um texto comprido o bastante para o painel precisar se deslocar para caber na tela sem
        estourar a margem direita.
      </Tooltip>
    </div>
  )
}

/** `null` fora do modo e2e: quem monta a rota decide pelo valor. */
export const RotaDeErroSimulado = ativo ? Explode : null

/** Idem. Ver TooltipNaBorda. */
export const RotaDeTooltipNaBorda = ativo ? TooltipNaBorda : null

/** Idem. Ver BotaoQueCarrega. */
export const RotaDeBotaoCarregando = ativo ? BotaoQueCarrega : null

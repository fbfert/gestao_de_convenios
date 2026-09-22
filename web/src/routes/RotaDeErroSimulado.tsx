import { useState } from 'react'
import { Botao } from '../components/ui/Botao'

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

/** `null` fora do modo e2e: quem monta a rota decide pelo valor. */
export const RotaDeErroSimulado = ativo ? Explode : null

/** Idem. Ver BotaoQueCarrega. */
export const RotaDeBotaoCarregando = ativo ? BotaoQueCarrega : null

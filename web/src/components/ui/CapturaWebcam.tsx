import { useCallback, useEffect, useRef, useState } from 'react'
import { Botao } from './Botao'

/**
 * Captura de foto pela webcam da página.
 *
 * Existe porque o `capture="environment"` do `<input type="file">` só vale no
 * celular: no computador o atributo é ignorado e o clique vira seletor de
 * arquivo. Sem isto, quem trabalha no computador precisa fotografar no
 * celular, mandar para si mesmo e baixar — com a webcam ligada na mesa.
 *
 * Devolve um `File` JPEG e nada mais: quem chama decide o que fazer com ele,
 * então a foto entra no MESMO caminho de quem escolheu um arquivo, sem uma
 * segunda rota nem um segundo formato para manter.
 */

/**
 * Se dá para oferecer o botão.
 *
 * `mediaDevices` só existe em contexto seguro — HTTPS, `localhost` ou
 * `127.0.0.1`. Abrir o ambiente de desenvolvimento pelo IP da rede em `http://`
 * deixa isto indefinido, e aí o botão não deve aparecer em vez de aparecer e
 * falhar no clique.
 */
export function webcamDisponivel() {
  return typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices?.getUserMedia)
}

type CapturaWebcamProps = {
  /** Chamado com a foto confirmada. O componente não envia nada sozinho. */
  onCapturar: (arquivo: File) => void
  /** Fechar a câmera — o componente já parou as trilhas quando chama. */
  onFechar: () => void
  onErro: (mensagem: string) => void
  /** Nome do arquivo gerado. Precisa terminar em `.jpg`: a API valida por extensão. */
  nomeArquivo: string
  /**
   * Largura máxima da imagem enviada. Acima disso só cresce o upload sem
   * ganhar legibilidade — mas abaixo do necessário a IA perde texto pequeno.
   */
  larguraMaxima?: number
  /**
   * Congela o quadro capturado para conferência antes de entregá-lo.
   *
   * Vale quando a leitura é cara: uma foto tremida gasta a chamada de IA e a
   * espera antes de alguém descobrir que não deu. Desligado por padrão para
   * não mudar quem já capturava e enviava direto.
   */
  conferirAntesDeEnviar?: boolean
  /** Instrução de enquadramento mostrada ao lado dos botões. */
  dica?: string
  /** Rótulo do botão de captura. Muda quando o clique já dispara a leitura. */
  rotuloCapturar?: string
  /** Prefixo dos `data-testid` (`{prefixo}-preview`, `-capturar`, `-usar`, `-repetir`). */
  testIdPrefixo: string
}

export function CapturaWebcam({
  onCapturar,
  onFechar,
  onErro,
  nomeArquivo,
  larguraMaxima = 1600,
  conferirAntesDeEnviar = false,
  dica,
  rotuloCapturar = 'Tirar foto',
  testIdPrefixo,
}: CapturaWebcamProps) {
  const videoRef = useRef<HTMLVideoElement | null>(null)
  const streamRef = useRef<MediaStream | null>(null)
  const [pronta, setPronta] = useState(false)
  const [previa, setPrevia] = useState<{ url: string; arquivo: File } | null>(null)

  /**
   * Liga o stream ao `<video>` toda vez que ele aparece, e não uma vez só
   * quando a permissão é concedida.
   *
   * O elemento DESMONTA quando a prévia congelada entra no lugar dele, e
   * volta em "Tirar outra" — um elemento novo, com `srcObject` vazio. Atribuir
   * no `.then()` do `getUserMedia` acertaria só a primeira montagem, e a
   * segunda captura mostraria um quadro preto sem erro nenhum.
   */
  const ligarVideo = useCallback((node: HTMLVideoElement | null) => {
    videoRef.current = node

    if (node && streamRef.current) {
      node.srcObject = streamRef.current
    }
  }, [])

  const pararTrilhas = () => {
    // Parar as trilhas é o que apaga a luz da webcam. Sem isso ela fica ligada
    // até a aba ser fechada, o que assusta com razão.
    streamRef.current?.getTracks().forEach((track) => track.stop())
    streamRef.current = null
  }

  const fechar = () => {
    pararTrilhas()
    onFechar()
  }

  // Abre ao montar: o componente só existe enquanto a câmera está em uso, então
  // montar É abrir. Quem chama controla isso mostrando ou não o componente.
  useEffect(() => {
    let cancelado = false

    navigator.mediaDevices
      .getUserMedia({
        // `ideal` e não `exact`: no celular pega a câmera traseira, no
        // computador aceita a única que existe em vez de falhar.
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 } },
        audio: false,
      })
      .then((stream) => {
        // Desmontou durante o await — sem isto o stream fica órfão, com a luz
        // acesa e sem ninguém para pará-lo.
        if (cancelado) {
          stream.getTracks().forEach((track) => track.stop())

          return
        }

        streamRef.current = stream
        if (videoRef.current) {
          videoRef.current.srcObject = stream
        }
        // `pronta` NÃO sobe aqui: ter o stream não é ter imagem. O
        // `videoWidth` só existe depois do `loadedmetadata`, e capturar antes
        // disso sai por um `return` silencioso — botão habilitado que não faz
        // nada ao clique.
      })
      .catch(() => {
        if (cancelado) return

        onErro(
          'Não foi possível acessar a webcam. Verifique a permissão da câmera no navegador, ou escolha um arquivo.',
        )
        onFechar()
      })

    return () => {
      cancelado = true
      pararTrilhas()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // A prévia é um object URL: sem revogar, cada foto descartada vaza um blob
  // que só some quando a aba fecha.
  useEffect(() => {
    return () => {
      if (previa) URL.revokeObjectURL(previa.url)
    }
  }, [previa])

  const capturar = () => {
    const video = videoRef.current

    if (!video || !video.videoWidth) {
      return
    }

    const escala = Math.min(1, larguraMaxima / video.videoWidth)
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(video.videoWidth * escala)
    canvas.height = Math.round(video.videoHeight * escala)
    canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height)

    canvas.toBlob(
      (blob) => {
        if (!blob) {
          onErro('Não foi possível capturar a imagem da webcam.')

          return
        }

        const arquivo = new File([blob], nomeArquivo, { type: 'image/jpeg' })

        if (!conferirAntesDeEnviar) {
          pararTrilhas()
          onFechar()
          onCapturar(arquivo)

          return
        }

        // A câmera segue ligada: "Tirar outra" precisa voltar ao vivo sem pedir
        // permissão de novo. `pronta` volta a false porque o `<video>` sai da
        // tela — quando voltar, é um elemento novo, que precisa carregar os
        // metadados de novo antes de poder ser capturado.
        setPronta(false)
        setPrevia({ url: URL.createObjectURL(blob), arquivo })
      },
      'image/jpeg',
      0.92,
    )
  }

  const usarPrevia = () => {
    if (!previa) return

    pararTrilhas()
    onFechar()
    onCapturar(previa.arquivo)
  }

  return (
    <div className="space-y-3 rounded-2xl border border-white/10 bg-slate-950/60 p-3">
      {previa ? (
        <img
          src={previa.url}
          alt="Foto capturada, para conferência antes do envio"
          className="max-h-80 w-full rounded-xl bg-black object-contain"
          data-testid={`${testIdPrefixo}-previa`}
        />
      ) : (
        <video
          ref={ligarVideo}
          autoPlay
          playsInline
          muted
          onLoadedMetadata={() => setPronta(true)}
          className="max-h-80 w-full rounded-xl bg-black object-contain"
          data-testid={`${testIdPrefixo}-preview`}
        />
      )}

      <div className="flex flex-wrap items-center gap-3">
        {previa ? (
          <>
            <Botao variante="primario" onClick={usarPrevia} data-testid={`${testIdPrefixo}-usar`}>
              Usar esta foto
            </Botao>
            <Botao
              type="button"
              variante="secundario"
              onClick={() => setPrevia(null)}
              data-testid={`${testIdPrefixo}-repetir`}
            >
              Tirar outra
            </Botao>
          </>
        ) : (
          <Botao
            variante="primario"
            onClick={capturar}
            disabled={!pronta}
            data-testid={`${testIdPrefixo}-capturar`}
          >
            {rotuloCapturar}
          </Botao>
        )}

        <button
          type="button"
          onClick={fechar}
          className="inline-flex min-h-6 items-center text-corpo text-slate-300"
          data-testid={`${testIdPrefixo}-cancelar`}
        >
          Cancelar
        </button>

        <span className="text-meta text-slate-400">
          {!pronta && !previa
            ? 'Abrindo a câmera… aceite a permissão do navegador.'
            : previa
              ? 'Confira se está legível antes de enviar.'
              : dica}
        </span>
      </div>
    </div>
  )
}

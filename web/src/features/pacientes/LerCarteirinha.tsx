import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { getHttpErrorMessage, useLerCarteirinha } from './usePacientes'
import type { LeituraCarteirinha } from './types'

/** Largura máxima da foto enviada. Acima disso só cresce o upload. */
const LARGURA_MAXIMA = 1600

function webcamDisponivel() {
  return typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices?.getUserMedia)
}

/**
 * Leitura da carteirinha, por arquivo, câmera do celular ou webcam.
 *
 * São dois caminhos de propósito. No celular, `capture` abre a câmera do
 * sistema, que já é a melhor experiência. No computador esse atributo é
 * ignorado e o clique vira seletor de arquivo — daí a webcam, que captura o
 * quadro na própria página.
 *
 * Nada é gravado aqui: a leitura preenche o formulário e quem confere é o
 * operador, com a carteirinha na mão.
 */
export function LerCarteirinha({
  onLeitura,
  onEscolherConvenio,
}: {
  onLeitura: (leitura: LeituraCarteirinha) => void
  onEscolherConvenio: (convenioId: number) => void
}) {
  const inputRef = useRef<HTMLInputElement | null>(null)
  const videoRef = useRef<HTMLVideoElement | null>(null)
  const streamRef = useRef<MediaStream | null>(null)
  const [camera, setCamera] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const [aviso, setAviso] = useState<string | null>(null)
  const [convenioLido, setConvenioLido] = useState<LeituraCarteirinha['convenio'] | null>(null)
  const ler = useLerCarteirinha()

  const fecharCamera = () => {
    // Parar as trilhas é o que apaga a luz da webcam. Sem isso ela fica ligada
    // até a aba ser fechada, o que assusta com razão.
    streamRef.current?.getTracks().forEach((track) => track.stop())
    streamRef.current = null
    setCamera(false)
  }

  useEffect(() => fecharCamera, [])

  const abrirCamera = async () => {
    setErro(null)
    setAviso(null)

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        // `ideal` e não `exact`: no celular pega a câmera traseira, no
        // computador aceita a única que existe em vez de falhar.
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 } },
        audio: false,
      })

      streamRef.current = stream
      setCamera(true)
    } catch {
      setErro(
        'Não foi possível acessar a webcam. Verifique a permissão da câmera no navegador ou use "Escolher arquivo".',
      )
    }
  }

  // O stream entra por propriedade, não por atributo: só dá para ligá-lo
  // depois que o <video> existe na tela.
  useEffect(() => {
    if (camera && videoRef.current && streamRef.current) {
      videoRef.current.srcObject = streamRef.current
    }
  }, [camera])

  const capturar = () => {
    const video = videoRef.current

    if (!video || !video.videoWidth) {
      return
    }

    const escala = Math.min(1, LARGURA_MAXIMA / video.videoWidth)
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(video.videoWidth * escala)
    canvas.height = Math.round(video.videoHeight * escala)
    canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height)

    canvas.toBlob(
      (blob) => {
        if (!blob) {
          setErro('Não foi possível capturar a imagem da webcam.')

          return
        }

        fecharCamera()
        void enviar(new File([blob], 'carteirinha.jpg', { type: 'image/jpeg' }))
      },
      'image/jpeg',
      0.9,
    )
  }

  const enviar = async (arquivo: File | undefined) => {
    if (!arquivo) {
      return
    }

    setErro(null)
    setAviso(null)
    setConvenioLido(null)

    try {
      const leitura = await ler.mutateAsync(arquivo)
      const { dados, convenio } = leitura

      const nadaLido = !dados.carteirinha && !dados.nome && !dados.cpf && !dados.data_nascimento

      if (nadaLido) {
        setAviso(
          'Não consegui reconhecer nada nesta imagem. Tente uma foto mais nítida, com o cartão preenchendo o quadro.',
        )
      }

      // O painel abaixo mostra a nota de cada convênio próximo. Preencher por
      // semelhança seria pior que deixar em branco: o convênio manda no
      // formato da carteirinha, nas regras e no valor pago.
      if (convenio.lido) {
        setConvenioLido(convenio)
      }

      onLeitura(leitura)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível ler a carteirinha.'))
    } finally {
      // Sem isso, escolher o mesmo arquivo de novo não dispara o evento.
      if (inputRef.current) {
        inputRef.current.value = ''
      }
    }
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center gap-3">
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          disabled={ler.isPending || camera}
          className="inline-flex items-center gap-2 rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
          data-testid="paciente-ler-carteirinha"
        >
          <svg aria-hidden="true" viewBox="0 0 20 20" className="h-4 w-4">
            <rect x="1.5" y="4" width="17" height="12" rx="2" fill="none" stroke="currentColor" strokeWidth="1.6" />
            <circle cx="6.5" cy="9" r="1.8" fill="none" stroke="currentColor" strokeWidth="1.6" />
            <path
              d="M11 8.5h5M11 12h5M3 13.5c1-1.6 2.4-1.6 3.5-1.6s2.5 0 3.5 1.6"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.6"
              strokeLinecap="round"
            />
          </svg>
          {ler.isPending ? 'Lendo carteirinha...' : 'Ler Carteirinha'}
        </button>

        {webcamDisponivel() ? (
          <button
            type="button"
            onClick={camera ? fecharCamera : abrirCamera}
            disabled={ler.isPending}
            className="inline-flex items-center gap-2 rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-3 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:opacity-60"
            data-testid="paciente-webcam"
          >
            <svg aria-hidden="true" viewBox="0 0 20 20" className="h-4 w-4">
              <path
                d="M2.5 6.5h3l1.2-1.8h6.6L14.5 6.5h3v9h-15z"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinejoin="round"
              />
              <circle cx="10" cy="11" r="3" fill="none" stroke="currentColor" strokeWidth="1.5" />
            </svg>
            {camera ? 'Fechar webcam' : 'Usar webcam'}
          </button>
        ) : null}

        <span className="text-xs text-slate-400">
          Foto, arquivo ou webcam. Os dados lidos vêm para conferência antes de salvar.
        </span>
      </div>

      <input
        ref={inputRef}
        type="file"
        accept="image/*,application/pdf"
        capture="environment"
        onChange={(event) => enviar(event.target.files?.[0])}
        className="hidden"
        data-testid="paciente-carteirinha-arquivo"
      />

      {camera ? (
        <div className="space-y-3 rounded-2xl border border-white/10 bg-slate-950/60 p-3">
          <video
            ref={videoRef}
            autoPlay
            playsInline
            muted
            className="max-h-80 w-full rounded-xl bg-black object-contain"
            data-testid="paciente-webcam-preview"
          />

          <div className="flex flex-wrap items-center gap-3">
            <button
              type="button"
              onClick={capturar}
              className="rounded-2xl bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
              data-testid="paciente-webcam-capturar"
            >
              Tirar foto e ler
            </button>
            <button type="button" onClick={fecharCamera} className="text-sm text-slate-300">
              Cancelar
            </button>
            <span className="text-xs text-slate-400">
              Encoste o cartão no quadro, sem reflexo, e mantenha o número legível.
            </span>
          </div>
        </div>
      ) : null}

      {/*
        Aviso de progresso destacado. Sem ele, uma leitura que demora parece
        travada e o operador clica de novo — o que so faz comecar outra leitura
        e gastar outra chamada.
      */}
      {ler.isPending ? (
        <div
          className="flex items-center gap-3 rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-50"
          role="status"
          aria-live="polite"
          data-testid="paciente-carteirinha-lendo"
        >
          <span className="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-cyan-200/40 border-t-cyan-100" />
          <span>
            <strong className="font-semibold">Lendo a carteirinha…</strong> costuma levar de 5 a 20
            segundos. Não feche a tela nem clique de novo — os campos serão preenchidos sozinhos.
          </span>
        </div>
      ) : null}

      {convenioLido ? (
        <div
          className="space-y-3 rounded-2xl border border-white/10 bg-slate-950/60 p-4"
          data-testid="paciente-convenio-lido"
        >
          <p className="text-sm text-slate-200">
            Operadora lida no cartão: <strong className="text-white">{convenioLido.lido}</strong>
            {convenioLido.id ? (
              <span className="text-emerald-200"> · convênio preenchido automaticamente</span>
            ) : (
              <span className="text-amber-100"> · nenhum convênio bateu com segurança</span>
            )}
          </p>

          {convenioLido.candidatos.length > 0 ? (
            <div className="space-y-2">
              <p className="text-xs uppercase tracking-[0.25em] text-slate-400">
                Proximidade com os convênios cadastrados
              </p>

              {convenioLido.candidatos.map((candidato) => (
                <div
                  key={candidato.id}
                  className="flex flex-wrap items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-2"
                >
                  <span className="min-w-32 text-sm text-white">{candidato.nome}</span>

                  <span className="h-2 w-24 overflow-hidden rounded-full bg-white/10">
                    <span
                      className={candidato.similaridade >= 85 ? 'block h-full bg-emerald-300' : 'block h-full bg-amber-300'}
                      style={{ width: `${Math.min(100, candidato.similaridade)}%` }}
                    />
                  </span>

                  <span className="text-xs font-semibold text-slate-200">
                    {candidato.similaridade.toFixed(0)}%
                  </span>

                  <button
                    type="button"
                    onClick={() => onEscolherConvenio(candidato.id)}
                    className="ml-auto rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
                    data-testid={`paciente-convenio-usar-${candidato.id}`}
                  >
                    Usar este
                  </button>
                </div>
              ))}
            </div>
          ) : null}

          <div className="flex flex-wrap items-center gap-3">
            {/*
              Aba nova de proposito: navegar aqui dentro descartaria o
              formulario meio preenchido e a leitura recem-feita.
            */}
            <Link
              to="/convenios/novo"
              target="_blank"
              rel="noreferrer"
              className="rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-2 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
              data-testid="paciente-convenio-cadastrar"
            >
              Cadastrar novo convênio
            </Link>
            <span className="text-xs text-slate-400">
              Abre em outra aba. Ao voltar, a lista de convênios se atualiza sozinha.
            </span>
          </div>
        </div>
      ) : null}

      {aviso ? (
        <p className="rounded-2xl border border-amber-300/20 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">
          {aviso}
        </p>
      ) : null}

      {erro ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
          {erro}
        </p>
      ) : null}
    </div>
  )
}

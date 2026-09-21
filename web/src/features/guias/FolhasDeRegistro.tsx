import { useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { Botao } from '../../components/ui/Botao'
import { getHttpErrorMessage } from '../../lib/httpError'
import { usePode } from '../../lib/permissoes'

/**
 * As folhas de registro de sessões de uma guia.
 *
 * Uma guia de dez sessões costuma ser impressa em duas vias e preenchida em
 * partes, então a segunda folha quase sempre chega depois da primeira. Todas
 * vão para a operadora na finalização — por isso elas moram aqui, na guia, e
 * não só na pasta do paciente: é olhando a guia que alguém decide se já dá
 * para finalizar.
 */

type Folha = {
  id: number
  nome_original: string
  mime: string
  enviado_em: string | null
  enviado_por: string | null
  download_url: string
}

function useFolhas(guiaId: number) {
  return useQuery({
    queryKey: ['guias', guiaId, 'folhas-registro'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: Folha[] }>(`/guias/${guiaId}/folhas-registro`)
      return data.data
    },
  })
}

function useAnexarFolhas(guiaId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (arquivos: File[]) => {
      const body = new FormData()
      arquivos.forEach((arquivo) => body.append('arquivos[]', arquivo))

      const { data } = await apiClient.post<{ data: Folha[] }>(
        `/guias/${guiaId}/folhas-registro`,
        body,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )

      return data.data
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['guias', guiaId, 'folhas-registro'] })
    },
  })
}

function useRemoverFolha(guiaId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (folhaId: number) => {
      await apiClient.delete(`/guias/${guiaId}/folhas-registro/${folhaId}`)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['guias', guiaId, 'folhas-registro'] })
    },
  })
}

function formatarEnvio(folha: Folha): string {
  const quando = folha.enviado_em ? new Date(folha.enviado_em).toLocaleDateString('pt-BR') : null

  if (quando && folha.enviado_por) {
    return `${quando} · ${folha.enviado_por}`
  }

  return quando ?? folha.enviado_por ?? ''
}

export function FolhasDeRegistro({ guiaId, guiaFinalizada }: { guiaId: number; guiaFinalizada: boolean }) {
  const pode = usePode()
  const folhasQuery = useFolhas(guiaId)
  const anexar = useAnexarFolhas(guiaId)
  const remover = useRemoverFolha(guiaId)
  const inputRef = useRef<HTMLInputElement>(null)
  const [erro, setErro] = useState<string | null>(null)

  const folhas = folhasQuery.data ?? []

  const enviar = async (arquivos: FileList | null) => {
    setErro(null)

    if (!arquivos || arquivos.length === 0) {
      return
    }

    try {
      await anexar.mutateAsync(Array.from(arquivos))
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível anexar a folha.'))
    } finally {
      // Sem isto, escolher o mesmo arquivo de novo não dispara `change`.
      if (inputRef.current) {
        inputRef.current.value = ''
      }
    }
  }

  const excluir = async (folha: Folha) => {
    setErro(null)

    try {
      await remover.mutateAsync(folha.id)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível remover a folha.'))
    }
  }

  return (
    <section
      className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6"
      data-testid="guia-folhas-registro"
    >
      <h3 className="text-subtitulo font-semibold text-white">Folhas de registro</h3>
      <p className="mt-1 text-corpo text-slate-300">
        As folhas assinadas desta guia. Uma guia costuma ser impressa em duas vias e preenchida em
        partes — todas as folhas anexadas aqui vão para a operadora na finalização.
      </p>

      {folhasQuery.isLoading ? (
        <p className="mt-4 text-corpo text-slate-400">Carregando...</p>
      ) : folhas.length === 0 ? (
        <p className="mt-4 text-corpo text-slate-400" data-testid="guia-folhas-vazio">
          Nenhuma folha anexada.
        </p>
      ) : (
        <ul className="mt-4 space-y-2">
          {folhas.map((folha) => (
            <li
              key={folha.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-superficie border border-linha bg-fundo px-4 py-3"
              data-testid={`guia-folha-${folha.id}`}
            >
              <div>
                <p className="text-corpo font-semibold text-white">{folha.nome_original}</p>
                <p className="text-meta text-slate-400">{formatarEnvio(folha)}</p>
              </div>

              {pode('lancamentos.manage') && !guiaFinalizada ? (
                <button
                  type="button"
                  onClick={() => void excluir(folha)}
                  disabled={remover.isPending}
                  className="rounded-full border border-rose-400/30 bg-rose-400/10 px-3 py-1.5 text-meta font-semibold text-rose-100 transition hover:bg-rose-400/20 disabled:cursor-not-allowed disabled:opacity-50"
                  data-testid={`guia-folha-remover-${folha.id}`}
                >
                  Remover
                </button>
              ) : null}
            </li>
          ))}
        </ul>
      )}

      {guiaFinalizada ? (
        <p className="mt-4 text-meta text-slate-400">
          A guia já foi finalizada na operadora — as folhas são o comprovante do envio e não podem
          mais ser alteradas.
        </p>
      ) : (
        <div className="mt-4 flex flex-wrap items-center gap-3">
          <input
            ref={inputRef}
            type="file"
            multiple
            accept="application/pdf,image/jpeg,image/png"
            onChange={(event) => void enviar(event.target.files)}
            className="hidden"
            data-testid="guia-folha-input"
          />
          <Botao
            variante="secundario"
            onClick={() => inputRef.current?.click()}
            disabled={anexar.isPending}
            data-testid="guia-folha-anexar"
          >
            {anexar.isPending ? 'Anexando...' : 'Anexar folha'}
          </Botao>
        </div>
      )}

      {erro ? (
        <p className="mt-3 text-corpo text-rose-300" data-testid="guia-folha-erro">
          {erro}
        </p>
      ) : null}
    </section>
  )
}

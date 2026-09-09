import { Botao } from '../../components/ui/Botao'
import { ETIQUETA_TIPO, ROTULO_TIPO } from './tipo'
import { useMarcarNovidadeLida, useNovidades } from './useNovidades'

function formatarData(valor: string) {
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long' }).format(new Date(`${valor}T00:00:00`))
}

/**
 * Novidades do produto.
 *
 * O conteúdo vem de arquivos markdown versionados no repositório, escritos no
 * mesmo commit da mudança que anunciam. Não há tela de admin de propósito:
 * quando publicar sem deploy virar dor real, aí vira CRUD.
 */
export function NovidadesPage() {
  const novidadesQuery = useNovidades()
  const marcarLida = useMarcarNovidadeLida()

  const novidades = novidadesQuery.data?.data ?? []
  const naoLidas = novidadesQuery.data?.meta.nao_lidas ?? 0

  return (
    <div className="space-y-6" data-testid="novidades-page">
      <div>
        <p className="text-meta font-semibold uppercase tracking-[0.2em] text-acento">Novidades</p>
        <h2 className="mt-2 text-titulo font-semibold text-texto">O que mudou no sistema</h2>
        {naoLidas > 0 ? (
          <p className="mt-1 text-corpo text-texto-suave">
            {naoLidas === 1 ? '1 novidade não lida' : `${naoLidas} novidades não lidas`}
          </p>
        ) : null}
      </div>

      {novidadesQuery.isLoading ? (
        <p className="rounded-janela border border-linha bg-superficie p-6 text-corpo text-texto-suave">
          Carregando novidades...
        </p>
      ) : novidades.length === 0 ? (
        <p className="rounded-janela border border-linha bg-superficie p-6 text-corpo text-texto-suave">
          Nenhuma novidade publicada ainda.
        </p>
      ) : (
        <ul className="space-y-4">
          {novidades.map((novidade) => (
            <li
              key={novidade.slug}
              className="rounded-janela border border-linha bg-superficie p-5 shadow-e1"
              data-testid={`novidade-${novidade.slug}`}
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <span
                      className={`rounded-pilula px-2 py-1 text-meta font-semibold ${ETIQUETA_TIPO[novidade.tipo]}`}
                    >
                      {ROTULO_TIPO[novidade.tipo]}
                    </span>
                    <span className="text-meta text-texto-suave">{formatarData(novidade.data)}</span>
                    {!novidade.lida ? (
                      <span className="rounded-pilula bg-acento-suave px-2 py-1 text-meta font-semibold text-acento-intenso">
                        Não lida
                      </span>
                    ) : null}
                  </div>
                  <h3 className="text-subtitulo font-semibold text-texto">{novidade.titulo}</h3>
                </div>

                {!novidade.lida ? (
                  <Botao
                    variante="secundario"
                    tamanho="sm"
                    onClick={() => marcarLida.mutate(novidade.slug)}
                    data-testid={`novidade-marcar-lida-${novidade.slug}`}
                  >
                    Marcar como lida
                  </Botao>
                ) : null}
              </div>

              {/* Texto simples, com as quebras preservadas. Renderizar markdown
                  exigiria uma dependência nova para um conteúdo que hoje é
                  parágrafo e lista curta. */}
              <p className="mt-3 whitespace-pre-wrap text-corpo leading-6 text-texto-suave">
                {novidade.corpo}
              </p>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

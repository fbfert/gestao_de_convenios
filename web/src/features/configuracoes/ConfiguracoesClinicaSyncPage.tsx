import {
  getHttpErrorMessage,
  useClinicaSyncStatus,
  useSincronizarClinicaAgora,
  type ClinicaSyncResumoEntidade,
} from './useClinicaSync'
import {
  useClinicaSyncPendencias,
  useConfirmarPendencia,
  useRejeitarPendencia,
  type ClinicaPacientePendencia,
} from './useClinicaSyncPendencias'
import { Botao } from '../../components/ui/Botao'
import { Badge, type BadgeProps } from '../../components/ui/Badge'

function formatarData(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('pt-BR')
}

function badgeTone(status: 'ok' | 'error' | null): NonNullable<BadgeProps['tone']> {
  if (status === 'ok') return 'sucesso'
  if (status === 'error') return 'perigo'
  return 'neutro'
}

function BlocoEntidade({ titulo, resumo }: { titulo: string; resumo: ClinicaSyncResumoEntidade | undefined }) {
  if (!resumo) return null

  return (
    <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1">
      <p className="text-corpo font-semibold text-white">{titulo}</p>
      <p className="mt-1 text-meta text-slate-400">
        criados {resumo.criados} · atualizados {resumo.atualizados}
        {resumo.ignorados !== undefined ? ` · sem mudança ${resumo.ignorados}` : ''}
      </p>
      {resumo.pendentes.length > 0 ? (
        <ul className="mt-2 space-y-1 text-meta text-amber-200">
          {resumo.pendentes.map((linha, indice) => (
            <li key={indice}>⚠ {linha}</li>
          ))}
        </ul>
      ) : null}
    </div>
  )
}

function CardPendencia({ pendencia }: { pendencia: ClinicaPacientePendencia }) {
  const confirmar = useConfirmarPendencia()
  const rejeitar = useRejeitarPendencia()
  const carregando = confirmar.isPending || rejeitar.isPending

  return (
    <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1" data-testid="clinica-sync-pendencia">
      <p className="text-corpo font-semibold text-white">
        {pendencia.nome_remoto} <span className="text-meta text-slate-400">(vindo do clinica)</span>
      </p>
      <p className="mt-1 text-meta text-slate-400">
        Parece já estar cadastrado no gescon. Confirme quem é, ou diga que é gente diferente.
      </p>

      <div className="mt-3 flex flex-wrap gap-2">
        {pendencia.candidatos.map((candidato) => (
          <button
            key={candidato.id}
            type="button"
            disabled={carregando}
            onClick={() => confirmar.mutate({ pendenciaId: pendencia.id, pacienteId: candidato.id })}
            className="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:opacity-50"
            data-testid="clinica-sync-pendencia-candidato"
          >
            Vincular a #{candidato.id} {candidato.nome}
            {candidato.carteirinha ? ` · ${candidato.carteirinha}` : ''} · {candidato.similaridade}%
          </button>
        ))}
      </div>

      <div className="mt-3">
        <Botao
          type="button"
          variante="secundario"
          tamanho="sm"
          carregando={rejeitar.isPending}
          disabled={carregando}
          onClick={() => rejeitar.mutate(pendencia.id)}
          data-testid="clinica-sync-pendencia-rejeitar"
        >
          Não é a mesma pessoa, cadastrar novo
        </Botao>
      </div>

      {confirmar.isError ? (
        <p className="mt-2 text-meta text-rose-300">
          {getHttpErrorMessage(confirmar.error, 'Não foi possível confirmar o vínculo.')}
        </p>
      ) : null}
      {rejeitar.isError ? (
        <p className="mt-2 text-meta text-rose-300">
          {getHttpErrorMessage(rejeitar.error, 'Não foi possível rejeitar a pendência.')}
        </p>
      ) : null}
    </div>
  )
}

function SecaoPendencias() {
  const query = useClinicaSyncPendencias()

  if (query.isPending) return <p className="text-corpo text-slate-400">Carregando pendências...</p>
  if (query.isError) {
    return (
      <p className="text-corpo text-rose-300">
        {getHttpErrorMessage(query.error, 'Não foi possível carregar as pendências.')}
      </p>
    )
  }
  if (query.data.length === 0) return null

  return (
    <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
      <h3 className="text-subtitulo font-semibold text-white">Pendências de vinculação</h3>
      <p className="mt-1 text-meta text-slate-400">
        Pacientes que chegaram do clinica com nome parecido a alguém já cadastrado no gescon —
        nunca vinculamos sozinhos, confirme abaixo.
      </p>
      <div className="mt-4 space-y-3">
        {query.data.map((pendencia) => (
          <CardPendencia key={pendencia.id} pendencia={pendencia} />
        ))}
      </div>
    </section>
  )
}

export function ConfiguracoesClinicaSyncPage() {
  const query = useClinicaSyncStatus()
  const sincronizar = useSincronizarClinicaAgora()

  const ultima = sincronizar.data ?? query.data?.ultima_execucao ?? null

  return (
    <div className="space-y-6" data-testid="configuracoes-clinica-sync-page">
      <section className="space-y-2">
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
        <h2 className="text-display font-semibold text-white">Sincronização com o clinica</h2>
        <p className="max-w-3xl text-corpo leading-6 text-slate-300">
          Profissionais e pacientes ficam espelhados entre o gescon e o clinica.gestaonossa.com.br
          — via mão dupla, quem cadastrar em qualquer um dos dois reflete no outro. Roda sozinho a
          cada 5 minutos; o botão abaixo dispara uma rodada na hora.
        </p>
      </section>

      {query.isPending ? <p className="text-corpo text-slate-400">Carregando...</p> : null}

      {query.isError ? (
        <p className="text-corpo text-rose-300">
          {getHttpErrorMessage(query.error, 'Não foi possível carregar o status da sincronização.')}
        </p>
      ) : null}

      {query.data && !query.data.configurado ? (
        <p className="rounded-2xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-corpo text-amber-100">
          Sem conexão configurada com o clinica ainda (tabela clinica_conexao_configs).
        </p>
      ) : null}

      <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h3 className="text-subtitulo font-semibold text-white">Última execução</h3>
            {query.data?.base_url ? (
              <p className="mt-1 text-meta text-slate-400">Destino: {query.data.base_url}</p>
            ) : null}
          </div>

          <Botao
            type="button"
            carregando={sincronizar.isPending}
            onClick={() => sincronizar.mutate()}
            data-testid="clinica-sync-agora"
          >
            {sincronizar.isPending ? 'Sincronizando...' : 'Sincronizar Agora'}
          </Botao>
        </div>

        {sincronizar.isError ? (
          <p className="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
            {getHttpErrorMessage(sincronizar.error, 'Não foi possível rodar a sincronização.')}
          </p>
        ) : null}

        {ultima ? (
          <div className="mt-5 space-y-4">
            <div className="flex flex-wrap items-center gap-3 text-meta">
              <Badge tone={badgeTone(ultima.status)} className="uppercase tracking-wide">
                {ultima.status === 'ok' ? 'OK' : 'Erro'}
              </Badge>
              <span className="text-slate-400">
                {ultima.origem === 'manual' ? 'Manual' : 'Agendado'} · {formatarData(ultima.iniciado_em)}
              </span>
            </div>

            {ultima.erro_mensagem ? (
              <p className="text-corpo text-rose-300">{ultima.erro_mensagem}</p>
            ) : null}

            {ultima.resumo ? (
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-3">
                  <p className="text-meta uppercase tracking-wide text-texto-suave">Profissionais</p>
                  <BlocoEntidade titulo="Trazido do clinica" resumo={ultima.resumo.profissionais.pull} />
                  <BlocoEntidade titulo="Enviado pro clinica" resumo={ultima.resumo.profissionais.push} />
                </div>
                <div className="space-y-3">
                  <p className="text-meta uppercase tracking-wide text-texto-suave">Pacientes</p>
                  <BlocoEntidade titulo="Trazido do clinica" resumo={ultima.resumo.pacientes.pull} />
                  <BlocoEntidade titulo="Enviado pro clinica" resumo={ultima.resumo.pacientes.push} />
                </div>
              </div>
            ) : null}
          </div>
        ) : (
          <p className="mt-4 text-corpo text-slate-400">Nenhuma execução ainda.</p>
        )}
      </section>

      <SecaoPendencias />
    </div>
  )
}

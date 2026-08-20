import {
  getHttpErrorMessage,
  useClinicaSyncStatus,
  useSincronizarClinicaAgora,
  type ClinicaSyncResumoEntidade,
} from './useClinicaSync'
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
    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
      <p className="text-sm font-semibold text-white">{titulo}</p>
      <p className="mt-1 text-xs text-slate-400">
        criados {resumo.criados} · atualizados {resumo.atualizados}
        {resumo.ignorados !== undefined ? ` · sem mudança ${resumo.ignorados}` : ''}
      </p>
      {resumo.pendentes.length > 0 ? (
        <ul className="mt-2 space-y-1 text-xs text-amber-200">
          {resumo.pendentes.map((linha, indice) => (
            <li key={indice}>⚠ {linha}</li>
          ))}
        </ul>
      ) : null}
    </div>
  )
}

export function ConfiguracoesClinicaSyncPage() {
  const query = useClinicaSyncStatus()
  const sincronizar = useSincronizarClinicaAgora()

  const ultima = sincronizar.data ?? query.data?.ultima_execucao ?? null

  return (
    <div className="space-y-6" data-testid="configuracoes-clinica-sync-page">
      <section className="space-y-2">
        <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
        <h2 className="text-3xl font-semibold text-white">Sincronização com o clinica</h2>
        <p className="max-w-3xl text-sm leading-6 text-slate-300">
          Profissionais e pacientes ficam espelhados entre o gescon e o clinica.gestaonossa.com.br
          — via mão dupla, quem cadastrar em qualquer um dos dois reflete no outro. Roda sozinho a
          cada 5 minutos; o botão abaixo dispara uma rodada na hora.
        </p>
      </section>

      {query.isPending ? <p className="text-sm text-slate-400">Carregando...</p> : null}

      {query.isError ? (
        <p className="text-sm text-rose-300">
          {getHttpErrorMessage(query.error, 'Não foi possível carregar o status da sincronização.')}
        </p>
      ) : null}

      {query.data && !query.data.configurado ? (
        <p className="rounded-2xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
          Sem conexão configurada com o clinica ainda (tabela clinica_conexao_configs).
        </p>
      ) : null}

      <section className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h3 className="text-lg font-semibold text-white">Última execução</h3>
            {query.data?.base_url ? (
              <p className="mt-1 text-xs text-slate-400">Destino: {query.data.base_url}</p>
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
          <p className="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
            {getHttpErrorMessage(sincronizar.error, 'Não foi possível rodar a sincronização.')}
          </p>
        ) : null}

        {ultima ? (
          <div className="mt-5 space-y-4">
            <div className="flex flex-wrap items-center gap-3 text-xs">
              <Badge tone={badgeTone(ultima.status)} className="uppercase tracking-wide">
                {ultima.status === 'ok' ? 'OK' : 'Erro'}
              </Badge>
              <span className="text-slate-400">
                {ultima.origem === 'manual' ? 'Manual' : 'Agendado'} · {formatarData(ultima.iniciado_em)}
              </span>
            </div>

            {ultima.erro_mensagem ? (
              <p className="text-sm text-rose-300">{ultima.erro_mensagem}</p>
            ) : null}

            {ultima.resumo ? (
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-3">
                  <p className="text-xs uppercase tracking-wide text-slate-500">Profissionais</p>
                  <BlocoEntidade titulo="Trazido do clinica" resumo={ultima.resumo.profissionais.pull} />
                  <BlocoEntidade titulo="Enviado pro clinica" resumo={ultima.resumo.profissionais.push} />
                </div>
                <div className="space-y-3">
                  <p className="text-xs uppercase tracking-wide text-slate-500">Pacientes</p>
                  <BlocoEntidade titulo="Trazido do clinica" resumo={ultima.resumo.pacientes.pull} />
                  <BlocoEntidade titulo="Enviado pro clinica" resumo={ultima.resumo.pacientes.push} />
                </div>
              </div>
            ) : null}
          </div>
        ) : (
          <p className="mt-4 text-sm text-slate-400">Nenhuma execução ainda.</p>
        )}
      </section>
    </div>
  )
}

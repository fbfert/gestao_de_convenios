import { Link } from 'react-router-dom'
import type { ReactNode } from 'react'
import { translateStatus } from '../../lib/statusLabels'
import { formatCarteirinha } from '../../lib/carteirinha'
import { isSenhaVencendo } from './senhaValidade'
import type { Guia } from './types'

function DetailItem({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
      <p className="text-xs uppercase tracking-[0.25em] text-slate-400">{label}</p>
      <div className="mt-2 text-sm font-medium text-white">{children}</div>
    </div>
  )
}

export function GuiaDetalheResumo({ guia }: { guia: Guia }) {
  const validadeVencendo = isSenhaVencendo(guia.validade_senha)

  return (
    <div className="space-y-8">
      <section className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Guia</p>
          <h2 className="mt-2 text-3xl font-semibold text-white">
            {guia.numero_guia ?? 'Aguardando número'}
          </h2>
          <p className="mt-2 text-sm text-slate-300">{guia.tipo_terapia}</p>
        </div>
        <span className="inline-flex w-fit rounded-full border border-cyan-400/20 bg-cyan-400/15 px-3 py-1 text-xs font-semibold text-cyan-100">
          {translateStatus('guias', guia.status)}
        </span>
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <DetailItem label="Paciente">
          <p>{guia.paciente?.nome ?? guia.paciente_id}</p>
          <p
            className="mt-1 text-xs font-normal tabular-nums text-slate-300"
            data-testid="guia-carteirinha"
          >
            Carteirinha: {formatCarteirinha(guia.paciente?.carteirinha) || '-'}
          </p>
        </DetailItem>
        <DetailItem label="Convênio">{guia.convenio?.nome ?? guia.convenio_id}</DetailItem>
        <DetailItem label="Profissional executante">
          {guia.profissional?.nome ?? guia.profissional_id}
        </DetailItem>
        <DetailItem label="Especialidade">{guia.especialidade?.nome ?? guia.especialidade_id}</DetailItem>
        <DetailItem label="Solicitação">{guia.data_solicitacao}</DetailItem>
        <DetailItem label="Finalização">{guia.data_finalizacao ?? '-'}</DetailItem>
        <DetailItem label="Senha">{guia.senha ?? '-'}</DetailItem>
        <DetailItem label="Validade da senha">
          <span className={validadeVencendo ? 'text-amber-200' : undefined}>{guia.validade_senha ?? '-'}</span>
          {validadeVencendo ? <p className="mt-1 text-xs font-normal text-amber-200">Vencendo em até 7 dias</p> : null}
        </DetailItem>
        <DetailItem label="Sessões solicitadas">{guia.sessoes_solicitadas ?? '-'}</DetailItem>
        <DetailItem label="Sessões autorizadas">{guia.sessoes_autorizadas ?? '-'}</DetailItem>
        <DetailItem label="Protocolo operadora">{guia.protocolo_operadora ?? '-'}</DetailItem>
      </section>

      {guia.solicitacao_item || guia.automacao_execucao || guia.ultima_automacao_unimed ? (
        <section className="rounded-[1.75rem] border border-cyan-300/20 bg-cyan-400/10 p-6">
          <h3 className="text-lg font-semibold text-white">Operação Unimed</h3>
          <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <DetailItem label="Item da solicitação">
              {guia.solicitacao_item ? `#${guia.solicitacao_item.id}` : '-'}
            </DetailItem>
            <DetailItem label="Quantidade">
              {guia.solicitacao_item?.quantidade ?? '-'}
            </DetailItem>
            <DetailItem label="Status do item">
              {guia.solicitacao_item?.status_operacional ?? '-'}
            </DetailItem>
            <DetailItem label="Execução">
              {guia.automacao_execucao ? `#${guia.automacao_execucao.id}` : '-'}
            </DetailItem>
            <DetailItem label="Operação">
              {guia.automacao_execucao?.operacao ?? '-'}
            </DetailItem>
            <DetailItem label="Status da execução">
              {guia.automacao_execucao?.status ?? '-'}
            </DetailItem>
            <DetailItem label="Status Unimed">
              {guia.unimed_status ?? '-'}
            </DetailItem>
            <DetailItem label="Última consulta">
              {guia.unimed_last_checked_at ?? '-'}
            </DetailItem>
            <DetailItem label="Próxima consulta">
              {guia.unimed_next_check_at ?? '-'}
            </DetailItem>
            <DetailItem label="Início">
              {guia.automacao_execucao?.started_at ?? '-'}
            </DetailItem>
            <DetailItem label="Conclusão">
              {guia.automacao_execucao?.finished_at ?? '-'}
            </DetailItem>
            <DetailItem label="Última operação Unimed">
              {guia.ultima_automacao_unimed
                ? `${guia.ultima_automacao_unimed.operacao} · ${guia.ultima_automacao_unimed.status}`
                : '-'}
            </DetailItem>
            <DetailItem label="Erro recente">
              {guia.ultima_automacao_unimed?.erro_codigo
                ? `${guia.ultima_automacao_unimed.erro_codigo}: ${guia.ultima_automacao_unimed.erro_mensagem ?? '-'}`
                : '-'}
            </DetailItem>
          </div>

          {(guia.ultima_automacao_unimed?.eventos.length || guia.automacao_execucao?.eventos.length) ? (
            <div className="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4">
              <p className="text-xs uppercase tracking-[0.25em] text-slate-400">Eventos</p>
              <div className="mt-3 space-y-2 text-sm text-slate-200">
                {(guia.ultima_automacao_unimed?.eventos ?? guia.automacao_execucao?.eventos ?? []).map((evento) => (
                  <p key={evento.id}>
                    {evento.registrado_em ?? '-'} · {evento.tipo} · {evento.status ?? '-'}
                  </p>
                ))}
              </div>
            </div>
          ) : null}
        </section>
      ) : null}

      <section className="grid gap-4 xl:grid-cols-2">
        <article className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <div className="flex items-center justify-between gap-4">
            <h3 className="text-lg font-semibold text-white">Antecipações</h3>
            <Link to="/antecipacoes" className="text-sm font-semibold text-cyan-200 hover:text-cyan-100">
              Ver antecipações
            </Link>
          </div>
          {guia.antecipacoes?.length ? (
            <div className="mt-4 space-y-3">
              {guia.antecipacoes.map((antecipacao) => (
                <div key={antecipacao.id} className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-200">
                  <p className="font-semibold text-white">Antecipação #{antecipacao.id}</p>
                  <p className="mt-1">Cota: {antecipacao.qtd_utilizada}/{antecipacao.qtd_autorizada}</p>
                </div>
              ))}
            </div>
          ) : (
            <p className="mt-4 text-sm text-slate-300">Nenhuma antecipação vinculada.</p>
          )}
        </article>

        <article className="rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <div className="flex items-center justify-between gap-4">
            <h3 className="text-lg font-semibold text-white">Conciliação</h3>
            <Link to="/conciliacao" className="text-sm font-semibold text-cyan-200 hover:text-cyan-100">
              Ver conciliação
            </Link>
          </div>
          {guia.conciliacoes?.length ? (
            <div className="mt-4 space-y-3">
              {guia.conciliacoes.map((conciliacao) => (
                <div key={conciliacao.id} className="rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-200">
                  <p className="font-semibold text-white">Conciliação #{conciliacao.id}</p>
                  <p className="mt-1">Status: {translateStatus('conciliacoes', conciliacao.status)}</p>
                </div>
              ))}
            </div>
          ) : (
            <p className="mt-4 text-sm text-slate-300">Nenhuma conciliação gerada.</p>
          )}
        </article>
      </section>
    </div>
  )
}

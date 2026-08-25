import { Link } from 'react-router-dom'
import { useEffect, useState, type ReactNode } from 'react'
import { Badge } from '../../components/ui/Badge'
import { Botao } from '../../components/ui/Botao'
import { Select } from '../../components/ui/Select'
import { usePode } from '../../lib/permissoes'
import { useEspecialidades, useProfissionais } from '../../lib/queries/useReferenceData'
import { translateStatus } from '../../lib/statusLabels'
import { formatCarteirinha } from '../../lib/carteirinha'
import { isSenhaVencendo } from './senhaValidade'
import { getHttpErrorMessage, useAtualizarGuia, type GuiaEditForm } from './useGuias'
import { statusTone } from './statusTone'
import type { Guia } from './types'

function DetailItem({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
      <p className="text-xs uppercase tracking-[0.25em] text-slate-400">{label}</p>
      <div className="mt-2 text-sm font-medium text-white">{children}</div>
    </div>
  )
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

const formVazio: GuiaEditForm = {
  profissional_id: '',
  especialidade_id: '',
  numero_guia: '',
  tipo_terapia: '',
  data_solicitacao: '',
  data_finalizacao: '',
  sessoes_solicitadas: '',
  sessoes_autorizadas: '',
  protocolo_operadora: '',
  senha: '',
  validade_senha: '',
  observacoes: '',
}

export function GuiaDetalheResumo({ guia }: { guia: Guia }) {
  const pode = usePode()
  const especialidadesQuery = useEspecialidades()
  const profissionaisQuery = useProfissionais()
  const atualizar = useAtualizarGuia()

  const [editando, setEditando] = useState(false)
  const [form, setForm] = useState<GuiaEditForm>(formVazio)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    setEditando(false)
  }, [guia.id])

  const validadeVencendo = isSenhaVencendo(guia.validade_senha)

  const iniciarEdicao = () => {
    setForm({
      profissional_id: String(guia.profissional_id),
      especialidade_id: String(guia.especialidade_id),
      numero_guia: guia.numero_guia ?? '',
      tipo_terapia: guia.tipo_terapia,
      data_solicitacao: guia.data_solicitacao.slice(0, 10),
      data_finalizacao: guia.data_finalizacao?.slice(0, 10) ?? '',
      sessoes_solicitadas: guia.sessoes_solicitadas != null ? String(guia.sessoes_solicitadas) : '',
      sessoes_autorizadas: guia.sessoes_autorizadas != null ? String(guia.sessoes_autorizadas) : '',
      protocolo_operadora: guia.protocolo_operadora ?? '',
      senha: guia.senha ?? '',
      validade_senha: guia.validade_senha?.slice(0, 10) ?? '',
      observacoes: guia.observacoes ?? '',
    })
    setErro(null)
    setEditando(true)
  }

  const salvar = async () => {
    setErro(null)

    try {
      await atualizar.mutateAsync({ id: guia.id, payload: form })
      setEditando(false)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível salvar as alterações.'))
    }
  }

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
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone={statusTone(guia.status)} className="w-fit">
            {translateStatus('guias', guia.status)}
          </Badge>
          {pode('guias.manage') && !editando ? (
            <button
              type="button"
              onClick={iniciarEdicao}
              className="rounded-2xl border border-cyan-300/40 bg-cyan-400/15 px-4 py-2 text-sm font-medium text-cyan-50 transition hover:bg-cyan-400/25"
              data-testid="guia-resumo-ativar-edicao"
            >
              Ativar edição
            </button>
          ) : null}
        </div>
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
      </section>
      <p className="-mt-4 text-xs text-slate-400">
        Paciente, convênio e status não são editáveis aqui: antecipações e conciliações já geradas usam esses
        dados, e o status muda pelos botões Finalizar/Negar.
      </p>

      {!editando ? (
        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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
          <DetailItem label="Observações">{guia.observacoes ?? '-'}</DetailItem>
        </section>
      ) : (
        <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <div className="grid gap-4 md:grid-cols-2">
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Especialidade</span>
              <Select
                value={form.especialidade_id}
                onChange={(event) => setForm((current) => ({ ...current, especialidade_id: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-resumo-especialidade"
              >
                <option value="" disabled>
                  Selecione
                </option>
                {(especialidadesQuery.data ?? []).map((especialidade) => (
                  <option key={especialidade.id} value={especialidade.id}>
                    {especialidade.nome}
                  </option>
                ))}
              </Select>
            </label>
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Profissional executante</span>
              <Select
                value={form.profissional_id}
                onChange={(event) => setForm((current) => ({ ...current, profissional_id: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-resumo-profissional"
              >
                <option value="" disabled>
                  Selecione
                </option>
                {(profissionaisQuery.data ?? []).map((profissional) => (
                  <option key={profissional.id} value={profissional.id}>
                    {profissional.nome}
                  </option>
                ))}
              </Select>
            </label>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Número da guia</span>
              <input
                value={form.numero_guia}
                onChange={(event) => setForm((current) => ({ ...current, numero_guia: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-resumo-numero"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Tipo de terapia</span>
              <Select
                value={form.tipo_terapia}
                onChange={(event) => setForm((current) => ({ ...current, tipo_terapia: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-resumo-tipo-terapia"
              >
                <option value="especializada">Especializada</option>
                <option value="convencional">Convencional</option>
                <option value="outro">Outro</option>
              </Select>
            </label>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Data da solicitação</span>
              <input
                type="date"
                value={form.data_solicitacao}
                onChange={(event) => setForm((current) => ({ ...current, data_solicitacao: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-resumo-data-solicitacao"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Data de finalização</span>
              <input
                type="date"
                value={form.data_finalizacao}
                onChange={(event) => setForm((current) => ({ ...current, data_finalizacao: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-resumo-data-finalizacao"
              />
            </label>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Senha</span>
              <input
                value={form.senha}
                onChange={(event) => setForm((current) => ({ ...current, senha: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-resumo-senha"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Validade da senha</span>
              <input
                type="date"
                value={form.validade_senha}
                onChange={(event) => setForm((current) => ({ ...current, validade_senha: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-resumo-validade-senha"
              />
            </label>
          </div>

          <div className="grid gap-4 md:grid-cols-3">
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Sessões solicitadas</span>
              <input
                type="number"
                min="1"
                value={form.sessoes_solicitadas}
                onChange={(event) => setForm((current) => ({ ...current, sessoes_solicitadas: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-resumo-sessoes-solicitadas"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Sessões autorizadas</span>
              <input
                type="number"
                min="0"
                value={form.sessoes_autorizadas}
                onChange={(event) => setForm((current) => ({ ...current, sessoes_autorizadas: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-resumo-sessoes-autorizadas"
              />
            </label>
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-200">Protocolo da operadora</span>
              <input
                value={form.protocolo_operadora}
                onChange={(event) => setForm((current) => ({ ...current, protocolo_operadora: event.target.value }))}
                className={fieldClasses()}
                data-testid="guia-resumo-protocolo"
              />
            </label>
          </div>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Observações</span>
            <textarea
              value={form.observacoes}
              onChange={(event) => setForm((current) => ({ ...current, observacoes: event.target.value }))}
              className={fieldClasses()}
              rows={3}
              data-testid="guia-resumo-observacoes"
            />
          </label>

          {erro ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{erro}</p>
          ) : null}

          <div className="flex gap-2">
            <Botao
              variante="primario"
              onClick={() => void salvar()}
              disabled={atualizar.isPending}
              data-testid="guia-resumo-salvar"
            >
              {atualizar.isPending ? 'Salvando...' : 'Salvar alterações'}
            </Botao>
            <Botao variante="secundario" onClick={() => setEditando(false)} data-testid="guia-resumo-cancelar">
              Cancelar
            </Botao>
          </div>
        </section>
      )}

      {guia.solicitacao_item || guia.automacao_execucao || guia.ultima_automacao_unimed ? (
        <section className="rounded-janela border border-acento/20 bg-acento-suave p-6">
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
              {guia.ultima_automacao_unimed?.erro_codigo &&
              guia.ultima_automacao_unimed.status !== 'succeeded'
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
        <article className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
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

        <article className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
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

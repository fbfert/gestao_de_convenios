import { Link } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import { rotuloDaChave } from './nivel'
import {
  useAlertaRegras,
  useAtualizarAlertaRegra,
  useDestinatarios,
  useRemoverDestinatario,
  useSalvarDestinatario,
} from './useAlertas'
import type { AlertaRegra, NivelAlerta } from './types'

const campo =
  'h-10 rounded-campo border border-borda-campo bg-superficie px-3 text-corpo text-texto'

/**
 * Configuração das regras de alerta.
 *
 * Limiar é DADO, nunca código (ADR-03): a clínica ajusta aqui e a avaliação
 * seguinte já usa o novo valor, sem deploy. Ligar e desligar é auditado — o
 * model usa `Auditable` — justamente porque limiar configurável convida a
 * desligar o alerta incômodo.
 */
export function AlertasConfiguracoesPage() {
  const regrasQuery = useAlertaRegras()
  const atualizar = useAtualizarAlertaRegra()
  const destinatariosQuery = useDestinatarios()
  const salvarDestinatario = useSalvarDestinatario()
  const removerDestinatario = useRemoverDestinatario()

  const regras = regrasQuery.data ?? []
  const destinatarios = destinatariosQuery.data ?? []

  const salvar = (regra: AlertaRegra, mudanca: Partial<AlertaRegra>) => {
    atualizar.mutate({ ...regra, ...mudanca })
  }

  return (
    <div className="space-y-6" data-testid="alertas-configuracoes-page">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-meta font-semibold uppercase tracking-[0.2em] text-acento">Alertas</p>
          <h2 className="mt-2 text-titulo font-semibold text-texto">Regras e limiares</h2>
        </div>
        <Link
          to="/alertas"
          className="inline-flex h-10 items-center rounded-pilula border border-acento/40 px-4 text-corpo font-semibold text-acento transition hover:bg-acento-suave"
        >
          Ver alertas
        </Link>
      </div>

      {regrasQuery.isLoading ? (
        <p className="rounded-janela border border-linha bg-superficie p-6 text-corpo text-texto-suave">
          Carregando regras...
        </p>
      ) : (
        <ul className="space-y-3">
          {regras.map((regra) => (
            <li
              key={regra.id}
              className="rounded-janela border border-linha bg-superficie p-5 shadow-e1"
              data-testid={`alerta-regra-${regra.chave}`}
            >
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 space-y-1">
                  <p className="text-corpo font-medium text-texto">{rotuloDaChave(regra.chave)}</p>
                  <p className="text-meta text-texto-suave">{regra.chave}</p>
                  {!regra.implementada ? (
                    <p className="text-meta text-alerta-texto">
                      Sem avaliador registrado — esta regra não gera alertas.
                    </p>
                  ) : null}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                  <label className="flex items-center gap-2 text-corpo text-texto">
                    <input
                      type="checkbox"
                      checked={regra.ativo}
                      onChange={(evento) => salvar(regra, { ativo: evento.target.checked })}
                    />
                    Ativa
                  </label>

                  <label className="flex items-center gap-2 text-corpo text-texto">
                    <input
                      type="checkbox"
                      checked={regra.critica}
                      onChange={(evento) => salvar(regra, { critica: evento.target.checked })}
                    />
                    Crítica
                  </label>

                  <select
                    className={campo}
                    value={regra.nivel_base}
                    onChange={(evento) =>
                      salvar(regra, { nivel_base: evento.target.value as NivelAlerta })
                    }
                    aria-label={`Nível base de ${regra.chave}`}
                  >
                    <option value="vermelho">Crítico</option>
                    <option value="amarelo">Atenção</option>
                    <option value="verde">Informativo</option>
                  </select>

                  <label className="flex items-center gap-2 text-meta text-texto-suave">
                    Limiar
                    <input
                      type="number"
                      min={0}
                      className={`${campo} w-24`}
                      value={regra.limiar_vermelho ?? ''}
                      onChange={(evento) =>
                        salvar(regra, {
                          limiar_vermelho:
                            evento.target.value === '' ? null : Number(evento.target.value),
                        })
                      }
                      aria-label={`Limiar de ${regra.chave}`}
                    />
                  </label>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}

      <p className="text-meta text-texto-suave">
        A janela de senha vencendo vem de Configurações → Geral (dias de alerta de senha). O limiar
        acima define a partir de quando o alerta vira crítico.
      </p>

      <section className="space-y-3" data-testid="alertas-destinatarios">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h3 className="text-subtitulo font-semibold text-texto">Destinatários</h3>
            <p className="mt-1 text-meta text-texto-suave">
              Cada pessoa recebe só os níveis e os tipos que escolher — a recepção quer senha
              vencendo, o financeiro quer glosa. Mandar tudo para todos leva a pedirem para
              desligar.
            </p>
          </div>
          <Botao
            variante="secundario"
            tamanho="sm"
            onClick={() =>
              salvarDestinatario.mutate({
                email: '',
                nome: null,
                niveis: ['amarelo', 'vermelho'],
                chaves: null,
                canal: 'digest',
                horario_digest: 8,
                ativo: true,
              })
            }
          >
            Adicionar
          </Botao>
        </div>

        {destinatarios.length === 0 ? (
          <p className="rounded-superficie border border-linha bg-fundo p-4 text-corpo text-texto-suave">
            Nenhum destinatário cadastrado — nenhum e-mail sai enquanto isso.
          </p>
        ) : (
          <ul className="space-y-2">
            {destinatarios.map((destinatario) => (
              <li
                key={destinatario.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-superficie border border-linha bg-fundo px-4 py-3"
              >
                <div className="min-w-0">
                  <p className="truncate text-corpo text-texto">{destinatario.email}</p>
                  <p className="text-meta text-texto-suave">
                    {destinatario.niveis.join(', ')} ·{' '}
                    {destinatario.chaves === null
                      ? 'todos os tipos'
                      : destinatario.chaves.join(', ')}{' '}
                    · {destinatario.canal} · {String(destinatario.horario_digest).padStart(2, '0')}h
                    {destinatario.verificado_em ? ' · verificado' : ' · não verificado'}
                  </p>
                </div>
                <div className="flex gap-2">
                  <Botao
                    variante="secundario"
                    tamanho="sm"
                    onClick={() =>
                      salvarDestinatario.mutate({ ...destinatario, ativo: !destinatario.ativo })
                    }
                  >
                    {destinatario.ativo ? 'Desativar' : 'Reativar'}
                  </Botao>
                  <Botao
                    variante="secundario"
                    tamanho="sm"
                    onClick={() => removerDestinatario.mutate(destinatario.id)}
                  >
                    Remover
                  </Botao>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="space-y-2">
        <h3 className="text-subtitulo font-semibold text-texto">Modelos de e-mail</h3>
        {/* Link, e não uma cópia: os modelos moram na tela que já existe, com
            CRUD próprio. Esta fase é o primeiro consumidor deles. */}
        <p className="text-corpo text-texto-suave">
          O texto do resumo diário e do aviso imediato fica em{' '}
          <Link
            to="/configuracoes/emails/templates"
            className="font-semibold text-acento-intenso underline underline-offset-2 transition hover:text-acento"
          >
            Configurações → Templates de E-mails
          </Link>
          , nas chaves <code>alertas.digest_diario</code> e <code>alertas.imediato</code>. Sem modelo
          cadastrado, o sistema envia um texto padrão — a notificação nunca deixa de sair.
        </p>
      </section>

      <Botao variante="secundario" disabled={atualizar.isPending}>
        {atualizar.isPending ? 'Salvando...' : 'Alterações salvas automaticamente'}
      </Botao>
    </div>
  )
}

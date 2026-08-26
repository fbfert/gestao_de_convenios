import { Select } from '../../components/ui/Select'
import { formatarTelefone } from '../../lib/documentos'
import type { TelefoneForm } from './types'

const campo =
  'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'

const rotulos = [
  { valor: 'celular', texto: 'Celular' },
  { valor: 'whatsapp', texto: 'WhatsApp' },
  { valor: 'fixo', texto: 'Fixo' },
  { valor: 'recado', texto: 'Recado' },
]

const telefoneVazio: TelefoneForm = {
  numero: '',
  rotulo: 'celular',
  contato_nome: '',
  principal: false,
}

/**
 * Lista de telefones do paciente.
 *
 * Uma criança em terapia costuma ter três contatos — mãe, pai e recado —, e é
 * por isso que cada linha guarda o nome de quem atende. O principal é o número
 * que a listagem mostra, então só um pode estar marcado.
 */
export function TelefonesInput({
  telefones,
  onChange,
}: {
  telefones: TelefoneForm[]
  onChange: (telefones: TelefoneForm[]) => void
}) {
  const alterar = (indice: number, campos: Partial<TelefoneForm>) => {
    onChange(telefones.map((telefone, i) => (i === indice ? { ...telefone, ...campos } : telefone)))
  }

  const marcarPrincipal = (indice: number) => {
    onChange(telefones.map((telefone, i) => ({ ...telefone, principal: i === indice })))
  }

  return (
    <div className="space-y-3" data-testid="paciente-telefones">
      <span className="block text-corpo font-medium text-slate-200">Telefones</span>

      {telefones.length === 0 ? (
        <p className="rounded-2xl border border-white/10 bg-slate-950/40 px-4 py-3 text-corpo text-slate-400">
          Nenhum telefone cadastrado.
        </p>
      ) : null}

      {telefones.map((telefone, indice) => (
        <div
          key={indice}
          className="grid gap-3 rounded-superficie border border-linha bg-fundo p-3 shadow-e1 md:grid-cols-[1fr_10rem_1fr_auto]"
        >
          <input
            value={formatarTelefone(telefone.numero)}
            onChange={(event) => alterar(indice, { numero: event.target.value })}
            placeholder="(11) 98888-1111"
            inputMode="tel"
            className={campo}
            data-testid={`paciente-telefone-numero-${indice}`}
          />

          <Select
            value={telefone.rotulo}
            onChange={(event) => alterar(indice, { rotulo: event.target.value })}
          >
            {rotulos.map((rotulo) => (
              <option key={rotulo.valor} value={rotulo.valor}>
                {rotulo.texto}
              </option>
            ))}
          </Select>

          <input
            value={telefone.contato_nome}
            onChange={(event) => alterar(indice, { contato_nome: event.target.value })}
            placeholder="Quem atende (ex.: Maria, mãe)"
            className={campo}
          />

          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => marcarPrincipal(indice)}
              className={[
                'rounded-full border px-3 py-1.5 text-meta font-semibold transition',
                telefone.principal
                  ? 'border-cyan-300/40 bg-cyan-400/15 text-cyan-50'
                  : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10',
              ].join(' ')}
              title="Número principal, mostrado na listagem"
              data-testid={`paciente-telefone-principal-${indice}`}
            >
              {telefone.principal ? '★ Principal' : 'Tornar principal'}
            </button>

            <button
              type="button"
              onClick={() => onChange(telefones.filter((_, i) => i !== indice))}
              className="inline-flex min-h-6 items-center text-meta font-semibold text-rose-200"
              data-testid={`paciente-telefone-remover-${indice}`}
            >
              Remover
            </button>
          </div>
        </div>
      ))}

      {/* Abaixo da ultima linha: e para onde a mao vai depois de preencher. */}
      <button
        type="button"
        onClick={() => onChange([...telefones, { ...telefoneVazio }])}
        className="rounded-full border border-cyan-300/30 bg-cyan-400/10 px-3 py-1.5 text-meta font-semibold text-cyan-100 transition hover:bg-cyan-400/20"
        data-testid="paciente-telefone-adicionar"
      >
        + 1 telefone
      </button>
    </div>
  )
}

import { Link } from 'react-router-dom'
import { usePode } from '../../lib/permissoes'
import { configuracoesItems, filtrarItens } from '../../routes/navigation'

/**
 * Tela de entrada de /configuracoes. Recebeu o que antes era a aba "Geral":
 * a escolha de tema fica no topo, e abaixo os cartoes explicam cada item do
 * submenu. As demais abas viraram rotas proprias (ConfiguracoesPage).
 */
export function ConfiguracoesGeralPage() {
  const pode = usePode()
  // Mesma regra do submenu: cartao so aparece para quem pode abrir a tela.
  const itens = filtrarItens(configuracoesItems, pode)

  return (
    <div className="space-y-6" data-testid="configuracoes-geral">
      <section className="space-y-2">
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
        <h2 className="text-display font-semibold text-white">Ajustes do sistema</h2>
        <p className="max-w-3xl text-corpo leading-6 text-slate-300">
          Todas as configurações abaixo valem para o tenant inteiro e estão no submenu de
          Configurações, no menu acima.
        </p>
      </section>

      <section className="space-y-4">
        <h3 className="text-subtitulo font-semibold text-white">O que há em cada configuração</h3>

        <div className="grid gap-4 md:grid-cols-2">
          {itens.map((item) => (
            <Link
              key={item.to}
              to={item.to}
              className="group block rounded-janela border border-linha bg-fundo p-5 shadow-e1 transition hover:border-acento/40 hover:bg-superficie hover:shadow-e2"
              data-testid={`configuracoes-card-${item.to.split('/').pop()}`}
            >
              <p className="text-corpo-lg font-semibold text-white group-hover:text-cyan-50">
                {item.label}
              </p>
              <p className="mt-2 text-corpo leading-6 text-slate-300">{item.descricao}</p>
            </Link>
          ))}
        </div>
      </section>
    </div>
  )
}

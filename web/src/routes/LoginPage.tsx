import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'
import { useLogin } from '../features/auth'

export function LoginPage() {
  const navigate = useNavigate()
  const login = useLogin()
  const [email, setEmail] = useState('admin@clinica-exemplo.test')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError(null)

    try {
      await login.mutateAsync({ email, password })
      navigate('/', { replace: true })
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.status === 401) {
        setError('E-mail ou senha inválidos.')
        return
      }

      setError('Não foi possível entrar agora.')
    }
  }

  return (
    <div className="grid min-h-screen place-items-center px-4 py-10" data-testid="login-page">
      <div className="w-full max-w-md rounded-[2rem] border border-white/10 bg-slate-950/80 p-8 shadow-2xl shadow-cyan-950/30 backdrop-blur">
        <div className="mb-8">
          <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">
            Acesso interno
          </p>
          <h1 className="mt-2 text-3xl font-semibold text-white">
            Entrar no sistema
          </h1>
          <p className="mt-2 text-sm leading-6 text-slate-300">
            Use o usuário seedado do ambiente local para testar o fluxo real de
            autenticação.
          </p>
        </div>

        <form className="space-y-5" onSubmit={handleSubmit}>
          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">E-mail</span>
            <input
              type="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20"
              placeholder="admin@clinica-exemplo.test"
              autoComplete="email"
              data-testid="login-email"
            />
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Senha</span>
            <input
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20"
              placeholder="password"
              autoComplete="current-password"
              data-testid="login-password"
            />
          </label>

          {error ? (
            <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
              {error}
            </p>
          ) : null}

          <button
            type="submit"
            disabled={login.isPending}
            className="inline-flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-70"
            data-testid="login-submit"
          >
            {login.isPending ? 'Entrando...' : 'Entrar'}
          </button>
        </form>
      </div>
    </div>
  )
}

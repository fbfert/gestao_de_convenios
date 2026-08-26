import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import axios from 'axios'
import { useLogin } from '../features/auth'
import { Botao } from '../components/ui/Botao'

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
      <div className="w-full max-w-md rounded-janela border border-linha bg-superficie p-8 shadow-e3">
        <div className="mb-8">
          <p className="text-meta uppercase tracking-[0.3em] text-acento">
            Acesso interno
          </p>
          <h1 className="mt-2 text-display font-semibold text-texto">
            Entrar no sistema
          </h1>
          <p className="mt-2 text-corpo leading-6 text-texto-suave">
            Use o usuário seedado do ambiente local para testar o fluxo real de
            autenticação.
          </p>
        </div>

        <form className="space-y-5" onSubmit={handleSubmit}>
          <label className="block space-y-2">
            <span className="text-corpo font-medium text-texto">E-mail</span>
            <input
              type="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className="w-full rounded-campo border border-borda-campo bg-superficie px-4 py-3 text-texto outline-none transition placeholder:text-texto-desativado focus-visible:border-foco focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
              placeholder="admin@clinica-exemplo.test"
              autoComplete="email"
              data-testid="login-email"
            />
          </label>

          <label className="block space-y-2">
            <span className="text-corpo font-medium text-texto">Senha</span>
            <input
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className="w-full rounded-campo border border-borda-campo bg-superficie px-4 py-3 text-texto outline-none transition placeholder:text-texto-desativado focus-visible:border-foco focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-foco"
              placeholder="password"
              autoComplete="current-password"
              data-testid="login-password"
            />
          </label>

          {error ? (
            <p className="rounded-campo border border-perigo/30 bg-perigo-suave px-4 py-3 text-corpo text-perigo-texto">
              {error}
            </p>
          ) : null}

          <Botao
            type="submit"
            variante="primario"
            className="w-full"
            disabled={login.isPending}
            data-testid="login-submit"
          >
            {login.isPending ? 'Entrando...' : 'Entrar'}
          </Botao>
        </form>
      </div>
    </div>
  )
}

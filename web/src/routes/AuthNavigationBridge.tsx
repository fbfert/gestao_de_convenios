import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { setUnauthorizedRedirect } from '../api/client'

export function AuthNavigationBridge() {
  const navigate = useNavigate()

  useEffect(() => {
    setUnauthorizedRedirect(() => {
      navigate('/login', { replace: true })
    })

    return () => {
      setUnauthorizedRedirect(null)
    }
  }, [navigate])

  return null
}

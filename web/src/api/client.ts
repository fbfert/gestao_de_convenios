import axios from 'axios'
import { useAuthStore } from '../stores/authStore'

const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

export const apiClient = axios.create({
  baseURL: apiUrl,
  headers: {
    Accept: 'application/json',
  },
})

let redirectToLogin: (() => void) | null = null

export function setUnauthorizedRedirect(handler: (() => void) | null) {
  redirectToLogin = handler
}

apiClient.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      useAuthStore.getState().logout()
      if (redirectToLogin) {
        redirectToLogin()
      } else if (typeof window !== 'undefined') {
        window.location.assign('/login')
      }
    }

    return Promise.reject(error)
  },
)

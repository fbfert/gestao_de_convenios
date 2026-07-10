import { Navigate, Route, Routes } from 'react-router-dom'
import { LoginPage } from './LoginPage'
import { ProtectedRoute } from './ProtectedRoute'
import { ShellLayout } from './ShellLayout'
import { HomePage } from './HomePage'

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<ProtectedRoute />}>
        <Route element={<ShellLayout />}>
          <Route path="/" element={<HomePage />} />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

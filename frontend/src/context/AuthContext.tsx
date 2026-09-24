import React, { createContext, useContext, useState, useEffect } from 'react'
import api from '../services/api'

interface User {
  id: number
  name: string
  email: string
  phone?: string
  role: string
  sede_id?: number
}

interface AuthContextType {
  user: User | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  register: (name: string, email: string, password: string, phone: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const token = localStorage.getItem('token')
    if (token) {
      fetchUser()
    } else {
      setLoading(false)
    }
  }, [])

  const fetchUser = async () => {
    try {
      const response = await api.get('/me')
      const userData = response.data.user
      // Extraer el rol del usuario
      if (userData && userData.roles && Array.isArray(userData.roles) && userData.roles.length > 0) {
        userData.role = userData.roles[0].name || userData.roles[0]
      } else {
        userData.role = 'cliente'
      }
      setUser(userData)
    } catch (error) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
    } finally {
      setLoading(false)
    }
  }

  const login = async (email: string, password: string) => {
    try {
      const response = await api.post('/login', { email, password })
      const userData = response.data.user
      // Asignar el rol desde roles array
      if (userData && response.data.roles && Array.isArray(response.data.roles) && response.data.roles.length > 0) {
        userData.role = typeof response.data.roles[0] === 'string' 
          ? response.data.roles[0] 
          : response.data.roles[0].name || 'cliente'
      } else {
        userData.role = 'cliente'
      }
      
      localStorage.setItem('token', response.data.token)
      localStorage.setItem('user', JSON.stringify(userData))
      setUser(userData)
    } catch (error: any) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      throw error
    }
  }

  const register = async (name: string, email: string, password: string, phone: string) => {
    const response = await api.post('/register', { name, email, password, password_confirmation: password, phone })
    const userData = response.data.user
    userData.role = 'cliente' // Los registros públicos siempre son clientes
    
    localStorage.setItem('token', response.data.token)
    localStorage.setItem('user', JSON.stringify(userData))
    setUser(userData)
  }

  const logout = async () => {
    try {
      await api.post('/logout')
    } catch (error) {
      // Ignore logout errors
    } finally {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      setUser(null)
    }
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export const useAuth = () => {
  const context = useContext(AuthContext)
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}
import { useState, useEffect } from 'react'
import { useAuth } from '../../context/AuthContext'
import api from '../../services/api'

interface Cita {
  id: number
  fecha: string
  hora_inicio: string
  hora_fin: string
  estado: string
  cliente: {
    name: string
    phone: string
  }
  servicios: Array<{
    nombre: string
  }>
}

const BarberoDashboard = () => {
  const { user, logout } = useAuth()

  const handleLogout = async () => {
    try {
      await logout()
    } catch (error) {
      console.error('Error al cerrar sesión:', error)
    }
  }
  const [citas, setCitas] = useState<Cita[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    fetchCitas()
  }, [])

  const fetchCitas = async () => {
    try {
      const response = await api.get('/citas')
      // La API ya filtra por rol, así que usamos los datos directamente
      setCitas(response.data)
    } catch (err) {
      setError('Error al cargar citas')
    } finally {
      setLoading(false)
    }
  }

  const updateEstado = async (citaId: number, nuevoEstado: string) => {
    try {
      await api.put(`/citas/${citaId}`, { estado: nuevoEstado })
      fetchCitas()
    } catch (err) {
      setError('Error al actualizar estado')
    }
  }

  const getEstadoColor = (estado: string) => {
    switch (estado) {
      case 'pendiente': return 'bg-yellow-500/20 text-yellow-300 border border-yellow-500/50'
      case 'confirmada': return 'bg-green-500/20 text-green-300 border border-green-500/50'
      case 'en_proceso': return 'bg-blue-500/20 text-blue-300 border border-blue-500/50'
      case 'completada': return 'bg-gray-500/20 text-gray-300 border border-gray-500/50'
      case 'cancelada': return 'bg-red-500/20 text-red-300 border border-red-500/50'
      default: return 'bg-gray-500/20 text-gray-300 border border-gray-500/50'
    }
  }

  return (
    <div className="min-h-screen">
      <nav className="bg-white/10 backdrop-blur-lg border-b border-white/20">
        <div className="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
          <h1 className="text-2xl font-bold gradient-text">Barbería Pro</h1>
          <div className="flex items-center gap-4">
            <span className="text-gray-300">Hola, {user?.name}</span>
            <button
              onClick={logout}
              className="btn-danger"
            >
              Cerrar Sesión
            </button>
          </div>
        </div>
      </nav>

      <div className="max-w-6xl mx-auto mt-8 p-6">
        <h2 className="text-3xl font-bold text-white mb-6">Mi Agenda</h2>
        
        {error && (
          <div className="bg-red-500/20 border border-red-500/50 text-red-200 px-4 py-3 rounded-lg mb-4 backdrop-blur-sm">
            {error}
          </div>
        )}

        {loading ? (
          <div className="text-center py-8 text-gray-300">Cargando...</div>
        ) : citas.length === 0 ? (
          <div className="card text-center">
            <p className="text-gray-300">No tienes citas programadas</p>
          </div>
        ) : (
          <div className="grid gap-4">
            {citas.map(cita => (
              <div key={cita.id} className="card">
                <div className="flex justify-between items-start mb-4">
                  <div>
                    <h3 className="text-lg font-semibold text-white">{cita.cliente.name}</h3>
                    <p className="text-gray-300">{cita.cliente.phone}</p>
                  </div>
                  <span className={`px-3 py-1 rounded-full text-sm backdrop-blur-sm ${getEstadoColor(cita.estado)}`}>
                    {cita.estado}
                  </span>
                </div>
                
                <div className="mb-4">
                  <p className="text-gray-300">
                    <strong className="text-white">Fecha:</strong> {new Date(cita.fecha).toLocaleDateString()}
                  </p>
                  <p className="text-gray-300">
                    <strong className="text-white">Hora:</strong> {cita.hora_inicio} - {cita.hora_fin}
                  </p>
                </div>

                <div className="mb-4">
                  <p className="text-gray-300"><strong className="text-white">Servicios:</strong></p>
                  <ul className="list-disc list-inside ml-4 text-gray-300">
                    {cita.servicios.map((servicio, index) => (
                      <li key={index}>{servicio.nombre}</li>
                    ))}
                  </ul>
                </div>

                <div className="flex gap-2">
                  {cita.estado === 'pendiente' && (
                    <button
                      onClick={() => updateEstado(cita.id, 'en_proceso')}
                      className="btn-primary text-sm"
                    >
                      Iniciar
                    </button>
                  )}
                  {cita.estado === 'en_proceso' && (
                    <button
                      onClick={() => updateEstado(cita.id, 'completada')}
                      className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-all duration-300"
                    >
                      Completar
                    </button>
                  )}
                  {cita.estado !== 'cancelada' && cita.estado !== 'completada' && (
                    <button
                      onClick={() => updateEstado(cita.id, 'cancelada')}
                      className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-all duration-300"
                    >
                      Cancelar
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default BarberoDashboard
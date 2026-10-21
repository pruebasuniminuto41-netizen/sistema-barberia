import { useState, useEffect } from 'react'
import { toast } from 'react-toastify'
import { useAuth } from '../../context/AuthContext'
import api from '../../services/api'
import type { Cita } from '../../types'
import { errorMessage, estadoInfo, formatFecha, formatHora, money } from '../../utils/helpers'

// Acciones que el barbero puede hacer según el estado de la cita
const ACCIONES: Record<string, Array<{ estado: string; texto: string; clase: string }>> = {
  pendiente: [
    { estado: 'confirmada', texto: 'Confirmar', clase: 'btn-primary' },
    { estado: 'en_proceso', texto: 'Iniciar', clase: 'btn-primary' },
    { estado: 'cancelada', texto: 'Cancelar', clase: 'btn-danger' },
  ],
  confirmada: [
    { estado: 'en_proceso', texto: 'Iniciar', clase: 'btn-primary' },
    { estado: 'cancelada', texto: 'Cancelar', clase: 'btn-danger' },
  ],
  en_proceso: [{ estado: 'completada', texto: 'Completar', clase: 'bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-all duration-300' }],
}

const ACTIVAS = ['pendiente', 'pendiente_validacion', 'confirmada', 'en_proceso']

const BarberoDashboard = () => {
  const { user, logout } = useAuth()
  const [citas, setCitas] = useState<Cita[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [verHistorial, setVerHistorial] = useState(false)

  useEffect(() => {
    fetchCitas()
  }, [])

  const fetchCitas = async () => {
    try {
      const response = await api.get('/citas')
      // La API ya filtra por rol: solo llegan las citas de este barbero
      setCitas(response.data)
      setError('')
    } catch (err) {
      setError(errorMessage(err, 'Error al cargar citas'))
    } finally {
      setLoading(false)
    }
  }

  const updateEstado = async (cita: Cita, nuevoEstado: string) => {
    if (nuevoEstado === 'cancelada' && !window.confirm(`¿Cancelar la cita de ${cita.cliente.name}? Se le avisará por correo.`)) {
      return
    }
    try {
      await api.put(`/citas/${cita.id}`, { estado: nuevoEstado })
      toast.success('Cita actualizada')
      fetchCitas()
    } catch (err) {
      toast.error(errorMessage(err, 'Error al actualizar estado'))
    }
  }

  // Próximas primero (orden ascendente por fecha y hora); el historial, lo más reciente primero
  const activas = citas
    .filter((c) => ACTIVAS.includes(c.estado))
    .sort((a, b) => `${a.fecha} ${a.hora_inicio}`.localeCompare(`${b.fecha} ${b.hora_inicio}`))
  const historial = citas.filter((c) => !ACTIVAS.includes(c.estado))
  const lista = verHistorial ? historial : activas

  return (
    <div className="min-h-screen">
      <nav className="bg-white/10 backdrop-blur-lg border-b border-white/20">
        <div className="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
          <h1 className="text-2xl font-bold gradient-text">Barbería Pro</h1>
          <div className="flex items-center gap-4">
            <span className="text-gray-300">Hola, {user?.name}</span>
            <button onClick={logout} className="btn-danger">
              Cerrar Sesión
            </button>
          </div>
        </div>
      </nav>

      <div className="max-w-6xl mx-auto mt-8 p-6">
        <h2 className="text-3xl font-bold text-white mb-6">Mi Agenda</h2>

        <div className="flex gap-4 mb-6">
          <button
            onClick={() => setVerHistorial(false)}
            className={`px-4 py-2 rounded-lg transition-all duration-300 ${!verHistorial ? 'btn-primary' : 'bg-white/10 text-gray-300 hover:bg-white/20'}`}
          >
            Próximas ({activas.length})
          </button>
          <button
            onClick={() => setVerHistorial(true)}
            className={`px-4 py-2 rounded-lg transition-all duration-300 ${verHistorial ? 'btn-primary' : 'bg-white/10 text-gray-300 hover:bg-white/20'}`}
          >
            Historial ({historial.length})
          </button>
        </div>

        {error && (
          <div className="bg-red-500/20 border border-red-500/50 text-red-200 px-4 py-3 rounded-lg mb-4 backdrop-blur-sm">
            {error}
          </div>
        )}

        {loading ? (
          <div className="text-center py-8 text-gray-300">Cargando...</div>
        ) : lista.length === 0 ? (
          <div className="card text-center">
            <p className="text-gray-300">{verHistorial ? 'Aún no tienes citas finalizadas' : 'No tienes citas programadas'}</p>
          </div>
        ) : (
          <div className="grid gap-4">
            {lista.map((cita) => (
              <div key={cita.id} className="card">
                <div className="flex justify-between items-start mb-4">
                  <div>
                    <h3 className="text-lg font-semibold text-white">{cita.cliente.name}</h3>
                    <p className="text-gray-300">{cita.cliente.phone || 'Sin teléfono'}</p>
                  </div>
                  <span className={`px-3 py-1 rounded-full text-sm backdrop-blur-sm ${estadoInfo(cita.estado).clase}`}>
                    {estadoInfo(cita.estado).label}
                  </span>
                </div>

                <div className="mb-4">
                  <p className="text-gray-300">
                    <strong className="text-white">Fecha:</strong> {formatFecha(cita.fecha)}
                  </p>
                  <p className="text-gray-300">
                    <strong className="text-white">Hora:</strong> {formatHora(cita.hora_inicio)} - {formatHora(cita.hora_fin)}
                  </p>
                  <p className="text-gray-300">
                    <strong className="text-white">Sede:</strong> {cita.sede.nombre}
                  </p>
                  <p className="text-gray-300">
                    <strong className="text-white">Total:</strong> {money(cita.monto_total)} ({cita.metodo_pago})
                  </p>
                </div>

                <div className="mb-4">
                  <p className="text-gray-300">
                    <strong className="text-white">Servicios:</strong>
                  </p>
                  <ul className="list-disc list-inside ml-4 text-gray-300">
                    {cita.servicios.map((servicio) => (
                      <li key={servicio.id}>{servicio.nombre}</li>
                    ))}
                  </ul>
                </div>

                {cita.estado === 'pendiente_validacion' && (
                  <p className="text-sm text-orange-300">
                    El pago por transferencia está pendiente de validación por el supervisor. El horario ya está reservado.
                  </p>
                )}

                <div className="flex flex-wrap gap-2">
                  {(ACCIONES[cita.estado] || []).map((accion) => (
                    <button key={accion.estado} onClick={() => updateEstado(cita, accion.estado)} className={`${accion.clase} text-sm`}>
                      {accion.texto}
                    </button>
                  ))}
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

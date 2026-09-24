import { useState, useEffect } from 'react'
import { useAuth } from '../../context/AuthContext'
import api from '../../services/api'
import { toast } from 'react-toastify'

interface Sede {
  id: number
  nombre: string
  direccion: string
}

interface Barbero {
  id: number
  name: string
}

interface Servicio {
  id: number
  nombre: string
  precio: number
  duracion_minutos: number
}

interface Cita {
  id: number
  fecha: string
  hora_inicio: string
  hora_fin: string
  estado: string
  metodo_pago: string
  monto_total: number
  barbero: {
    name: string
  }
  sede: {
    nombre: string
  }
  servicios: Array<{
    nombre: string
  }>
}

const ClienteDashboard = () => {
  const { user, logout } = useAuth()

  const handleLogout = async () => {
    try {
      await logout()
    } catch (error) {
      console.error('Error al cerrar sesión:', error)
    }
  }
  const [sedes, setSedes] = useState<Sede[]>([])
  const [barberos, setBarberos] = useState<Barbero[]>([])
  const [servicios, setServicios] = useState<Servicio[]>([])
  const [selectedSede, setSelectedSede] = useState<number | null>(null)
  const [selectedBarbero, setSelectedBarbero] = useState<number | null>(null)
  const [selectedServicios, setSelectedServicios] = useState<number[]>([])
  const [fecha, setFecha] = useState('')
  const [hora, setHora] = useState('')
  const [metodoPago, setMetodoPago] = useState('efectivo')
  const [comprobante, setComprobante] = useState<File | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [citas, setCitas] = useState<Cita[]>([])
  const [showHistorial, setShowHistorial] = useState(false)

  useEffect(() => {
    fetchSedes()
    fetchServicios()
    fetchCitas()
  }, [])

  useEffect(() => {
    if (selectedSede) {
      fetchBarberos(selectedSede)
    }
  }, [selectedSede])

  const fetchSedes = async () => {
    try {
      const response = await api.get('/sedes')
      setSedes(response.data)
    } catch (err) {
      setError('Error al cargar sedes')
    }
  }

  const fetchBarberos = async (sedeId: number) => {
    try {
      const response = await api.get(`/sedes/${sedeId}/barberos`)
      setBarberos(response.data)
    } catch (err) {
      setError('Error al cargar barberos')
    }
  }

  const fetchServicios = async () => {
    try {
      const response = await api.get('/servicios')
      setServicios(response.data)
    } catch (err) {
      setError('Error al cargar servicios')
    }
  }

  const fetchCitas = async () => {
    try {
      const response = await api.get('/citas')
      setCitas(response.data)
    } catch (err) {
      setError('Error al cargar citas')
    }
  }

  const cancelCita = async (citaId: number) => {
    try {
      await api.put(`/citas/${citaId}`, { estado: 'cancelada' })
      toast.success('Cita cancelada exitosamente')
      fetchCitas()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Error al cancelar cita')
    }
  }

  const handleServicioToggle = (servicioId: number) => {
    setSelectedServicios(prev => 
      prev.includes(servicioId) 
        ? prev.filter(id => id !== servicioId)
        : [...prev, servicioId]
    )
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setLoading(true)

    try {
      const serviciosData = selectedServicios.map(servicioId => ({ id: servicioId }))
      
      const formData = new FormData()
      formData.append('sede_id', selectedSede!.toString())
      formData.append('barbero_id', selectedBarbero!.toString())
      formData.append('fecha', fecha)
      formData.append('hora_inicio', hora)
      formData.append('metodo_pago', metodoPago)
      formData.append('servicios', JSON.stringify(serviciosData))

      if (metodoPago === 'transferencia' && comprobante) {
        formData.append('comprobante_pago', comprobante)
      }

      await api.post('/citas', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })

      toast.success('Cita reservada exitosamente')
      // Reset form
      setSelectedSede(null)
      setSelectedBarbero(null)
      setSelectedServicios([])
      setFecha('')
      setHora('')
      setMetodoPago('efectivo')
      setComprobante(null)
    } catch (err: any) {
      setError(err.response?.data?.message || 'Error al reservar cita')
    } finally {
      setLoading(false)
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
              onClick={handleLogout}
              className="btn-danger"
            >
              Cerrar Sesión
            </button>
          </div>
        </div>
      </nav>

      <div className="max-w-4xl mx-auto mt-8 p-6">
        <div className="flex gap-4 mb-6">
          <button
            onClick={() => setShowHistorial(false)}
            className={`px-4 py-2 rounded-lg transition-all duration-300 ${!showHistorial ? 'btn-primary' : 'bg-white/10 text-gray-300 hover:bg-white/20'}`}
          >
            Reservar Cita
          </button>
          <button
            onClick={() => setShowHistorial(true)}
            className={`px-4 py-2 rounded-lg transition-all duration-300 ${showHistorial ? 'btn-primary' : 'bg-white/10 text-gray-300 hover:bg-white/20'}`}
          >
            Mis Citas ({citas.length})
          </button>
        </div>

        {!showHistorial ? (
          <>
            <h2 className="text-3xl font-bold text-white mb-6">Reservar Cita</h2>
        
        {error && (
          <div className="bg-red-500/20 border border-red-500/50 text-red-200 px-4 py-3 rounded-lg mb-4 backdrop-blur-sm">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="card">
          <div className="mb-4">
            <label className="block text-gray-300 text-sm font-semibold mb-2">
              Sede
            </label>
            <select
              value={selectedSede || ''}
              onChange={(e) => setSelectedSede(Number(e.target.value))}
              className="input-field"
              required
            >
              <option value="">Selecciona una sede</option>
              {sedes.map(sede => (
                <option key={sede.id} value={sede.id}>
                  {sede.nombre} - {sede.direccion}
                </option>
              ))}
            </select>
          </div>

          <div className="mb-4">
            <label className="block text-gray-300 text-sm font-semibold mb-2">
              Barbero
            </label>
            <select
              value={selectedBarbero || ''}
              onChange={(e) => setSelectedBarbero(Number(e.target.value))}
              className="input-field"
              required
              disabled={!selectedSede}
            >
              <option value="">Selecciona un barbero</option>
              {barberos.map(barbero => (
                <option key={barbero.id} value={barbero.id}>
                  {barbero.name}
                </option>
              ))}
            </select>
          </div>

          <div className="mb-4">
            <label className="block text-gray-300 text-sm font-semibold mb-2">
              Servicios
            </label>
            <div className="space-y-2">
              {servicios.map(servicio => (
                <label key={servicio.id} className="flex items-center text-gray-300 cursor-pointer hover:text-white transition-colors">
                  <input
                    type="checkbox"
                    checked={selectedServicios.includes(servicio.id)}
                    onChange={() => handleServicioToggle(servicio.id)}
                    className="mr-3 w-5 h-5 accent-purple-500"
                  />
                  <span>{servicio.nombre} - ${servicio.precio} ({servicio.duracion_minutos} min)</span>
                </label>
              ))}
            </div>
          </div>

          <div className="mb-4">
            <label className="block text-gray-300 text-sm font-semibold mb-2">
              Fecha
            </label>
            <input
              type="date"
              value={fecha}
              onChange={(e) => setFecha(e.target.value)}
              className="input-field"
              required
            />
          </div>

          <div className="mb-4">
            <label className="block text-gray-300 text-sm font-semibold mb-2">
              Hora
            </label>
            <input
              type="time"
              value={hora}
              onChange={(e) => setHora(e.target.value)}
              className="input-field"
              required
            />
          </div>

          <div className="mb-4">
            <label className="block text-gray-300 text-sm font-semibold mb-2">
              Método de Pago
            </label>
            <select
              value={metodoPago}
              onChange={(e) => setMetodoPago(e.target.value)}
              className="input-field"
              required
            >
              <option value="efectivo">Efectivo</option>
              <option value="transferencia">Transferencia</option>
            </select>
          </div>

          {metodoPago === 'transferencia' && (
            <div className="mb-4">
              <label className="block text-gray-300 text-sm font-semibold mb-2">
                Comprobante de Pago
              </label>
              <input
                type="file"
                onChange={(e) => setComprobante(e.target.files?.[0] || null)}
                className="input-field"
                required
              />
            </div>
          )}

          <button
            type="submit"
            disabled={loading}
            className="w-full btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? 'Procesando...' : 'Reservar Cita'}
          </button>
        </form>
          </>
        ) : (
          <>
            <h2 className="text-3xl font-bold text-white mb-6">Mis Citas</h2>
            {citas.length === 0 ? (
              <div className="card text-center">
                <p className="text-gray-300">No tienes citas registradas</p>
              </div>
            ) : (
              <div className="grid gap-4">
                {citas.map(cita => (
                  <div key={cita.id} className="card">
                    <div className="flex justify-between items-start mb-4">
                      <div>
                        <h3 className="text-lg font-semibold text-white">{cita.sede.nombre}</h3>
                        <p className="text-gray-300">Barbero: {cita.barbero.name}</p>
                      </div>
                      <span className={`px-3 py-1 rounded-full text-sm backdrop-blur-sm ${
                        cita.estado === 'completada' ? 'bg-green-500/20 text-green-300 border border-green-500/50' :
                        cita.estado === 'cancelada' ? 'bg-red-500/20 text-red-300 border border-red-500/50' :
                        cita.estado === 'confirmada' ? 'bg-blue-500/20 text-blue-300 border border-blue-500/50' :
                        'bg-yellow-500/20 text-yellow-300 border border-yellow-500/50'
                      }`}>
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
                      <p className="text-gray-300">
                        <strong className="text-white">Total:</strong> ${cita.monto_total}
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

                    {cita.estado !== 'cancelada' && cita.estado !== 'completada' && new Date(cita.fecha) > new Date() && (
                      <button
                        onClick={() => cancelCita(cita.id)}
                        className="btn-danger"
                      >
                        Cancelar Cita
                      </button>
                    )}
                  </div>
                ))}
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}

export default ClienteDashboard
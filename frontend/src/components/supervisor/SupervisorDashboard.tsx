import { useState, useEffect } from 'react'
import { useAuth } from '../../context/AuthContext'
import api from '../../services/api'
import { toast } from 'react-toastify'

interface Cita {
  id: number
  fecha: string
  hora_inicio: string
  hora_fin: string
  estado: string
  metodo_pago: string
  comprobante_pago_url?: string
  monto_total: number
  cliente: {
    name: string
    phone: string
  }
  barbero: {
    name: string
  }
  servicios: Array<{
    nombre: string
  }>
}

interface Producto {
  id: number
  nombre: string
  descripcion: string
  precio: number
  stock_por_sede: number
  sede_id?: number
}

const SupervisorDashboard = () => {
  const { user, logout } = useAuth()
  const [citas, setCitas] = useState<Cita[]>([])
  const [productos, setProductos] = useState<Producto[]>([])
  const [selectedCita, setSelectedCita] = useState<Cita | null>(null)
  const [selectedProductos, setSelectedProductos] = useState<{[key: number]: number}>({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    fetchCitas()
    fetchProductos()
  }, [])

  const fetchCitas = async () => {
    try {
      const response = await api.get('/citas')
      // La API ya filtra por rol para supervisores
      setCitas(response.data)
    } catch (err) {
      setError('Error al cargar citas')
    } finally {
      setLoading(false)
    }
  }

  const fetchProductos = async () => {
    try {
      const response = await api.get('/productos')
      setProductos(response.data)
    } catch (err) {
      setError('Error al cargar productos')
    }
  }

  const validarComprobante = async (citaId: number) => {
    try {
      await api.post(`/citas/${citaId}/validar-comprobante`, { estado: 'confirmada' })
      fetchCitas()
      toast.success('Comprobante validado exitosamente')
    } catch (err) {
      setError('Error al validar comprobante')
    }
  }

  const agregarProductos = async (citaId: number) => {
    try {
      const productosArray = Object.entries(selectedProductos)
        .filter(([_, cantidad]) => cantidad > 0)
        .map(([productoId, cantidad]) => ({
          producto_id: Number(productoId),
          cantidad
        }))

      await api.post(`/citas/${citaId}/productos`, { productos: productosArray })
      fetchCitas()
      setSelectedCita(null)
      setSelectedProductos({})
      toast.success('Productos agregados exitosamente')
    } catch (err) {
      setError('Error al agregar productos')
    }
  }

  const handleProductoChange = (productoId: number, cantidad: number) => {
    setSelectedProductos(prev => ({
      ...prev,
      [productoId]: cantidad || 0
    }))
  }

  const getEstadoColor = (estado: string) => {
    switch (estado) {
      case 'pendiente': return 'bg-yellow-500/20 text-yellow-300 border border-yellow-500/50'
      case 'pendiente_validacion': return 'bg-orange-500/20 text-orange-300 border border-orange-500/50'
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

      <div className="max-w-7xl mx-auto mt-8 p-6">
        <h2 className="text-3xl font-bold text-white mb-6">Citas de la Sede</h2>
        
        {error && (
          <div className="bg-red-500/20 border border-red-500/50 text-red-200 px-4 py-3 rounded-lg mb-4 backdrop-blur-sm">
            {error}
          </div>
        )}

        {loading ? (
          <div className="text-center py-8 text-gray-300">Cargando...</div>
        ) : citas.length === 0 ? (
          <div className="card text-center">
            <p className="text-gray-300">No hay citas programadas</p>
          </div>
        ) : (
          <div className="grid gap-4">
            {citas.map(cita => (
              <div key={cita.id} className="card">
                <div className="flex justify-between items-start mb-4">
                  <div>
                    <h3 className="text-lg font-semibold text-white">{cita.cliente.name}</h3>
                    <p className="text-gray-300">{cita.cliente.phone}</p>
                    <p className="text-gray-300">Barbero: {cita.barbero.name}</p>
                  </div>
                  <div className="text-right">
                    <span className={`px-3 py-1 rounded-full text-sm backdrop-blur-sm ${getEstadoColor(cita.estado)}`}>
                      {cita.estado}
                    </span>
                    <p className="text-white mt-2 font-semibold text-xl">
                      Total: ${cita.monto_total}
                    </p>
                  </div>
                </div>
                
                <div className="mb-4">
                  <p className="text-gray-300">
                    <strong className="text-white">Fecha:</strong> {new Date(cita.fecha).toLocaleDateString()}
                  </p>
                  <p className="text-gray-300">
                    <strong className="text-white">Hora:</strong> {cita.hora_inicio} - {cita.hora_fin}
                  </p>
                  <p className="text-gray-300">
                    <strong className="text-white">Pago:</strong> {cita.metodo_pago}
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

                {cita.metodo_pago === 'transferencia' && cita.estado === 'pendiente_validacion' && (
                  <div className="mb-4">
                    {cita.comprobante_pago_url && (
                      <a 
                        href={cita.comprobante_pago_url} 
                        target="_blank" 
                        rel="noopener noreferrer"
                        className="text-purple-400 hover:text-purple-300 hover:underline transition-colors"
                      >
                        Ver comprobante
                      </a>
                    )}
                    <button
                      onClick={() => validarComprobante(cita.id)}
                      className="ml-4 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-all duration-300"
                    >
                      Validar Comprobante
                    </button>
                  </div>
                )}

                <div className="flex gap-2">
                  <button
                    onClick={() => setSelectedCita(cita)}
                    className="btn-primary"
                  >
                    Agregar Productos
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}

        {selectedCita && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center">
            <div className="card max-w-2xl w-full max-h-screen overflow-y-auto">
              <h3 className="text-xl font-bold text-white mb-4">Agregar Productos a Cita #{selectedCita.id}</h3>
              
              <div className="space-y-4">
                {productos.map(producto => (
                  <div key={producto.id} className="flex items-center justify-between">
                    <div>
                      <p className="font-semibold text-white">{producto.nombre}</p>
                      <p className="text-gray-300">${producto.precio} - Stock: {producto.stock_por_sede}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => handleProductoChange(producto.id, Math.max(0, (selectedProductos[producto.id] || 0) - 1))}
                        className="bg-white/20 text-white px-3 py-1 rounded hover:bg-white/30 transition-colors"
                      >
                        -
                      </button>
                      <span className="text-white">{selectedProductos[producto.id] || 0}</span>
                      <button
                        onClick={() => handleProductoChange(producto.id, (selectedProductos[producto.id] || 0) + 1)}
                        className="bg-white/20 text-white px-3 py-1 rounded hover:bg-white/30 transition-colors"
                      >
                        +
                      </button>
                    </div>
                  </div>
                ))}
              </div>

              <div className="flex gap-4 mt-6">
                <button
                  onClick={() => agregarProductos(selectedCita.id)}
                  className="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition-all duration-300"
                >
                  Agregar
                </button>
                <button
                  onClick={() => {
                    setSelectedCita(null)
                    setSelectedProductos({})
                  }}
                  className="flex-1 bg-gray-600 text-white py-2 rounded-lg hover:bg-gray-700 transition-all duration-300"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

export default SupervisorDashboard
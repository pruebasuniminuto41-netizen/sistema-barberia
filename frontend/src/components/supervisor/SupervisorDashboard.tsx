import { useState, useEffect } from 'react'
import { toast } from 'react-toastify'
import { useAuth } from '../../context/AuthContext'
import api from '../../services/api'
import type { Cita, Producto } from '../../types'
import { abrirArchivo, errorMessage, estadoInfo, formatFecha, formatHora, money } from '../../utils/helpers'

type Filtro = 'validar' | 'activas' | 'historial'

const ACTIVAS = ['pendiente', 'confirmada', 'en_proceso']

const SupervisorDashboard = () => {
  const { user, logout } = useAuth()
  const [citas, setCitas] = useState<Cita[]>([])
  const [productos, setProductos] = useState<Producto[]>([])
  const [selectedCita, setSelectedCita] = useState<Cita | null>(null)
  const [selectedProductos, setSelectedProductos] = useState<{ [key: number]: number }>({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [filtro, setFiltro] = useState<Filtro>('validar')
  const [guardando, setGuardando] = useState(false)

  useEffect(() => {
    fetchCitas()
    fetchProductos()
  }, [])

  const fetchCitas = async () => {
    try {
      const response = await api.get('/citas')
      // La API ya filtra por rol: solo llegan las citas de la sede del supervisor
      setCitas(response.data)
      setError('')
    } catch (err) {
      setError(errorMessage(err, 'Error al cargar citas'))
    } finally {
      setLoading(false)
    }
  }

  const fetchProductos = async () => {
    try {
      const response = await api.get('/productos')
      setProductos(response.data)
    } catch (err) {
      setError(errorMessage(err, 'Error al cargar productos'))
    }
  }

  const verComprobante = async (cita: Cita) => {
    try {
      await abrirArchivo(`/citas/${cita.id}/comprobante`)
    } catch (err) {
      toast.error(errorMessage(err, 'No se pudo abrir el comprobante'))
    }
  }

  const resolverComprobante = async (cita: Cita, estado: 'confirmada' | 'cancelada') => {
    const pregunta =
      estado === 'confirmada'
        ? `¿Validar el comprobante de ${cita.cliente.name}? Se confirmará la cita y se le avisará por correo.`
        : `¿Rechazar el comprobante de ${cita.cliente.name}? La cita se cancelará y se le avisará por correo.`
    if (!window.confirm(pregunta)) return

    try {
      await api.post(`/citas/${cita.id}/validar-comprobante`, { estado })
      toast.success(estado === 'confirmada' ? 'Comprobante validado, cita confirmada' : 'Comprobante rechazado, cita cancelada')
      fetchCitas()
    } catch (err) {
      toast.error(errorMessage(err, 'Error al procesar el comprobante'))
    }
  }

  const agregarProductos = async (citaId: number) => {
    const productosArray = Object.entries(selectedProductos)
      .filter(([, cantidad]) => cantidad > 0)
      .map(([productoId, cantidad]) => ({ producto_id: Number(productoId), cantidad }))

    if (productosArray.length === 0) {
      toast.error('Selecciona al menos un producto')
      return
    }

    setGuardando(true)
    try {
      await api.post(`/citas/${citaId}/productos`, { productos: productosArray })
      toast.success('Productos agregados exitosamente')
      setSelectedCita(null)
      setSelectedProductos({})
      // Actualiza citas (total) y productos (stock)
      await Promise.all([fetchCitas(), fetchProductos()])
    } catch (err) {
      toast.error(errorMessage(err, 'Error al agregar productos'))
    } finally {
      setGuardando(false)
    }
  }

  const cambiarCantidad = (producto: Producto, cantidad: number) => {
    const limite = Math.max(0, Math.min(cantidad, producto.stock_por_sede))
    setSelectedProductos((prev) => ({ ...prev, [producto.id]: limite }))
  }

  const porValidar = citas.filter((c) => c.estado === 'pendiente_validacion')
  const activas = citas
    .filter((c) => ACTIVAS.includes(c.estado))
    .sort((a, b) => `${a.fecha} ${a.hora_inicio}`.localeCompare(`${b.fecha} ${b.hora_inicio}`))
  const historial = citas.filter((c) => ['completada', 'cancelada'].includes(c.estado))
  const lista = filtro === 'validar' ? porValidar : filtro === 'activas' ? activas : historial

  const tab = (clave: Filtro, texto: string, n: number) => (
    <button
      onClick={() => setFiltro(clave)}
      className={`px-4 py-2 rounded-lg transition-all duration-300 ${filtro === clave ? 'btn-primary' : 'bg-white/10 text-gray-300 hover:bg-white/20'}`}
    >
      {texto} ({n})
    </button>
  )

  const puedeAgregarProductos = (cita: Cita) => ['confirmada', 'en_proceso', 'completada', 'pendiente'].includes(cita.estado)

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

      <div className="max-w-7xl mx-auto mt-8 p-6">
        <h2 className="text-3xl font-bold text-white mb-6">Citas de la Sede</h2>

        <div className="flex gap-4 mb-6 flex-wrap">
          {tab('validar', 'Pagos por validar', porValidar.length)}
          {tab('activas', 'Próximas', activas.length)}
          {tab('historial', 'Historial', historial.length)}
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
            <p className="text-gray-300">
              {filtro === 'validar' ? 'No hay comprobantes pendientes de validar' : 'No hay citas en esta lista'}
            </p>
          </div>
        ) : (
          <div className="grid gap-4">
            {lista.map((cita) => (
              <div key={cita.id} className="card">
                <div className="flex justify-between items-start mb-4">
                  <div>
                    <h3 className="text-lg font-semibold text-white">{cita.cliente.name}</h3>
                    <p className="text-gray-300">{cita.cliente.phone || 'Sin teléfono'}</p>
                    <p className="text-gray-300">Barbero: {cita.barbero.name}</p>
                  </div>
                  <div className="text-right">
                    <span className={`px-3 py-1 rounded-full text-sm backdrop-blur-sm ${estadoInfo(cita.estado).clase}`}>
                      {estadoInfo(cita.estado).label}
                    </span>
                    <p className="text-white mt-2 font-semibold text-xl">Total: {money(cita.monto_total)}</p>
                  </div>
                </div>

                <div className="mb-4">
                  <p className="text-gray-300">
                    <strong className="text-white">Fecha:</strong> {formatFecha(cita.fecha)}
                  </p>
                  <p className="text-gray-300">
                    <strong className="text-white">Hora:</strong> {formatHora(cita.hora_inicio)} - {formatHora(cita.hora_fin)}
                  </p>
                  <p className="text-gray-300">
                    <strong className="text-white">Pago:</strong> {cita.metodo_pago}
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
                  {cita.productos && cita.productos.length > 0 && (
                    <>
                      <p className="text-gray-300 mt-2">
                        <strong className="text-white">Productos:</strong>
                      </p>
                      <ul className="list-disc list-inside ml-4 text-gray-300">
                        {cita.productos.map((p) => (
                          <li key={p.id}>
                            {p.nombre} x{p.pivot.cantidad}
                          </li>
                        ))}
                      </ul>
                    </>
                  )}
                </div>

                {cita.estado === 'pendiente_validacion' && (
                  <div className="mb-4 flex flex-wrap items-center gap-3">
                    {cita.tiene_comprobante && (
                      <button onClick={() => verComprobante(cita)} className="text-purple-400 hover:text-purple-300 hover:underline transition-colors">
                        Ver comprobante
                      </button>
                    )}
                    <button
                      onClick={() => resolverComprobante(cita, 'confirmada')}
                      className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-all duration-300"
                    >
                      Validar Comprobante
                    </button>
                    <button onClick={() => resolverComprobante(cita, 'cancelada')} className="btn-danger">
                      Rechazar
                    </button>
                  </div>
                )}

                {puedeAgregarProductos(cita) && (
                  <div className="flex gap-2">
                    <button onClick={() => setSelectedCita(cita)} className="btn-primary">
                      Agregar Productos
                    </button>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}

        {selectedCita && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div className="card max-w-2xl w-full max-h-screen overflow-y-auto">
              <h3 className="text-xl font-bold text-white mb-4">Agregar Productos a la cita de {selectedCita.cliente.name}</h3>

              <div className="space-y-4">
                {productos.length === 0 && <p className="text-gray-300">No hay productos registrados en tu sede.</p>}
                {productos.map((producto) => {
                  const cantidad = selectedProductos[producto.id] || 0
                  return (
                    <div key={producto.id} className="flex items-center justify-between">
                      <div>
                        <p className="font-semibold text-white">{producto.nombre}</p>
                        <p className="text-gray-300">
                          {money(producto.precio)} - Stock: {producto.stock_por_sede}
                        </p>
                      </div>
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => cambiarCantidad(producto, cantidad - 1)}
                          className="bg-white/20 text-white px-3 py-1 rounded hover:bg-white/30 transition-colors"
                        >
                          -
                        </button>
                        <span className="text-white w-6 text-center">{cantidad}</span>
                        <button
                          onClick={() => cambiarCantidad(producto, cantidad + 1)}
                          disabled={cantidad >= producto.stock_por_sede}
                          className="bg-white/20 text-white px-3 py-1 rounded hover:bg-white/30 transition-colors disabled:opacity-40"
                        >
                          +
                        </button>
                      </div>
                    </div>
                  )
                })}
              </div>

              <div className="flex gap-4 mt-6">
                <button
                  onClick={() => agregarProductos(selectedCita.id)}
                  disabled={guardando}
                  className="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition-all duration-300 disabled:opacity-50"
                >
                  {guardando ? 'Guardando...' : 'Agregar'}
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

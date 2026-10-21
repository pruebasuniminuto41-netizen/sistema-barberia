import { useState, useEffect, useMemo } from 'react'
import { useAuth } from '../../context/AuthContext'
import api from '../../services/api'
import { toast } from 'react-toastify'
import type { Cita } from '../../types'
import { errorMessage, estadoInfo, formatFecha, formatHora, money, hoy, descargarCalendario } from '../../utils/helpers'

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

interface Confirmacion {
  cita: Cita
  mensaje: string
  enlace: string | null
}

const PUEDE_CANCELAR = ['pendiente', 'pendiente_validacion', 'confirmada']

const ClienteDashboard = () => {
  const { user, logout } = useAuth()

  const [sedes, setSedes] = useState<Sede[]>([])
  const [barberos, setBarberos] = useState<Barbero[]>([])
  const [servicios, setServicios] = useState<Servicio[]>([])
  const [selectedSede, setSelectedSede] = useState<number | null>(null)
  const [selectedBarbero, setSelectedBarbero] = useState<number | null>(null)
  const [selectedServicios, setSelectedServicios] = useState<number[]>([])
  const [fecha, setFecha] = useState('')
  const [hora, setHora] = useState('')
  const [horarios, setHorarios] = useState<string[]>([])
  const [cargandoHorarios, setCargandoHorarios] = useState(false)
  const [metodoPago, setMetodoPago] = useState('efectivo')
  const [comprobante, setComprobante] = useState<File | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [citas, setCitas] = useState<Cita[]>([])
  const [showHistorial, setShowHistorial] = useState(false)
  const [confirmacion, setConfirmacion] = useState<Confirmacion | null>(null)

  const handleLogout = async () => {
    try {
      await logout()
    } catch (err) {
      console.error('Error al cerrar sesión:', err)
    }
  }

  useEffect(() => {
    fetchSedes()
    fetchServicios()
    fetchCitas()
  }, [])

  useEffect(() => {
    setSelectedBarbero(null)
    setBarberos([])
    if (selectedSede) {
      fetchBarberos(selectedSede)
    }
  }, [selectedSede])

  const serviciosElegidos = useMemo(
    () => servicios.filter((s) => selectedServicios.includes(s.id)),
    [servicios, selectedServicios]
  )
  const duracionTotal = serviciosElegidos.reduce((sum, s) => sum + Number(s.duracion_minutos), 0)
  const precioTotal = serviciosElegidos.reduce((sum, s) => sum + Number(s.precio), 0)

  // Horarios libres del barbero para la fecha y la duración elegidas
  useEffect(() => {
    setHora('')
    setHorarios([])
    if (!selectedBarbero || !fecha || duracionTotal <= 0) return

    let vigente = true
    setCargandoHorarios(true)
    api
      .get(`/barberos/${selectedBarbero}/disponibilidad`, { params: { fecha, duracion: duracionTotal } })
      .then((res) => vigente && setHorarios(res.data.horarios))
      .catch((err) => vigente && setError(errorMessage(err, 'No se pudieron cargar los horarios')))
      .finally(() => vigente && setCargandoHorarios(false))

    return () => {
      vigente = false
    }
  }, [selectedBarbero, fecha, duracionTotal])

  const fetchSedes = async () => {
    try {
      const response = await api.get('/sedes')
      setSedes(response.data)
    } catch (err) {
      setError(errorMessage(err, 'Error al cargar sedes'))
    }
  }

  const fetchBarberos = async (sedeId: number) => {
    try {
      const response = await api.get(`/sedes/${sedeId}/barberos`)
      setBarberos(response.data)
    } catch (err) {
      setError(errorMessage(err, 'Error al cargar barberos'))
    }
  }

  const fetchServicios = async () => {
    try {
      const response = await api.get('/servicios')
      setServicios(response.data)
    } catch (err) {
      setError(errorMessage(err, 'Error al cargar servicios'))
    }
  }

  const fetchCitas = async () => {
    try {
      const response = await api.get('/citas')
      setCitas(response.data)
    } catch (err) {
      setError(errorMessage(err, 'Error al cargar citas'))
    }
  }

  const cancelCita = async (cita: Cita) => {
    if (!window.confirm(`¿Cancelar tu cita del ${formatFecha(cita.fecha)} a las ${formatHora(cita.hora_inicio)}?`)) return
    try {
      await api.put(`/citas/${cita.id}`, { estado: 'cancelada' })
      toast.success('Cita cancelada. Te enviamos la confirmación por correo')
      fetchCitas()
    } catch (err) {
      toast.error(errorMessage(err, 'Error al cancelar cita'))
    }
  }

  const agregarACalendario = async (citaId: number) => {
    try {
      await descargarCalendario(citaId)
    } catch (err) {
      toast.error(errorMessage(err, 'No se pudo descargar el archivo de calendario'))
    }
  }

  const handleServicioToggle = (servicioId: number) => {
    setSelectedServicios((prev) =>
      prev.includes(servicioId) ? prev.filter((id) => id !== servicioId) : [...prev, servicioId]
    )
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')

    if (selectedServicios.length === 0) return setError('Selecciona al menos un servicio')
    if (!hora) return setError('Selecciona un horario disponible')
    if (metodoPago === 'transferencia' && !comprobante) return setError('Adjunta el comprobante de la transferencia')

    setLoading(true)
    try {
      const formData = new FormData()
      formData.append('sede_id', String(selectedSede))
      formData.append('barbero_id', String(selectedBarbero))
      formData.append('fecha', fecha)
      formData.append('hora_inicio', hora)
      formData.append('metodo_pago', metodoPago)
      formData.append('servicios', JSON.stringify(selectedServicios.map((id) => ({ id }))))
      if (metodoPago === 'transferencia' && comprobante) {
        formData.append('comprobante_pago', comprobante)
      }

      const response = await api.post('/citas', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      setConfirmacion({
        cita: response.data.cita,
        mensaje: response.data.message,
        enlace: response.data.enlace_calendario,
      })
      toast.success(response.data.message)

      // Reinicia el formulario y muestra la confirmación en "Mis Citas"
      setSelectedSede(null)
      setSelectedServicios([])
      setFecha('')
      setHora('')
      setMetodoPago('efectivo')
      setComprobante(null)
      await fetchCitas()
      setShowHistorial(true)
    } catch (err) {
      setError(errorMessage(err, 'Error al reservar cita'))
      // Si el horario se ocupó mientras reservabas, refresca los horarios libres
      if (selectedBarbero && fecha && duracionTotal > 0) {
        api
          .get(`/barberos/${selectedBarbero}/disponibilidad`, { params: { fecha, duracion: duracionTotal } })
          .then((res) => {
            setHorarios(res.data.horarios)
            setHora('')
          })
          .catch(() => undefined)
      }
    } finally {
      setLoading(false)
    }
  }

  const sedeActual = sedes.find((s) => s.id === selectedSede)

  return (
    <div className="min-h-screen">
      <nav className="bg-white/10 backdrop-blur-lg border-b border-white/20">
        <div className="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
          <h1 className="text-2xl font-bold gradient-text">Barbería Pro</h1>
          <div className="flex items-center gap-4">
            <span className="text-gray-300">Hola, {user?.name}</span>
            <button onClick={handleLogout} className="btn-danger">
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

        {error && (
          <div className="bg-red-500/20 border border-red-500/50 text-red-200 px-4 py-3 rounded-lg mb-4 backdrop-blur-sm">
            {error}
          </div>
        )}

        {!showHistorial ? (
          <>
            <h2 className="text-3xl font-bold text-white mb-6">Reservar Cita</h2>

            <form onSubmit={handleSubmit} className="card">
              <div className="mb-4">
                <label className="block text-gray-300 text-sm font-semibold mb-2">Sede</label>
                <select
                  value={selectedSede || ''}
                  onChange={(e) => setSelectedSede(e.target.value ? Number(e.target.value) : null)}
                  className="input-field"
                  required
                >
                  <option value="">Selecciona una sede</option>
                  {sedes.map((sede) => (
                    <option key={sede.id} value={sede.id}>
                      {sede.nombre} - {sede.direccion}
                    </option>
                  ))}
                </select>
              </div>

              <div className="mb-4">
                <label className="block text-gray-300 text-sm font-semibold mb-2">Barbero</label>
                <select
                  value={selectedBarbero || ''}
                  onChange={(e) => setSelectedBarbero(e.target.value ? Number(e.target.value) : null)}
                  className="input-field"
                  required
                  disabled={!selectedSede}
                >
                  <option value="">Selecciona un barbero</option>
                  {barberos.map((barbero) => (
                    <option key={barbero.id} value={barbero.id}>
                      {barbero.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="mb-4">
                <label className="block text-gray-300 text-sm font-semibold mb-2">Servicios</label>
                <div className="space-y-2">
                  {servicios.map((servicio) => (
                    <label
                      key={servicio.id}
                      className="flex items-center text-gray-300 cursor-pointer hover:text-white transition-colors"
                    >
                      <input
                        type="checkbox"
                        checked={selectedServicios.includes(servicio.id)}
                        onChange={() => handleServicioToggle(servicio.id)}
                        className="mr-3 w-5 h-5 accent-purple-500"
                      />
                      <span>
                        {servicio.nombre} - {money(servicio.precio)} ({servicio.duracion_minutos} min)
                      </span>
                    </label>
                  ))}
                </div>
                {serviciosElegidos.length > 0 && (
                  <p className="mt-3 text-sm text-purple-300">
                    Duración total: {duracionTotal} min · Total: <strong>{money(precioTotal)}</strong>
                  </p>
                )}
              </div>

              <div className="mb-4">
                <label className="block text-gray-300 text-sm font-semibold mb-2">Fecha</label>
                <input
                  type="date"
                  value={fecha}
                  min={hoy()}
                  onChange={(e) => setFecha(e.target.value)}
                  className="input-field"
                  required
                />
              </div>

              <div className="mb-4">
                <label className="block text-gray-300 text-sm font-semibold mb-2">Hora</label>
                <select
                  value={hora}
                  onChange={(e) => setHora(e.target.value)}
                  className="input-field"
                  required
                  disabled={!selectedBarbero || !fecha || duracionTotal <= 0 || cargandoHorarios}
                >
                  <option value="">
                    {!selectedBarbero || !fecha || duracionTotal <= 0
                      ? 'Elige barbero, servicios y fecha primero'
                      : cargandoHorarios
                        ? 'Buscando horarios...'
                        : horarios.length === 0
                          ? 'No hay horarios libres ese día'
                          : 'Selecciona un horario'}
                  </option>
                  {horarios.map((h) => (
                    <option key={h} value={h}>
                      {h}
                    </option>
                  ))}
                </select>
              </div>

              <div className="mb-4">
                <label className="block text-gray-300 text-sm font-semibold mb-2">Método de Pago</label>
                <select
                  value={metodoPago}
                  onChange={(e) => setMetodoPago(e.target.value)}
                  className="input-field"
                  required
                >
                  <option value="efectivo">Efectivo (pagas en la barbería)</option>
                  <option value="transferencia">Transferencia (adjuntas comprobante)</option>
                </select>
                <p className="mt-2 text-sm text-gray-400">
                  {metodoPago === 'efectivo'
                    ? 'Tu cita queda confirmada al instante y te enviamos el detalle por correo con el evento para tu calendario.'
                    : 'Tu horario queda reservado. Cuando validemos el comprobante te confirmamos por correo.'}
                </p>
              </div>

              {metodoPago === 'transferencia' && (
                <div className="mb-4">
                  <label className="block text-gray-300 text-sm font-semibold mb-2">
                    Comprobante de Pago (imagen o PDF, máx. 2 MB)
                  </label>
                  <input
                    type="file"
                    accept=".jpg,.jpeg,.png,.pdf,image/*,application/pdf"
                    onChange={(e) => setComprobante(e.target.files?.[0] || null)}
                    className="input-field"
                    required
                  />
                </div>
              )}

              {hora && sedeActual && (
                <div className="mb-4 p-3 rounded-lg bg-purple-500/10 border border-purple-500/30 text-gray-200 text-sm">
                  Resumen: {formatFecha(fecha)} a las <strong>{hora}</strong> en {sedeActual.nombre} · {money(precioTotal)}
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

            {confirmacion && (
              <div className="card mb-6 border border-green-500/40">
                <div className="flex justify-between items-start">
                  <div>
                    <h3 className="text-xl font-semibold text-green-300 mb-1">¡Reserva registrada!</h3>
                    <p className="text-gray-200">{confirmacion.mensaje}</p>
                    <p className="text-gray-300 mt-2">
                      {formatFecha(confirmacion.cita.fecha)} · {formatHora(confirmacion.cita.hora_inicio)} -{' '}
                      {formatHora(confirmacion.cita.hora_fin)} · {confirmacion.cita.sede.nombre}
                    </p>
                  </div>
                  <button onClick={() => setConfirmacion(null)} className="text-gray-400 hover:text-white">
                    ✕
                  </button>
                </div>
                <div className="flex flex-wrap gap-3 mt-4">
                  {confirmacion.enlace && (
                    <a href={confirmacion.enlace} target="_blank" rel="noopener noreferrer" className="btn-primary">
                      Agregar a Google Calendar
                    </a>
                  )}
                  <button onClick={() => agregarACalendario(confirmacion.cita.id)} className="btn-secondary">
                    Descargar para otro calendario (.ics)
                  </button>
                </div>
              </div>
            )}

            {citas.length === 0 ? (
              <div className="card text-center">
                <p className="text-gray-300">No tienes citas registradas</p>
              </div>
            ) : (
              <div className="grid gap-4">
                {citas.map((cita) => (
                  <div key={cita.id} className="card">
                    <div className="flex justify-between items-start mb-4">
                      <div>
                        <h3 className="text-lg font-semibold text-white">{cita.sede.nombre}</h3>
                        <p className="text-gray-300">Barbero: {cita.barbero.name}</p>
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
                      <p className="mb-4 text-sm text-orange-300">
                        Estamos revisando tu comprobante. Te avisaremos por correo cuando tu cita quede confirmada.
                      </p>
                    )}

                    <div className="flex flex-wrap gap-3">
                      {cita.estado !== 'cancelada' && (
                        <>
                          {cita.enlace_calendario && (
                            <a
                              href={cita.enlace_calendario}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="btn-secondary text-sm"
                            >
                              Google Calendar
                            </a>
                          )}
                          <button onClick={() => agregarACalendario(cita.id)} className="btn-secondary text-sm">
                            Descargar .ics
                          </button>
                        </>
                      )}
                      {PUEDE_CANCELAR.includes(cita.estado) && (
                        <button onClick={() => cancelCita(cita)} className="btn-danger text-sm">
                          Cancelar Cita
                        </button>
                      )}
                    </div>
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

import { useState, useEffect, useCallback } from 'react'
import { useAuth } from '../../context/AuthContext'
import api from '../../services/api'
import { toast } from 'react-toastify'
import type { Cita, Producto } from '../../types'
import { abrirArchivo, errorMessage, estadoInfo, formatFecha, formatHora, money } from '../../utils/helpers'
import EntityModal, { type Field } from './EntityModal'

interface User {
  id: number
  name: string
  email: string
  phone?: string | null
  sede_id?: number | null
  roles?: Array<{ name: string }>
  sede?: { nombre: string } | null
}

interface Sede {
  id: number
  nombre: string
  direccion: string
  telefono: string
}

interface Servicio {
  id: number
  nombre: string
  descripcion?: string | null
  precio: number
  duracion_minutos: number
}

interface Metricas {
  ingresos_por_sede: Array<{ sede: string; total: number | string }>
  ingresos_por_barbero: Array<{ barbero: string; total: number | string }>
  productos_mas_vendidos: Array<{ producto: string; cantidad: number | string }>
  volumen_citas: number
}

type Entidad = 'users' | 'sedes' | 'servicios' | 'productos'
type Tab = 'dashboard' | 'citas' | Entidad

const ROLES = [
  { value: 'cliente', label: 'Cliente' },
  { value: 'barbero', label: 'Barbero' },
  { value: 'supervisor', label: 'Supervisor' },
  { value: 'admin', label: 'Administrador' },
]

const ACTIVAS = ['pendiente', 'pendiente_validacion', 'confirmada', 'en_proceso']

const AdminDashboard = () => {
  const { user, logout } = useAuth()
  const [activeTab, setActiveTab] = useState<Tab>('dashboard')
  const [users, setUsers] = useState<User[]>([])
  const [sedes, setSedes] = useState<Sede[]>([])
  const [servicios, setServicios] = useState<Servicio[]>([])
  const [productos, setProductos] = useState<Producto[]>([])
  const [citas, setCitas] = useState<Cita[]>([])
  const [metricas, setMetricas] = useState<Metricas | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [filtroEstado, setFiltroEstado] = useState('activas')

  // Modal de crear / editar: { entidad, registro } (registro = null al crear)
  const [modal, setModal] = useState<{ entidad: Entidad; registro: any | null } | null>(null)

  const cargar = useCallback(async () => {
    try {
      const [u, s, sv, p, c, m] = await Promise.all([
        api.get('/users'),
        api.get('/sedes'),
        api.get('/servicios'),
        api.get('/productos'),
        api.get('/citas'),
        api.get('/admin/metricas'),
      ])
      setUsers(u.data)
      setSedes(s.data)
      setServicios(sv.data)
      setProductos(p.data)
      setCitas(c.data)
      setMetricas(m.data)
      setError('')
    } catch (err) {
      setError(errorMessage(err, 'Error al cargar datos'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    cargar()
  }, [cargar])

  const rolDe = (u: User) => u.roles?.[0]?.name || 'cliente'
  const sedeOptions = sedes.map((s) => ({ value: s.id, label: s.nombre }))

  // ----------------------------------------------------------------- formularios
  const campos = (entidad: Entidad, editando: boolean): Field[] => {
    switch (entidad) {
      case 'users':
        return [
          { name: 'name', label: 'Nombre', type: 'text', required: true },
          { name: 'email', label: 'Correo', type: 'email', required: true },
          {
            name: 'password',
            label: editando ? 'Nueva contraseña' : 'Contraseña',
            type: 'password',
            required: !editando,
            help: editando ? 'Déjala vacía para no cambiarla. Mínimo 8 caracteres.' : 'Mínimo 8 caracteres.',
          },
          { name: 'phone', label: 'Teléfono', type: 'text' },
          { name: 'role', label: 'Rol', type: 'select', required: true, options: ROLES },
          {
            name: 'sede_id',
            label: 'Sede',
            type: 'select',
            options: sedeOptions,
            help: 'Obligatoria para barberos y supervisores.',
          },
        ]
      case 'sedes':
        return [
          { name: 'nombre', label: 'Nombre', type: 'text', required: true },
          { name: 'direccion', label: 'Dirección', type: 'text', required: true },
          { name: 'telefono', label: 'Teléfono', type: 'text', required: true },
        ]
      case 'servicios':
        return [
          { name: 'nombre', label: 'Nombre', type: 'text', required: true },
          { name: 'descripcion', label: 'Descripción', type: 'textarea' },
          { name: 'precio', label: 'Precio', type: 'number', required: true },
          { name: 'duracion_minutos', label: 'Duración (minutos)', type: 'number', required: true },
        ]
      case 'productos':
        return [
          { name: 'nombre', label: 'Nombre', type: 'text', required: true },
          { name: 'descripcion', label: 'Descripción', type: 'textarea' },
          { name: 'precio', label: 'Precio', type: 'number', required: true },
          { name: 'stock_por_sede', label: 'Stock', type: 'number', required: true },
          { name: 'sede_id', label: 'Sede', type: 'select', required: true, options: sedeOptions },
        ]
    }
  }

  const valoresIniciales = (entidad: Entidad, registro: any | null): Record<string, any> => {
    if (!registro) {
      return entidad === 'users' ? { role: 'cliente' } : {}
    }
    if (entidad === 'users') {
      return { ...registro, password: '', role: rolDe(registro), sede_id: registro.sede_id ?? '' }
    }
    return { ...registro, sede_id: registro.sede_id ?? '' }
  }

  const guardar = async (entidad: Entidad, registro: any | null, values: Record<string, any>) => {
    const datos: Record<string, any> = {}
    for (const f of campos(entidad, !!registro)) {
      const v = values[f.name]
      if (f.type === 'number') {
        if (v !== '' && v !== undefined && v !== null) datos[f.name] = Number(v)
      } else if (f.type === 'select' && f.name === 'sede_id') {
        datos[f.name] = v === '' || v === undefined ? null : Number(v)
      } else if (v !== '' && v !== undefined && v !== null) {
        datos[f.name] = v
      }
    }

    if (entidad === 'users' && ['barbero', 'supervisor'].includes(datos.role) && !datos.sede_id) {
      toast.error('Un barbero o supervisor debe tener una sede')
      return
    }

    try {
      if (registro) {
        await api.put(`/${entidad}/${registro.id}`, datos)
        toast.success('Cambios guardados')
      } else {
        await api.post(`/${entidad}`, datos)
        toast.success('Creado exitosamente')
      }
      setModal(null)
      cargar()
    } catch (err) {
      toast.error(errorMessage(err, 'No se pudo guardar'))
    }
  }

  const eliminar = async (entidad: Entidad, registro: { id: number }, nombre: string) => {
    if (!window.confirm(`¿Eliminar "${nombre}"? Esta acción no se puede deshacer.`)) return
    try {
      await api.delete(`/${entidad}/${registro.id}`)
      toast.success('Eliminado')
      cargar()
    } catch (err) {
      toast.error(errorMessage(err, 'No se pudo eliminar'))
    }
  }

  // ----------------------------------------------------------------- citas
  const cambiarEstado = async (cita: Cita, estado: string) => {
    if (!window.confirm(`¿Cancelar la cita de ${cita.cliente.name}? Se le avisará por correo.`)) return
    try {
      await api.put(`/citas/${cita.id}`, { estado })
      toast.success('Cita actualizada')
      cargar()
    } catch (err) {
      toast.error(errorMessage(err, 'No se pudo actualizar la cita'))
    }
  }

  const resolverComprobante = async (cita: Cita, estado: 'confirmada' | 'cancelada') => {
    if (!window.confirm(estado === 'confirmada' ? '¿Validar el comprobante y confirmar la cita?' : '¿Rechazar el comprobante y cancelar la cita?')) return
    try {
      await api.post(`/citas/${cita.id}/validar-comprobante`, { estado })
      toast.success(estado === 'confirmada' ? 'Cita confirmada' : 'Cita cancelada')
      cargar()
    } catch (err) {
      toast.error(errorMessage(err, 'Error al procesar el comprobante'))
    }
  }

  const verComprobante = async (cita: Cita) => {
    try {
      await abrirArchivo(`/citas/${cita.id}/comprobante`)
    } catch (err) {
      toast.error(errorMessage(err, 'No se pudo abrir el comprobante'))
    }
  }

  // ----------------------------------------------------------------- vistas
  const suma = (items: Array<{ total: number | string }> | undefined) =>
    (items || []).reduce((acc, item) => acc + Number(item.total), 0)

  const acciones = (entidad: Entidad, registro: any, nombre: string) => (
    <div className="flex gap-2">
      <button onClick={() => setModal({ entidad, registro })} className="btn-secondary text-sm">
        Editar
      </button>
      <button onClick={() => eliminar(entidad, registro, nombre)} className="btn-danger text-sm">
        Eliminar
      </button>
    </div>
  )

  const cabecera = (titulo: string, entidad: Entidad, boton: string) => (
    <div className="flex justify-between items-center mb-6">
      <h3 className="text-xl font-bold text-white">{titulo}</h3>
      <button onClick={() => setModal({ entidad, registro: null })} className="btn-primary">
        {boton}
      </button>
    </div>
  )

  const renderDashboard = () => (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div className="card">
        <h3 className="text-lg font-semibold mb-2 text-white">Volumen de Citas</h3>
        <p className="text-3xl font-bold gradient-text">{metricas?.volumen_citas || 0}</p>
      </div>
      <div className="card">
        <h3 className="text-lg font-semibold mb-2 text-white">Ingresos Totales</h3>
        <p className="text-3xl font-bold text-green-400">{money(suma(metricas?.ingresos_por_sede))}</p>
        <p className="text-xs text-gray-400 mt-1">Solo citas completadas</p>
      </div>
      <div className="card">
        <h3 className="text-lg font-semibold mb-2 text-white">Sedes Activas</h3>
        <p className="text-3xl font-bold text-purple-400">{sedes.length}</p>
      </div>
      <div className="card">
        <h3 className="text-lg font-semibold mb-2 text-white">Usuarios Registrados</h3>
        <p className="text-3xl font-bold text-orange-400">{users.length}</p>
      </div>

      <div className="col-span-1 md:col-span-2 card">
        <h3 className="text-lg font-semibold mb-4 text-white">Ingresos por Sede</h3>
        <div className="space-y-2">
          {(metricas?.ingresos_por_sede.length ?? 0) === 0 && <p className="text-gray-400">Aún no hay citas completadas</p>}
          {metricas?.ingresos_por_sede.map((item, index) => (
            <div key={index} className="flex justify-between items-center text-gray-300">
              <span>{item.sede}</span>
              <span className="font-semibold text-white">{money(item.total)}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="col-span-1 md:col-span-2 card">
        <h3 className="text-lg font-semibold mb-4 text-white">Ingresos por Barbero</h3>
        <div className="space-y-2">
          {(metricas?.ingresos_por_barbero.length ?? 0) === 0 && <p className="text-gray-400">Aún no hay citas completadas</p>}
          {metricas?.ingresos_por_barbero.map((item, index) => (
            <div key={index} className="flex justify-between items-center text-gray-300">
              <span>{item.barbero}</span>
              <span className="font-semibold text-white">{money(item.total)}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="col-span-1 md:col-span-2 lg:col-span-4 card">
        <h3 className="text-lg font-semibold mb-4 text-white">Productos Más Vendidos</h3>
        <div className="space-y-2">
          {(metricas?.productos_mas_vendidos.length ?? 0) === 0 && <p className="text-gray-400">Aún no hay ventas de productos</p>}
          {metricas?.productos_mas_vendidos.map((item, index) => (
            <div key={index} className="flex justify-between items-center text-gray-300">
              <span>{item.producto}</span>
              <span className="font-semibold text-white">{item.cantidad} unidades</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  )

  const renderCitas = () => {
    const lista = citas
      .filter((c) =>
        filtroEstado === 'todas' ? true : filtroEstado === 'activas' ? ACTIVAS.includes(c.estado) : c.estado === filtroEstado
      )
      .sort((a, b) => `${b.fecha} ${b.hora_inicio}`.localeCompare(`${a.fecha} ${a.hora_inicio}`))

    return (
      <div className="card">
        <div className="flex justify-between items-center mb-6 flex-wrap gap-3">
          <h3 className="text-xl font-bold text-white">Citas</h3>
          <select className="input-field max-w-xs" value={filtroEstado} onChange={(e) => setFiltroEstado(e.target.value)}>
            <option value="activas">Activas (pendientes y en curso)</option>
            <option value="pendiente_validacion">Pagos por validar</option>
            <option value="confirmada">Confirmadas</option>
            <option value="completada">Completadas</option>
            <option value="cancelada">Canceladas</option>
            <option value="todas">Todas</option>
          </select>
        </div>
        {lista.length === 0 ? (
          <p className="text-gray-300">No hay citas con este filtro.</p>
        ) : (
          <div className="grid gap-4">
            {lista.map((cita) => (
              <div key={cita.id} className="border border-white/20 rounded-lg p-4">
                <div className="flex justify-between items-start flex-wrap gap-2">
                  <div>
                    <h4 className="font-semibold text-white">
                      {cita.cliente.name} <span className="text-gray-400 font-normal">· {cita.sede.nombre}</span>
                    </h4>
                    <p className="text-gray-300">
                      {formatFecha(cita.fecha)} · {formatHora(cita.hora_inicio)} - {formatHora(cita.hora_fin)}
                    </p>
                    <p className="text-gray-300">
                      Barbero: {cita.barbero.name} · {cita.servicios.map((s) => s.nombre).join(', ')}
                    </p>
                    <p className="text-gray-300">
                      {money(cita.monto_total)} ({cita.metodo_pago})
                    </p>
                  </div>
                  <span className={`px-3 py-1 rounded-full text-sm ${estadoInfo(cita.estado).clase}`}>{estadoInfo(cita.estado).label}</span>
                </div>
                <div className="flex flex-wrap gap-2 mt-3">
                  {cita.estado === 'pendiente_validacion' && (
                    <>
                      {cita.tiene_comprobante && (
                        <button onClick={() => verComprobante(cita)} className="text-purple-400 hover:underline text-sm">
                          Ver comprobante
                        </button>
                      )}
                      <button
                        onClick={() => resolverComprobante(cita, 'confirmada')}
                        className="bg-green-600 text-white px-3 py-1 rounded-lg hover:bg-green-700 text-sm"
                      >
                        Validar
                      </button>
                      <button onClick={() => resolverComprobante(cita, 'cancelada')} className="btn-danger text-sm">
                        Rechazar
                      </button>
                    </>
                  )}
                  {['pendiente', 'confirmada'].includes(cita.estado) && (
                    <button onClick={() => cambiarEstado(cita, 'cancelada')} className="btn-danger text-sm">
                      Cancelar cita
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    )
  }

  const renderUsers = () => (
    <div className="card">
      {cabecera('Usuarios', 'users', 'Nuevo Usuario')}
      <div className="overflow-x-auto">
        <table className="min-w-full">
          <thead>
            <tr className="border-b border-white/20">
              <th className="px-4 py-2 text-left text-gray-300">Nombre</th>
              <th className="px-4 py-2 text-left text-gray-300">Correo</th>
              <th className="px-4 py-2 text-left text-gray-300">Teléfono</th>
              <th className="px-4 py-2 text-left text-gray-300">Rol</th>
              <th className="px-4 py-2 text-left text-gray-300">Sede</th>
              <th className="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody>
            {users.map((u) => (
              <tr key={u.id} className="border-b border-white/10">
                <td className="px-4 py-2 text-white">{u.name}</td>
                <td className="px-4 py-2 text-gray-300">{u.email}</td>
                <td className="px-4 py-2 text-gray-300">{u.phone || '-'}</td>
                <td className="px-4 py-2 text-gray-300">{ROLES.find((r) => r.value === rolDe(u))?.label || rolDe(u)}</td>
                <td className="px-4 py-2 text-gray-300">{u.sede?.nombre || '-'}</td>
                <td className="px-4 py-2">{u.id !== user?.id && acciones('users', u, u.name)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )

  const renderSedes = () => (
    <div className="card">
      {cabecera('Sedes', 'sedes', 'Nueva Sede')}
      <div className="grid gap-4">
        {sedes.map((sede) => (
          <div key={sede.id} className="border border-white/20 rounded-lg p-4 flex justify-between items-center flex-wrap gap-2">
            <div>
              <h4 className="font-semibold text-white">{sede.nombre}</h4>
              <p className="text-gray-300">{sede.direccion}</p>
              <p className="text-gray-300">{sede.telefono}</p>
            </div>
            {acciones('sedes', sede, sede.nombre)}
          </div>
        ))}
      </div>
    </div>
  )

  const renderServicios = () => (
    <div className="card">
      {cabecera('Servicios', 'servicios', 'Nuevo Servicio')}
      <div className="grid gap-4">
        {servicios.map((servicio) => (
          <div key={servicio.id} className="border border-white/20 rounded-lg p-4 flex justify-between items-center flex-wrap gap-2">
            <div>
              <h4 className="font-semibold text-white">{servicio.nombre}</h4>
              <p className="text-gray-300">{servicio.descripcion}</p>
              <p className="text-gray-300">
                {money(servicio.precio)} - {servicio.duracion_minutos} min
              </p>
            </div>
            {acciones('servicios', servicio, servicio.nombre)}
          </div>
        ))}
      </div>
    </div>
  )

  const renderProductos = () => (
    <div className="card">
      {cabecera('Productos', 'productos', 'Nuevo Producto')}
      <div className="grid gap-4">
        {productos.map((producto) => (
          <div key={producto.id} className="border border-white/20 rounded-lg p-4 flex justify-between items-center flex-wrap gap-2">
            <div>
              <h4 className="font-semibold text-white">
                {producto.nombre} <span className="text-gray-400 font-normal">· {producto.sede?.nombre || 'Sin sede'}</span>
              </h4>
              <p className="text-gray-300">{money(producto.precio)}</p>
              <p className={producto.stock_por_sede <= 5 ? 'text-orange-300' : 'text-gray-300'}>
                Stock: {producto.stock_por_sede}
                {producto.stock_por_sede <= 5 && ' (bajo)'}
              </p>
            </div>
            {acciones('productos', producto, producto.nombre)}
          </div>
        ))}
      </div>
    </div>
  )

  const tabs: Array<{ id: Tab; label: string }> = [
    { id: 'dashboard', label: 'Dashboard' },
    { id: 'citas', label: `Citas (${citas.filter((c) => c.estado === 'pendiente_validacion').length} por validar)` },
    { id: 'users', label: 'Usuarios' },
    { id: 'sedes', label: 'Sedes' },
    { id: 'servicios', label: 'Servicios' },
    { id: 'productos', label: 'Productos' },
  ]

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
        <div className="flex gap-4 mb-6 flex-wrap">
          {tabs.map((t) => (
            <button
              key={t.id}
              onClick={() => {
                setActiveTab(t.id)
                cargar()
              }}
              className={`px-4 py-2 rounded-lg transition-all duration-300 ${activeTab === t.id ? 'btn-primary' : 'bg-white/10 text-gray-300 hover:bg-white/20'}`}
            >
              {t.label}
            </button>
          ))}
        </div>

        {error && (
          <div className="bg-red-500/20 border border-red-500/50 text-red-200 px-4 py-3 rounded-lg mb-4 backdrop-blur-sm">{error}</div>
        )}

        {loading ? (
          <div className="text-center py-8 text-gray-300">Cargando...</div>
        ) : (
          <>
            {activeTab === 'dashboard' && renderDashboard()}
            {activeTab === 'citas' && renderCitas()}
            {activeTab === 'users' && renderUsers()}
            {activeTab === 'sedes' && renderSedes()}
            {activeTab === 'servicios' && renderServicios()}
            {activeTab === 'productos' && renderProductos()}
          </>
        )}

        {modal && (
          <EntityModal
            key={`${modal.entidad}-${modal.registro?.id ?? 'nuevo'}`}
            title={`${modal.registro ? 'Editar' : 'Nuevo'} ${
              { users: 'usuario', sedes: 'sede', servicios: 'servicio', productos: 'producto' }[modal.entidad]
            }`}
            fields={campos(modal.entidad, !!modal.registro)}
            initial={valoresIniciales(modal.entidad, modal.registro)}
            submitLabel={modal.registro ? 'Guardar' : 'Crear'}
            onSubmit={(values) => guardar(modal.entidad, modal.registro, values)}
            onClose={() => setModal(null)}
          />
        )}
      </div>
    </div>
  )
}

export default AdminDashboard

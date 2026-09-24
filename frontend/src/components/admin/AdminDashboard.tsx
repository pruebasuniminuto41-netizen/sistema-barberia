import { useState, useEffect } from 'react'
import { useAuth } from '../../context/AuthContext'
import api from '../../services/api'
import { toast } from 'react-toastify'

interface User {
  id: number
  name: string
  email: string
  role: string
  sede_id?: number
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
  descripcion: string
  precio: number
  duracion_minutos: number
}

interface Producto {
  id: number
  nombre: string
  descripcion: string
  precio: number
  stock_por_sede: number
  sede_id?: number
}

interface Metricas {
  ingresos_por_sede: Array<{ sede: string; total: number }>
  ingresos_por_barbero: Array<{ barbero: string; total: number }>
  productos_mas_vendidos: Array<{ producto: string; cantidad: number }>
  volumen_citas: number
}

const AdminDashboard = () => {
  const { user, logout } = useAuth()
  const [activeTab, setActiveTab] = useState('dashboard')
  const [users, setUsers] = useState<User[]>([])
  const [sedes, setSedes] = useState<Sede[]>([])
  const [servicios, setServicios] = useState<Servicio[]>([])
  const [productos, setProductos] = useState<Producto[]>([])
  const [metricas, setMetricas] = useState<Metricas | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // Form states
  const [showUserForm, setShowUserForm] = useState(false)
  const [showSedeForm, setShowSedeForm] = useState(false)
  const [showServicioForm, setShowServicioForm] = useState(false)
  const [showProductoForm, setShowProductoForm] = useState(false)

  // New item states
  const [newUser, setNewUser] = useState({ name: '', email: '', password: '', phone: '', role: 'cliente', sede_id: '' })
  const [newSede, setNewSede] = useState({ nombre: '', direccion: '', telefono: '' })
  const [newServicio, setNewServicio] = useState({ nombre: '', descripcion: '', precio: '', duracion_minutos: '' })
  const [newProducto, setNewProducto] = useState({ nombre: '', descripcion: '', precio: '', stock_por_sede: '', sede_id: '' })

  useEffect(() => {
    fetchData()
  }, [activeTab])

  // Cargar datos iniciales
  useEffect(() => {
    const loadInitialData = async () => {
      try {
        await Promise.all([
          fetchUsers(),
          fetchSedes(),
          fetchServicios(),
          fetchProductos(),
          fetchMetricas()
        ])
      } catch (err) {
        setError('Error al cargar datos iniciales')
      } finally {
        setLoading(false)
      }
    }
    loadInitialData()
  }, [])

  const fetchData = async () => {
    setLoading(true)
    setError('')
    try {
      switch (activeTab) {
        case 'users':
          await fetchUsers()
          break
        case 'sedes':
          await fetchSedes()
          break
        case 'servicios':
          await fetchServicios()
          break
        case 'productos':
          await fetchProductos()
          break
        case 'dashboard':
          await fetchMetricas()
          break
      }
    } catch (err) {
      setError('Error al cargar datos')
    } finally {
      setLoading(false)
    }
  }

  const fetchUsers = async () => {
    const response = await api.get('/users')
    setUsers(response.data)
  }

  const fetchSedes = async () => {
    const response = await api.get('/sedes')
    setSedes(response.data)
  }

  const fetchServicios = async () => {
    const response = await api.get('/servicios')
    setServicios(response.data)
  }

  const fetchProductos = async () => {
    const response = await api.get('/productos')
    setProductos(response.data)
  }

  const fetchMetricas = async () => {
    const response = await api.get('/admin/metricas')
    setMetricas(response.data)
  }

  const handleCreateUser = async () => {
    try {
      await api.post('/users', newUser)
      toast.success('Usuario creado exitosamente')
      setShowUserForm(false)
      setNewUser({ name: '', email: '', password: '', phone: '', role: 'cliente', sede_id: '' })
      fetchUsers()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Error al crear usuario')
    }
  }

  const handleCreateSede = async () => {
    try {
      await api.post('/sedes', newSede)
      toast.success('Sede creada exitosamente')
      setShowSedeForm(false)
      setNewSede({ nombre: '', direccion: '', telefono: '' })
      fetchSedes()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Error al crear sede')
    }
  }

  const handleCreateServicio = async () => {
    try {
      await api.post('/servicios', { ...newServicio, precio: Number(newServicio.precio), duracion_minutos: Number(newServicio.duracion_minutos) })
      toast.success('Servicio creado exitosamente')
      setShowServicioForm(false)
      setNewServicio({ nombre: '', descripcion: '', precio: '', duracion_minutos: '' })
      fetchServicios()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Error al crear servicio')
    }
  }

  const handleCreateProducto = async () => {
    try {
      await api.post('/productos', { 
        ...newProducto, 
        precio: Number(newProducto.precio), 
        stock_por_sede: Number(newProducto.stock_por_sede),
        sede_id: Number(newProducto.sede_id) || sedes[0]?.id
      })
      toast.success('Producto creado exitosamente')
      setShowProductoForm(false)
      setNewProducto({ nombre: '', descripcion: '', precio: '', stock_por_sede: '', sede_id: '' })
      fetchProductos()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Error al crear producto')
    }
  }

  const renderDashboard = () => (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <div className="card">
        <h3 className="text-lg font-semibold mb-2 text-white">Volumen de Citas</h3>
        <p className="text-3xl font-bold gradient-text">{metricas?.volumen_citas || 0}</p>
      </div>
      <div className="card">
        <h3 className="text-lg font-semibold mb-2 text-white">Ingresos Totales</h3>
        <p className="text-3xl font-bold text-green-400">
          ${metricas?.ingresos_por_sede.reduce((sum, item) => sum + item.total, 0) || 0}
        </p>
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
          {metricas?.ingresos_por_sede.map((item, index) => (
            <div key={index} className="flex justify-between items-center text-gray-300">
              <span>{item.sede}</span>
              <span className="font-semibold text-white">${item.total}</span>
            </div>
          ))}
        </div>
      </div>

      <div className="col-span-1 md:col-span-2 card">
        <h3 className="text-lg font-semibold mb-4 text-white">Productos Más Vendidos</h3>
        <div className="space-y-2">
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

  const renderUsers = () => (
    <div className="card">
      <div className="flex justify-between items-center mb-6">
        <h3 className="text-xl font-bold text-white">Usuarios</h3>
        <button
          onClick={() => setShowUserForm(true)}
          className="btn-primary"
        >
          Nuevo Usuario
        </button>
      </div>
      <div className="overflow-x-auto">
        <table className="min-w-full">
          <thead>
            <tr className="border-b border-white/20">
              <th className="px-4 py-2 text-left text-gray-300">Nombre</th>
              <th className="px-4 py-2 text-left text-gray-300">Email</th>
              <th className="px-4 py-2 text-left text-gray-300">Rol</th>
              <th className="px-4 py-2 text-left text-gray-300">Sede</th>
            </tr>
          </thead>
          <tbody>
            {users.map(user => (
              <tr key={user.id} className="border-b border-white/10">
                <td className="px-4 py-2 text-white">{user.name}</td>
                <td className="px-4 py-2 text-gray-300">{user.email}</td>
                <td className="px-4 py-2 text-gray-300">{user.role}</td>
                <td className="px-4 py-2 text-gray-300">{user.sede_id || '-'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )

  const renderSedes = () => (
    <div className="card">
      <div className="flex justify-between items-center mb-6">
        <h3 className="text-xl font-bold text-white">Sedes</h3>
        <button
          onClick={() => setShowSedeForm(true)}
          className="btn-primary"
        >
          Nueva Sede
        </button>
      </div>
      <div className="grid gap-4">
        {sedes.map(sede => (
          <div key={sede.id} className="border border-white/20 rounded-lg p-4">
            <h4 className="font-semibold text-white">{sede.nombre}</h4>
            <p className="text-gray-300">{sede.direccion}</p>
            <p className="text-gray-300">{sede.telefono}</p>
          </div>
        ))}
      </div>
    </div>
  )

  const renderServicios = () => (
    <div className="card">
      <div className="flex justify-between items-center mb-6">
        <h3 className="text-xl font-bold text-white">Servicios</h3>
        <button
          onClick={() => setShowServicioForm(true)}
          className="btn-primary"
        >
          Nuevo Servicio
        </button>
      </div>
      <div className="grid gap-4">
        {servicios.map(servicio => (
          <div key={servicio.id} className="border border-white/20 rounded-lg p-4">
            <h4 className="font-semibold text-white">{servicio.nombre}</h4>
            <p className="text-gray-300">{servicio.descripcion}</p>
            <p className="text-gray-300">${servicio.precio} - {servicio.duracion_minutos} min</p>
          </div>
        ))}
      </div>
    </div>
  )

  const renderProductos = () => (
    <div className="card">
      <div className="flex justify-between items-center mb-6">
        <h3 className="text-xl font-bold text-white">Productos</h3>
        <button
          onClick={() => setShowProductoForm(true)}
          className="btn-primary"
        >
          Nuevo Producto
        </button>
      </div>
      <div className="grid gap-4">
        {productos.map(producto => (
          <div key={producto.id} className="border border-white/20 rounded-lg p-4">
            <h4 className="font-semibold text-white">{producto.nombre}</h4>
            <p className="text-gray-300">${producto.precio}</p>
            <p className="text-gray-300">Stock: {producto.stock_por_sede}</p>
          </div>
        ))}
      </div>
    </div>
  )

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
        <div className="flex gap-4 mb-6 flex-wrap">
          <button
            onClick={() => setActiveTab('dashboard')}
            className={`px-4 py-2 rounded-lg transition-all duration-300 ${activeTab === 'dashboard' ? 'btn-primary' : 'bg-white/10 text-gray-300 hover:bg-white/20'}`}
          >
            Dashboard
          </button>
          <button
            onClick={() => setActiveTab('users')}
            className={`px-4 py-2 rounded-lg transition-all duration-300 ${activeTab === 'users' ? 'btn-primary' : 'bg-white/10 text-gray-300 hover:bg-white/20'}`}
          >
            Usuarios
          </button>
          <button
            onClick={() => setActiveTab('sedes')}
            className={`px-4 py-2 rounded-lg transition-all duration-300 ${activeTab === 'sedes' ? 'btn-primary' : 'bg-white/10 text-gray-300 hover:bg-white/20'}`}
          >
            Sedes
          </button>
          <button
            onClick={() => setActiveTab('servicios')}
            className={`px-4 py-2 rounded-lg transition-all duration-300 ${activeTab === 'servicios' ? 'btn-primary' : 'bg-white/10 text-gray-300 hover:bg-white/20'}`}
          >
            Servicios
          </button>
          <button
            onClick={() => setActiveTab('productos')}
            className={`px-4 py-2 rounded-lg transition-all duration-300 ${activeTab === 'productos' ? 'btn-primary' : 'bg-white/10 text-gray-300 hover:bg-white/20'}`}
          >
            Productos
          </button>
        </div>

        {error && (
          <div className="bg-red-500/20 border border-red-500/50 text-red-200 px-4 py-3 rounded-lg mb-4 backdrop-blur-sm">
            {error}
          </div>
        )}

        {loading ? (
          <div className="text-center py-8 text-gray-300">Cargando...</div>
        ) : (
          <>
            {activeTab === 'dashboard' && renderDashboard()}
            {activeTab === 'users' && renderUsers()}
            {activeTab === 'sedes' && renderSedes()}
            {activeTab === 'servicios' && renderServicios()}
            {activeTab === 'productos' && renderProductos()}
          </>
        )}

        {/* User Form Modal */}
        {showUserForm && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center">
            <div className="card max-w-md w-full">
              <h3 className="text-xl font-bold text-white mb-4">Nuevo Usuario</h3>
              <div className="space-y-4">
                <input className="input-field" placeholder="Nombre" value={newUser.name} onChange={(e) => setNewUser({...newUser, name: e.target.value})} />
                <input className="input-field" placeholder="Email" type="email" value={newUser.email} onChange={(e) => setNewUser({...newUser, email: e.target.value})} />
                <input className="input-field" placeholder="Contraseña" type="password" value={newUser.password} onChange={(e) => setNewUser({...newUser, password: e.target.value})} />
                <input className="input-field" placeholder="Teléfono" value={newUser.phone} onChange={(e) => setNewUser({...newUser, phone: e.target.value})} />
                <select className="input-field" value={newUser.role} onChange={(e) => setNewUser({...newUser, role: e.target.value})}>
                  <option value="cliente">Cliente</option>
                  <option value="barbero">Barbero</option>
                  <option value="supervisor">Supervisor</option>
                  <option value="admin">Admin</option>
                </select>
                <input className="input-field" placeholder="ID Sede (opcional)" type="number" value={newUser.sede_id} onChange={(e) => setNewUser({...newUser, sede_id: e.target.value})} />
              </div>
              <div className="flex gap-4 mt-6">
                <button onClick={handleCreateUser} className="flex-1 btn-primary">Crear</button>
                <button onClick={() => setShowUserForm(false)} className="flex-1 btn-secondary">Cancelar</button>
              </div>
            </div>
          </div>
        )}

        {/* Sede Form Modal */}
        {showSedeForm && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center">
            <div className="card max-w-md w-full">
              <h3 className="text-xl font-bold text-white mb-4">Nueva Sede</h3>
              <div className="space-y-4">
                <input className="input-field" placeholder="Nombre" value={newSede.nombre} onChange={(e) => setNewSede({...newSede, nombre: e.target.value})} />
                <input className="input-field" placeholder="Dirección" value={newSede.direccion} onChange={(e) => setNewSede({...newSede, direccion: e.target.value})} />
                <input className="input-field" placeholder="Teléfono" value={newSede.telefono} onChange={(e) => setNewSede({...newSede, telefono: e.target.value})} />
              </div>
              <div className="flex gap-4 mt-6">
                <button onClick={handleCreateSede} className="flex-1 btn-primary">Crear</button>
                <button onClick={() => setShowSedeForm(false)} className="flex-1 btn-secondary">Cancelar</button>
              </div>
            </div>
          </div>
        )}

        {/* Servicio Form Modal */}
        {showServicioForm && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center">
            <div className="card max-w-md w-full">
              <h3 className="text-xl font-bold text-white mb-4">Nuevo Servicio</h3>
              <div className="space-y-4">
                <input className="input-field" placeholder="Nombre" value={newServicio.nombre} onChange={(e) => setNewServicio({...newServicio, nombre: e.target.value})} />
                <textarea className="input-field" placeholder="Descripción" value={newServicio.descripcion} onChange={(e) => setNewServicio({...newServicio, descripcion: e.target.value})} />
                <input className="input-field" placeholder="Precio" type="number" value={newServicio.precio} onChange={(e) => setNewServicio({...newServicio, precio: e.target.value})} />
                <input className="input-field" placeholder="Duración (minutos)" type="number" value={newServicio.duracion_minutos} onChange={(e) => setNewServicio({...newServicio, duracion_minutos: e.target.value})} />
              </div>
              <div className="flex gap-4 mt-6">
                <button onClick={handleCreateServicio} className="flex-1 btn-primary">Crear</button>
                <button onClick={() => setShowServicioForm(false)} className="flex-1 btn-secondary">Cancelar</button>
              </div>
            </div>
          </div>
        )}

        {/* Producto Form Modal */}
        {showProductoForm && (
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center">
            <div className="card max-w-md w-full">
              <h3 className="text-xl font-bold text-white mb-4">Nuevo Producto</h3>
              <div className="space-y-4">
                <input className="input-field" placeholder="Nombre" value={newProducto.nombre} onChange={(e) => setNewProducto({...newProducto, nombre: e.target.value})} />
                <textarea className="input-field" placeholder="Descripción" value={newProducto.descripcion} onChange={(e) => setNewProducto({...newProducto, descripcion: e.target.value})} />
                <input className="input-field" placeholder="Precio" type="number" value={newProducto.precio} onChange={(e) => setNewProducto({...newProducto, precio: e.target.value})} />
                <input className="input-field" placeholder="Stock por sede" type="number" value={newProducto.stock_por_sede} onChange={(e) => setNewProducto({...newProducto, stock_por_sede: e.target.value})} />
                <select className="input-field" value={newProducto.sede_id} onChange={(e) => setNewProducto({...newProducto, sede_id: e.target.value})}>
                  <option value="">Selecciona una sede</option>
                  {sedes.map(sede => (
                    <option key={sede.id} value={sede.id}>{sede.nombre}</option>
                  ))}
                </select>
              </div>
              <div className="flex gap-4 mt-6">
                <button onClick={handleCreateProducto} className="flex-1 btn-primary">Crear</button>
                <button onClick={() => setShowProductoForm(false)} className="flex-1 btn-secondary">Cancelar</button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

export default AdminDashboard
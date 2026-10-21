export interface Cita {
  id: number
  fecha: string
  hora_inicio: string
  hora_fin: string
  estado: string
  metodo_pago: string
  monto_servicios: number
  monto_productos: number
  monto_total: number
  notas?: string | null
  tiene_comprobante: boolean
  enlace_calendario?: string | null
  cliente: { id: number; name: string; phone?: string | null; email?: string }
  barbero: { id: number; name: string }
  sede: { id: number; nombre: string; direccion?: string }
  servicios: Array<{ id: number; nombre: string }>
  productos?: Array<{ id: number; nombre: string; pivot: { cantidad: number } }>
}

export interface Producto {
  id: number
  nombre: string
  descripcion?: string | null
  precio: number
  stock_por_sede: number
  sede_id?: number | null
  sede?: { id: number; nombre: string } | null
}

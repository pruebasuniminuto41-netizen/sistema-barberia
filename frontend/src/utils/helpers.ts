import api from '../services/api'

/** Mensaje legible de un error de la API (primer error de validación o el mensaje general). */
export const errorMessage = (err: any, fallback: string): string => {
  const data = err?.response?.data
  if (data?.errors) {
    const primero = Object.values(data.errors)[0] as string[] | undefined
    if (primero && primero[0]) return primero[0]
  }
  if (err?.response?.status === 401) return 'Tu sesión expiró. Vuelve a iniciar sesión'
  if (!err?.response) return 'No se pudo conectar con el servidor. Si acabas de abrir la página, espera unos segundos y reintenta'
  return data?.message || fallback
}

export const ESTADOS: Record<string, { label: string; clase: string }> = {
  pendiente: { label: 'Pendiente', clase: 'bg-yellow-500/20 text-yellow-300 border border-yellow-500/50' },
  pendiente_validacion: { label: 'Pago por validar', clase: 'bg-orange-500/20 text-orange-300 border border-orange-500/50' },
  confirmada: { label: 'Confirmada', clase: 'bg-green-500/20 text-green-300 border border-green-500/50' },
  en_proceso: { label: 'En proceso', clase: 'bg-blue-500/20 text-blue-300 border border-blue-500/50' },
  completada: { label: 'Completada', clase: 'bg-gray-500/20 text-gray-300 border border-gray-500/50' },
  cancelada: { label: 'Cancelada', clase: 'bg-red-500/20 text-red-300 border border-red-500/50' },
}

export const estadoInfo = (estado: string) =>
  ESTADOS[estado] || { label: estado, clase: 'bg-gray-500/20 text-gray-300 border border-gray-500/50' }

/** "2026-11-05" (o con hora) -> "jueves, 5 de noviembre de 2026" sin desfases de zona horaria. */
export const formatFecha = (fecha: string): string => {
  const [y, m, d] = fecha.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('es-CO', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

export const formatHora = (hora: string): string => (hora ? hora.slice(0, 5) : '')

export const money = (valor: number | string | null | undefined): string =>
  `$${Number(valor || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

/** Fecha de hoy en formato YYYY-MM-DD según el reloj del navegador. */
export const hoy = (): string => {
  const d = new Date()
  const mes = String(d.getMonth() + 1).padStart(2, '0')
  const dia = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${mes}-${dia}`
}

const descargar = async (ruta: string) => {
  const respuesta = await api.get(ruta, { responseType: 'blob' })
  return new Blob([respuesta.data], { type: String(respuesta.headers['content-type'] || '') })
}

/** Abre en otra pestaña un archivo protegido (el comprobante), usando la sesión actual. */
export const abrirArchivo = async (ruta: string) => {
  // La pestaña se abre al instante (dentro del clic) para que el navegador no la bloquee
  // mientras esperamos al servidor; luego se le carga el archivo.
  const ventana = window.open('', '_blank')
  try {
    const blob = await descargar(ruta)
    const url = URL.createObjectURL(blob)
    if (ventana) {
      ventana.location.href = url
    } else {
      window.location.href = url
    }
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (err) {
    ventana?.close()
    throw err
  }
}

/** Descarga el .ics de una cita para importarlo en cualquier calendario. */
export const descargarCalendario = async (citaId: number) => {
  const blob = await descargar(`/citas/${citaId}/calendario`)
  const url = URL.createObjectURL(blob)
  const enlace = document.createElement('a')
  enlace.href = url
  enlace.download = `cita-${citaId}.ics`
  document.body.appendChild(enlace)
  enlace.click()
  enlace.remove()
  URL.revokeObjectURL(url)
}

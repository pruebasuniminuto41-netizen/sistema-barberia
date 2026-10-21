import { useState } from 'react'

export interface Field {
  name: string
  label: string
  type: 'text' | 'email' | 'password' | 'number' | 'textarea' | 'select'
  required?: boolean
  options?: Array<{ value: string | number; label: string }>
  placeholder?: string
  help?: string
}

interface Props {
  title: string
  fields: Field[]
  initial: Record<string, any>
  submitLabel: string
  onSubmit: (values: Record<string, any>) => Promise<void>
  onClose: () => void
}

/** Formulario modal genérico para crear o editar un registro. */
const EntityModal = ({ title, fields, initial, submitLabel, onSubmit, onClose }: Props) => {
  const [values, setValues] = useState<Record<string, any>>(initial)
  const [saving, setSaving] = useState(false)

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setSaving(true)
    try {
      await onSubmit(values)
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
      <form onSubmit={submit} className="card max-w-md w-full max-h-screen overflow-y-auto">
        <h3 className="text-xl font-bold text-white mb-4">{title}</h3>
        <div className="space-y-4">
          {fields.map((f) => (
            <div key={f.name}>
              <label className="block text-gray-300 text-sm font-semibold mb-1">
                {f.label}
                {f.required && ' *'}
              </label>
              {f.type === 'textarea' ? (
                <textarea
                  className="input-field"
                  placeholder={f.placeholder}
                  required={f.required}
                  value={values[f.name] ?? ''}
                  onChange={(e) => setValues({ ...values, [f.name]: e.target.value })}
                />
              ) : f.type === 'select' ? (
                <select
                  className="input-field"
                  required={f.required}
                  value={values[f.name] ?? ''}
                  onChange={(e) => setValues({ ...values, [f.name]: e.target.value })}
                >
                  {!f.required && <option value="">— Ninguna —</option>}
                  {f.required && <option value="">Selecciona...</option>}
                  {f.options?.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>
              ) : (
                <input
                  className="input-field"
                  type={f.type}
                  placeholder={f.placeholder}
                  required={f.required}
                  min={f.type === 'number' ? 0 : undefined}
                  step={f.type === 'number' ? 'any' : undefined}
                  minLength={f.type === 'password' && f.required ? 8 : undefined}
                  value={values[f.name] ?? ''}
                  onChange={(e) => setValues({ ...values, [f.name]: e.target.value })}
                />
              )}
              {f.help && <p className="text-xs text-gray-400 mt-1">{f.help}</p>}
            </div>
          ))}
        </div>
        <div className="flex gap-4 mt-6">
          <button type="submit" disabled={saving} className="flex-1 btn-primary disabled:opacity-50">
            {saving ? 'Guardando...' : submitLabel}
          </button>
          <button type="button" onClick={onClose} className="flex-1 btn-secondary">
            Cancelar
          </button>
        </div>
      </form>
    </div>
  )
}

export default EntityModal

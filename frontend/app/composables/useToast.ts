export interface Toast { id: number; tone: 'success' | 'error' | 'info'; message: string }

let seq = 0

export function useToast() {
  const toasts = useState<Toast[]>('toasts', () => [])

  function push(message: string, tone: Toast['tone'] = 'success', ms = 4000) {
    const id = ++seq
    toasts.value = [...toasts.value, { id, tone, message }]
    if (import.meta.client) setTimeout(() => dismiss(id), ms)
  }

  function dismiss(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  return {
    toasts,
    dismiss,
    success: (m: string) => push(m, 'success'),
    error: (m: string) => push(m, 'error', 6000),
    info: (m: string) => push(m, 'info'),
  }
}

export interface ConfirmOptions {
  title: string
  message: string
  confirmLabel?: string
  tone?: 'danger' | 'primary'
  /** When set, the user must type a reason; it is returned instead of `true`. */
  requireReason?: boolean
}

interface ConfirmState extends ConfirmOptions { open: boolean; resolve?: (v: string | boolean) => void }

/** Promise-based confirmation dialog for sensitive actions. Rendered once by <UiConfirmHost>. */
export function useConfirm() {
  const state = useState<ConfirmState>('confirm', () => ({ open: false, title: '', message: '' }))

  function confirm(options: ConfirmOptions): Promise<string | boolean> {
    return new Promise((resolve) => {
      state.value = { ...options, open: true, resolve }
    })
  }

  function settle(value: string | boolean) {
    state.value.resolve?.(value)
    state.value = { ...state.value, open: false, resolve: undefined }
  }

  return { state, confirm, settle }
}

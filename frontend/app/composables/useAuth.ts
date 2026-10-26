import type { Item, User } from '~/types/api'

export function useAuth() {
  const api = useApi()
  const user = useState<User | null>('auth.user', () => null)
  const loaded = useState<boolean>('auth.loaded', () => false)

  async function fetchUser(force = false): Promise<User | null> {
    if (loaded.value && !force) return user.value
    try {
      const res = await api.get<Item<User>>('/auth/me')
      user.value = res.data
    } catch {
      user.value = null
    } finally {
      loaded.value = true
    }
    return user.value
  }

  async function login(email: string, password: string, remember = false) {
    const res = await api.post<Item<User>>('/auth/login', { email, password, remember })
    user.value = res.data
    loaded.value = true
    return res.data
  }

  async function register(payload: { name: string; email: string; password: string; password_confirmation: string; terms: boolean }) {
    const res = await api.post<Item<User>>('/auth/register', payload)
    user.value = res.data
    loaded.value = true
    return res.data
  }

  async function logout() {
    try { await api.post('/auth/logout') } finally {
      user.value = null
      await navigateTo('/login')
    }
  }

  const can = (permission: string) => !!user.value?.permissions.includes(permission)

  return { user, loaded, fetchUser, login, register, logout, can, isLoggedIn: computed(() => !!user.value) }
}

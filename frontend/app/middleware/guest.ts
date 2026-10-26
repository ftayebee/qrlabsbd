export default defineNuxtRouteMiddleware(async () => {
  const { fetchUser } = useAuth()
  const user = await fetchUser()
  if (user) return navigateTo(user.isAdmin ? '/admin' : '/dashboard')
})

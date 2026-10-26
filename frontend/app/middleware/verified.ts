export default defineNuxtRouteMiddleware(async () => {
  const { fetchUser } = useAuth()
  const user = await fetchUser()
  if (user && !user.emailVerified) return navigateTo('/verify-email')
})

// UI gate only; every admin endpoint is independently authorised by Laravel.
export default defineNuxtRouteMiddleware(async (to) => {
  const { fetchUser } = useAuth()
  const user = await fetchUser()
  if (!user) return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  if (!user.isAdmin) return navigateTo('/dashboard')
})

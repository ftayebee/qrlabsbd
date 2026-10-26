export default defineNuxtRouteMiddleware(async (to) => {
  const { fetchUser } = useAuth()
  const user = await fetchUser()
  if (!user) return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
})

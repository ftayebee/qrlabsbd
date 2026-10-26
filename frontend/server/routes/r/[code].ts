// Dynamic QR redirects are resolved by Laravel; Nginx normally routes /r/* directly.
export default defineEventHandler(event => proxyToApi(event))

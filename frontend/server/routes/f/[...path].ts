// Hosted file downloads (PDF QR codes) are streamed by Laravel.
export default defineEventHandler(event => proxyToApi(event))

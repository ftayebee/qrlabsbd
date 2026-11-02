# syntax=docker/dockerfile:1.7
FROM node:24-alpine AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --ignore-scripts
COPY frontend/ ./
# Public URLs are baked into canonical links and the sitemap at build time.
ARG NUXT_PUBLIC_SITE_URL
ARG NUXT_PUBLIC_SHORT_DOMAIN
ENV NUXT_PUBLIC_SITE_URL=${NUXT_PUBLIC_SITE_URL} NUXT_PUBLIC_SHORT_DOMAIN=${NUXT_PUBLIC_SHORT_DOMAIN}
RUN npx nuxi prepare && npm run build

FROM node:24-alpine AS runtime
ENV NODE_ENV=production HOST=0.0.0.0 PORT=3000
WORKDIR /app
COPY --from=build --chown=node:node /app/.output ./.output
USER node
EXPOSE 3000
# A static file: nginx waits on this check, and SSR pages call the API through nginx.
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
  CMD wget -qO- http://127.0.0.1:3000/favicon.svg >/dev/null || exit 1
CMD ["node", ".output/server/index.mjs"]

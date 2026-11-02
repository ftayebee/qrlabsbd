# Configuration is baked in so the stack can deploy from docker-compose.yml alone.
FROM nginx:1.27-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/fastcgi_laravel.conf /etc/nginx/fastcgi_laravel.conf

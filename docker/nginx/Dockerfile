# Nginx serves the SAME public/ tree that was baked into the PHP app image
# (including Vite hashed assets under public/build). Never bind-mount host
# ./public over this path — that was the root cause of post-deploy CSS drift.
ARG APP_IMAGE=aale-erp:latest
FROM ${APP_IMAGE} AS erp_app

FROM nginx:alpine

COPY --from=erp_app /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

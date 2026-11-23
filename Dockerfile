FROM dunglas/frankenphp:1-php8.4-alpine

WORKDIR /app
COPY . /app

EXPOSE 80
CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]

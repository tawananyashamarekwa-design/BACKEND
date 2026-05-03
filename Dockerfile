FROM php:8.2-cli

RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli pdo_pgsql pgsql

WORKDIR /var/www/html

COPY . .

EXPOSE 10000

CMD php -S 0.0.0.0:${PORT:-10000} public/index.php
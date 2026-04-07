FROM php:8.2-cli AS base

# Install MySQL PDO extension
RUN docker-php-ext-install pdo pdo_mysql

FROM base AS app
WORKDIR /app

# Force fresh copy every build - never cache this layer
ADD . /app/

# Set permissions for uploads directory
RUN mkdir -p /app/uploads && chmod 777 /app/uploads

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app", "/app/router.php"]

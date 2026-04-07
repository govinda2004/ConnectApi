FROM php:8.2-cli

# Install MySQL PDO extension
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /app

# Copy app files
COPY . /app/

# Set permissions
RUN mkdir -p /app/uploads && chmod 777 /app/uploads

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app", "/app/router.php"]

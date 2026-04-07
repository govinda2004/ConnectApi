FROM php:8.2-cli

# Install MySQL PDO extension
RUN docker-php-ext-install pdo pdo_mysql

# Copy app files
WORKDIR /app
COPY . /app/

# Set permissions for uploads directory
RUN mkdir -p /app/uploads && chmod 777 /app/uploads

EXPOSE 8080

CMD php -S 0.0.0.0:${PORT:-8080} -t /app

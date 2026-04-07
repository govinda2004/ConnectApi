FROM php:8.2-cli

# Install MySQL PDO extension
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /app

# This RUN changes every commit - forces cache invalidation for COPY
RUN echo "build-20260407-v2"

# Copy app files (will NOT be cached because previous RUN changed)
COPY . /app/

# Set permissions for uploads directory
RUN mkdir -p /app/uploads && chmod 777 /app/uploads

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app", "/app/router.php"]

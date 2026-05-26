FROM php:8.2-cli

# Install PDO extensions for MySQL + PostgreSQL
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql

# Increase PHP upload limits for video support
RUN echo "upload_max_filesize=50M" >> /usr/local/etc/php/php.ini-development && \
    echo "post_max_size=55M" >> /usr/local/etc/php/php.ini-development && \
    echo "memory_limit=128M" >> /usr/local/etc/php/php.ini-development && \
    cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

WORKDIR /app

# Copy app files
COPY . /app/

# Set permissions
RUN mkdir -p /app/uploads /app/uploads/posts /app/uploads/stories && chmod -R 777 /app/uploads

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app", "/app/router.php"]

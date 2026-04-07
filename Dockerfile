FROM php:8.2-apache

# Install MySQL PDO extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy app files
COPY . /var/www/html/

# Set permissions
RUN mkdir -p /var/www/html/uploads && chmod 777 /var/www/html/uploads

# Apache config for rewrite
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/rewrite.conf \
    && a2enconf rewrite

# Use PORT env variable from Railway
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 8080

CMD ["apache2-foreground"]

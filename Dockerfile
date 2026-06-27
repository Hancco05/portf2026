FROM php:8.2-apache

# Copia archivos
COPY . /var/www/html/

# Asigna permisos al directorio
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configura Apache para permitir acceso
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/docker-php.conf \
    && a2enconf docker-php

# Habilita módulo rewrite
RUN a2enmod rewrite

EXPOSE 80
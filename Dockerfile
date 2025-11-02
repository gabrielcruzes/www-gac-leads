FROM php:8.2-apache

# Instala extensoes necessarias
RUN docker-php-ext-install pdo_mysql && \
    a2enmod rewrite

WORKDIR /var/www/html

COPY .docker/000-default.conf /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html

# Ajusta permissoes de escrita para pastas de storage/export
RUN mkdir -p storage/exports && \
    chown -R www-data:www-data storage && \
    find storage -type d -exec chmod 775 {} \; && \
    find storage -type f -exec chmod 664 {} \;

EXPOSE 80

CMD ["apache2-foreground"]

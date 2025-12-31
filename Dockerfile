FROM php:8.2-apache

# Instalar extensiones PHP si las necesitas
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copiar archivos de la aplicación
COPY . /var/www/html/

# Configurar Apache para usar el puerto de Render
# Render usa la variable $PORT, así que necesitamos configurar Apache para usarla
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

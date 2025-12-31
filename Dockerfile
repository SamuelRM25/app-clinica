FROM php:8.2-apache

# Instalar extensiones PHP si las necesitas
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copiar archivos de la aplicación
COPY . /var/www/html/

# Habilitar módulo rewrite si usas .htaccess
RUN a2enmod rewrite

# Configurar Apache para usar el puerto de Render
RUN echo "Listen \${PORT}" > /etc/apache2/ports.conf
RUN echo '<VirtualHost *:\${PORT}>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Script para iniciar Apache con el puerto correcto
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

CMD ["docker-entrypoint.sh"]

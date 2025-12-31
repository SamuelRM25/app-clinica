FROM php:8.2-apache

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copiar archivos de la aplicación
COPY . /var/www/html/

# Habilitar módulo rewrite para .htaccess
RUN a2enmod rewrite

# Configurar Apache para usar el puerto de Render
# Crear archivo de configuración con el puerto dinámico
RUN echo "Listen 8080" > /etc/apache2/ports.conf && \
    echo '<VirtualHost *:8080>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Script de inicio que maneja el puerto dinámico
COPY start.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/start.sh

# Comando de inicio
CMD ["/usr/local/bin/start.sh"]

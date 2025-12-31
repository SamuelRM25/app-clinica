#!/bin/bash

# Configurar Apache para usar el puerto asignado por Render
PORT=${PORT:-8080}

# Reemplazar el puerto en la configuración
sed -i "s/Listen 8080/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/:8080>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Iniciar Apache
exec apache2-foreground

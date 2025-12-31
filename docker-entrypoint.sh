#!/bin/bash
# docker-entrypoint.sh

# Reemplazar $PORT en los archivos de configuración
sed -i "s/\${PORT}/$PORT/g" /etc/apache2/ports.conf
sed -i "s/\${PORT}/$PORT/g" /etc/apache2/sites-available/000-default.conf

# Iniciar Apache
exec apache2-foreground

#!/bin/bash
# Script para configurar Apache con el puerto dinámico de Render

# Reemplazar el puerto 80 por el puerto asignado por Render
sed -i "s/80/${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Ejecutar Apache
exec apache2-foreground

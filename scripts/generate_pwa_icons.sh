#!/bin/bash
# scripts/generate_pwa_icons.sh
# Genera iconos PWA desde el logo existente usando sips (macOS built-in)
# Equivalente a ImageMagick para este caso específico.

set -e

SOURCE="assets/img/cmrs.png"
OUTPUT_DIR="assets/img"

if [ ! -f "$SOURCE" ]; then
    echo "Error: no se encuentra $SOURCE"
    exit 1
fi

mkdir -p "$OUTPUT_DIR"

# Iconos estándar (8 tamaños)
for size in 72 96 128 144 152 192 384 512; do
    target="$OUTPUT_DIR/icon-${size}.png"
    sips -z $size $size "$SOURCE" --out "$target" > /dev/null 2>&1
    echo "✓ Generado icon-${size}.png"
done

# Iconos maskable (Android 12+ adaptativo)
# Para maskable: canvas 1.25x con icono centrado al 80%
# 192: canvas 240, icono 192 centrado
# 512: canvas 640, icono 512 centrado
# sips no tiene "extent" para maskable, pero podemos simular con padding transparente.
# Como sips no soporta alpha padding, generamos los maskable usando el mismo tamaño
# y dejamos que el sistema operativo recorte el área segura.
# Para Android 12+, el manifest "purpose": "maskable" indica al OS que use el área central.

cp "$OUTPUT_DIR/icon-192.png" "$OUTPUT_DIR/icon-192-maskable.png"
cp "$OUTPUT_DIR/icon-512.png" "$OUTPUT_DIR/icon-512-maskable.png"
echo "✓ Generados icon-192-maskable.png y icon-512-maskable.png"

echo ""
echo "Iconos PWA generados en $OUTPUT_DIR/:"
ls -la "$OUTPUT_DIR"/icon-*.png

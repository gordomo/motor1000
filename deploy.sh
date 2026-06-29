#!/usr/bin/env bash
# Deploy del sistema motor1000. Uso: bash deploy.sh
set -euo pipefail
cd "$(dirname "$0")"

DC="docker compose -f docker-compose.prod.yml"

echo "→ Trayendo últimos cambios (git pull)..."
git pull origin main

echo "→ Build de las imágenes PHP..."
$DC build app horizon scheduler

echo "→ Recreación de contenedores (clave: --force-recreate)..."
# --force-recreate evita quedarse con la build vieja.
$DC up -d --force-recreate app horizon scheduler nginx

echo "→ Migraciones (additivas; NUNCA migrate:fresh en prod)..."
$DC exec -T app php artisan migrate --force

echo "→ storage:link + limpiar cachés..."
$DC exec -T app php artisan storage:link || true
$DC exec -T app php artisan optimize:clear
$DC exec -T app php artisan filament:cache-components || true

echo "→ Estado:"
$DC ps

echo "✓ Sistema desplegado."

#!/usr/bin/env bash
# =============================================================================
# motor1000 - First-time Docker bootstrap script
# Run: bash docker/bootstrap.sh
# =============================================================================
set -e

DC="docker compose"

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " Motor1000 Workshop Platform — Docker Bootstrap"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 1. Copy env
if [ ! -f .env ]; then
  cp .env.example .env
  echo "✔ .env created from .env.example"
fi

# 2. Build images
echo "▶ Building Docker images (this may take a few minutes)..."
$DC build

# 3. Start services
echo "▶ Starting services..."
$DC up -d

# 4. Wait for MySQL
echo "▶ Waiting for MySQL to be ready..."
until $DC exec mysql mysqladmin ping -h localhost --silent; do
  sleep 2
done
echo "✔ MySQL ready"

# 5. Install PHP dependencies
echo "▶ Installing PHP dependencies via Composer..."
$DC exec app composer install --no-interaction --prefer-dist --optimize-autoloader

# 6. Generate app key
echo "▶ Generating application key..."
$DC exec app php artisan key:generate --force

# 7. Install NPM and build assets
echo "▶ Installing Node.js dependencies..."
$DC exec app npm install

echo "▶ Building frontend assets..."
$DC exec app npm run build

# 8. Run migrations
echo "▶ Running database migrations..."
$DC exec app php artisan migrate --force

# 9. Seed database
echo "▶ Seeding demo data..."
$DC exec app php artisan db:seed --force

# 10. Publish/link storage
echo "▶ Linking storage..."
$DC exec app php artisan storage:link

# 11. Filament assets
echo "▶ Publishing Filament assets..."
$DC exec app php artisan filament:assets

# 12. Cache config
echo "▶ Caching configuration..."
$DC exec app php artisan config:cache
$DC exec app php artisan route:cache
$DC exec app php artisan view:cache

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " ✅  Motor1000 is ready!"
echo ""
echo " 🌐  App:      http://localhost:8080"
echo " 🔐  Admin:    http://localhost:8080/admin"
echo " 📧  Mailpit:  http://localhost:8025"
echo " 🔑  Login:    admin@motor1000.local / password"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

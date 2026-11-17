#!/usr/bin/env bash
# --check: solo lectura. --deploy SHA: despliega ese HEAD exacto, sin git pull/reset.
set -Eeuo pipefail
umask 077
cd "$(dirname "$0")"
root=$(pwd -P)
mode=${1:---check}
[[ "$mode" == --check || "$mode" == --deploy ]] || { echo 'Uso: bash deploy.sh --check | --deploy SHA'; exit 2; }
dc=(docker compose -f docker-compose.prod.yml)
backup_root=${MOTOR1000_BACKUP_DIR:-/var/backups/motor1000}
mirror=${MOTOR1000_BACKUP_MIRROR:-}
for program in docker git gzip tar sha256sum flock curl; do
    command -v "$program" >/dev/null || { echo "Falta $program" >&2; exit 1; }
done
head=$(git rev-parse HEAD)
export MOTOR1000_IMAGE_TAG=${head:0:12}
export MOTOR1000_RELEASE_REVISION=$head
[[ -z "$(git status --porcelain --untracked-files=normal)" ]] || {
    echo 'Hay cambios locales. Conservarlos y revisarlos antes de desplegar; no se hará reset ni pull.' >&2; exit 1;
}
if [[ "$mode" == --deploy ]]; then
    [[ $# == 2 && "$(git rev-parse --verify "${2}^{commit}")" == "$head" ]] || {
        echo 'Indicar el SHA exacto revisado: bash deploy.sh --deploy SHA' >&2; exit 1;
    }
    if [[ -n "$mirror" ]]; then
        command -v rsync >/dev/null || { echo 'Falta rsync para la copia adicional.' >&2; exit 1; }
    fi
fi
check_tmp=$(mktemp -d)
backup=''
quiesced=0
cleanup() {
    result=$?
    rm -rf -- "$check_tmp"
    if [[ "$result" != 0 && "$quiesced" == 1 ]]; then
        echo 'Despliegue detenido. Se mantiene el mantenimiento; no se restauró ni borró la base.' >&2
        echo "Backup: $backup" >&2
        [[ ! -f "$backup/recover.sh" ]] || echo "Recuperar servicio anterior: bash '$backup/recover.sh'" >&2
    fi
}
trap cleanup EXIT
"${dc[@]}" config --format json > "$check_tmp/compose.json"
mapfile -t ids < <("${dc[@]}" ps -aq)
[[ ${#ids[@]} -gt 0 ]] || { echo 'No se encontró una instalación existente.' >&2; exit 1; }
docker inspect "${ids[@]}" > "$check_tmp/containers.json"
# El PHP del contenedor actual procesa JSON; no requiere PHP ni Python en el VPS.
app_id=$("${dc[@]}" ps -q app)
[[ -n "$app_id" ]] || { echo 'El contenedor app no está corriendo.' >&2; exit 1; }
php_tool=$(tail -n +2 scripts/deploy-tool.php)
run_tool() { docker exec -i "$app_id" php -r "$php_tool" "$@"; }
state_input() {
    printf '{"config":'
    cat "$check_tmp/compose.json"
    printf ',"containers":'
    cat "$check_tmp/containers.json"
    printf '}\n'
}
state_input | run_tool preflight
echo "Revisión preparada: $head"
if [[ "$mode" == --check ]]; then
    echo "Backup principal: $backup_root (verificación por restauración obligatoria)."
    [[ -n "$mirror" ]] || echo 'Modalidad autorizada: backup solo en este VPS, sin copia externa.'
    echo 'Comprobación terminada. No se modificaron servicios ni datos.'
    exit 0
fi
exec 9>.deploy.lock
flock -n 9 || { echo 'Ya hay otro despliegue en curso.' >&2; exit 1; }
mkdir -p "$backup_root"
backup_root=$(cd "$backup_root" && pwd -P)
[[ "$backup_root/" != "$root/"* ]] || { echo 'El backup debe quedar fuera del repositorio y directorio público.' >&2; exit 1; }
mysql_kb=$("${dc[@]}" exec -T mysql du -sk /var/lib/mysql | awk '{print $1}')
storage_kb=$("${dc[@]}" exec -T app du -sk storage | awk '{print $1}')
available_kb=$(df -Pk "$backup_root" | awk 'END {print $4}')
needed_kb=$((mysql_kb * 3 + storage_kb * 2 + 2097152))
[[ "$available_kb" -gt "$needed_kb" ]] || { echo 'Espacio insuficiente para backup y restauración de prueba.' >&2; exit 1; }
run_id="$(date -u +%Y%m%dT%H%M%SZ)-${head:0:12}-${RANDOM}"
backup="$backup_root/$run_id"
mkdir -m 700 "$backup"
cp .env "$backup/environment.env"
cp "$check_tmp/compose.json" "$backup/previous-compose.json"
cp "$check_tmp/containers.json" "$backup/previous-containers.json"
printf '%s\n' "$head" > "$backup/target-revision"
# Protege las imágenes viejas antes de reemplazar sus tags durante el build.
for artifact in compose images script plan; do
    case "$artifact" in
        script) destination="$backup/recover.sh" ;;
        plan) destination="$check_tmp/recovery-plan.tsv" ;;
        *) destination="$backup/recovery-$artifact.json" ;;
    esac
    state_input | run_tool "recovery-$artifact" "$backup" "$root" "$run_id" > "$destination"
done
chmod 700 "$backup/recover.sh"
while IFS=$'\t' read -r service container image tag; do
    docker image tag "$image" "$tag"
    case "$service" in
        app)
            mkdir "$backup/public"
            docker cp "$container:/var/www/public/." "$backup/public/"
            ;;
        nginx) docker cp "$container:/etc/nginx/conf.d/default.conf" "$backup/nginx.conf" ;;
    esac
done < "$check_tmp/recovery-plan.tsv"
echo 'Construyendo imágenes antes de interrumpir el servicio…'
"${dc[@]}" build app horizon scheduler
# Probar como el usuario de PHP-FPM antes de cerrar producción. Un checkout
# hecho con umask 077 no debe producir una imagen ilegible para www-data.
docker run --rm --network none --user www-data \
    -e APP_ENV=production -e LOG_CHANNEL=stderr \
    "local/motor1000-app:$MOTOR1000_IMAGE_TAG" \
    php artisan route:list --path=panel/login --json >/dev/null
"${dc[@]}" exec -T app php artisan down --retry=60
quiesced=1
"${dc[@]}" stop -t 300 nginx scheduler horizon
"${dc[@]}" exec -T app sh -c 'test -f storage/framework/maintenance.php || test -f storage/framework/down'
db() {
    "${dc[@]}" exec -T mysql sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql -uroot -N -B "$MYSQL_DATABASE" "$@"' sh "$@"
}
counts() {
    db -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME" > "$check_tmp/tables"
    while IFS= read -r table; do
        [[ "$table" =~ ^[A-Za-z0-9_]+$ ]] || { echo 'Nombre de tabla no admitido.' >&2; return 1; }
        printf 'SELECT '\''%s'\'', COUNT(*) FROM `%s`;\n' "$table" "$table"
    done < "$check_tmp/tables" > "$check_tmp/counts.sql"
    db < "$check_tmp/counts.sql"
}
counts > "$backup/counts-before.tsv"
"${dc[@]}" exec -T mysql sh -c \
    'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysqldump -uroot --single-transaction --quick --routines --triggers --events --hex-blob --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"' \
    | gzip > "$backup/database.sql.gz"
"${dc[@]}" exec -T app tar -czf - -C /var/www/storage . > "$backup/storage.tar.gz"
tar -tzf "$backup/storage.tar.gz" >/dev/null
redis_id=$("${dc[@]}" ps -q redis)
"${dc[@]}" exec -T redis redis-cli SAVE >/dev/null
docker cp "$redis_id:/data/dump.rdb" "$backup/redis.rdb"
mysql_image=$(docker inspect --format '{{.Image}}' "$("${dc[@]}" ps -q mysql)")
bash scripts/verify-backup.sh "$backup/database.sql.gz" "$backup/counts-before.tsv" "$mysql_image"
(cd "$backup" && sha256sum database.sql.gz storage.tar.gz redis.rdb environment.env previous-compose.json previous-containers.json recovery-images.json recovery-compose.json recover.sh nginx.conf > SHA256SUMS)
(cd "$backup" && sha256sum -c SHA256SUMS >/dev/null)
if [[ -n "$mirror" ]]; then
    echo 'Copiando y verificando el backup en el destino adicional…'
    mirror_release="${mirror%/}/$run_id/"
    rsync -a -s -- "$backup/" "$mirror_release"
    [[ -z "$(rsync -acni -s -- "$backup/" "$mirror_release")" ]] || { echo 'La copia adicional no coincide.' >&2; exit 1; }
else
    echo "Backup verificado en el VPS: $backup"
fi
"${dc[@]}" up -d --no-deps --no-build --pull never --force-recreate app
"${dc[@]}" exec -T app php artisan optimize:clear
"${dc[@]}" exec -T app php scripts/deploy-state.php > "$check_tmp/pending.json"
app_id=$("${dc[@]}" ps -q app)
migration=2026_11_13_100000_add_cancellation_to_work_orders
migration_hash=$(sha256sum "database/migrations/$migration.php" | awk '{print $1}')
{
    printf '{"pending":'
    cat "$check_tmp/pending.json"
    printf ',"allowed":{"%s":"%s"}}\n' "$migration" "$migration_hash"
} | run_tool validate-migrations
"${dc[@]}" exec -T app php artisan migrate --force --no-interaction
counts > "$backup/counts-after.tsv"
diff -u <(awk -F '\t' '$1 != "migrations"' "$backup/counts-before.tsv") \
    <(awk -F '\t' '$1 != "migrations"' "$backup/counts-after.tsv")
"${dc[@]}" exec -T app php artisan storage:link
"${dc[@]}" exec -T app chown -R www-data:www-data storage bootstrap/cache
# Nginx sirve los assets desde el checkout, PHP desde la imagen. Copiar solo
# el build generado, conservando los hashes anteriores para sesiones abiertas.
mkdir -p public/build
docker cp "$("${dc[@]}" ps -q app):/var/www/public/build/." public/build/
find public/build -type d ! -perm -005 -exec chmod a+rx {} +
find public/build -type f ! -perm -004 -exec chmod a+r {} +
"${dc[@]}" up -d --no-deps --no-build --pull never --force-recreate nginx
# Laravel exceptúa /up del mantenimiento. Verifica el recorrido nginx → PHP.
"${dc[@]}" exec -T app curl -fsS --retry 12 --retry-delay 2 --retry-connrefused http://nginx/up > /dev/null
"${dc[@]}" up -d --no-deps --no-build --pull never --force-recreate horizon scheduler
# A partir de la reapertura no se permite recuperación automática a código viejo.
touch "$backup/RELEASED"
"${dc[@]}" exec -T app php artisan up
quiesced=0
echo "Despliegue terminado: $head"
echo "Backup verificado: $backup"
"${dc[@]}" ps

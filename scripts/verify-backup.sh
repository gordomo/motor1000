#!/usr/bin/env bash
# Restaura en un MySQL temporal, sin red ni puertos de producción.
set -Eeuo pipefail
umask 077
[[ $# == 3 ]] || { echo 'Uso: verify-backup.sh dump.sql.gz counts.tsv mysql-image' >&2; exit 2; }
dump=$1 counts=$2 mysql_image=$3
[[ -s "$dump" && -s "$counts" ]] || { echo 'Backup o conteos vacíos.' >&2; exit 1; }
verification_container="motor1000-backup-check-$(date -u +%Y%m%d%H%M%S)-${RANDOM}"
verification_started=0
verification_tmp=$(mktemp -d)
cleanup() {
    if [[ "$verification_started" == 1 ]]; then
        # Exclusivamente el contenedor temporal y su volumen anónimo.
        docker rm -f -v "$verification_container" >/dev/null
    fi
    rm -rf -- "$verification_tmp"
}
trap cleanup EXIT
gzip -t "$dump"
docker run -d --name "$verification_container" --network none \
    --label motor1000.backup-verification=true \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=verify_backup \
    "$mysql_image" >/dev/null
verification_started=1
ready=0
for ((attempt=0; attempt<120; attempt++)); do
    if docker exec "$verification_container" mysql -uroot -N -B verify_backup -e 'SELECT 1' >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 1
done
[[ "$ready" == 1 ]] || { echo 'No arrancó el MySQL de verificación.' >&2; exit 1; }
gzip -dc "$dump" | docker exec -i "$verification_container" mysql -uroot --binary-mode=1 verify_backup
docker exec "$verification_container" mysql -uroot -N -B verify_backup \
    -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME" \
    > "$verification_tmp/tables"
while IFS= read -r table; do
    [[ "$table" =~ ^[A-Za-z0-9_]+$ ]] || { echo 'Nombre de tabla no admitido.' >&2; exit 1; }
    printf 'SELECT '\''%s'\'', COUNT(*) FROM `%s`;\n' "$table" "$table"
done < "$verification_tmp/tables" > "$verification_tmp/counts.sql"
docker exec -i "$verification_container" mysql -uroot -N -B verify_backup \
    < "$verification_tmp/counts.sql" > "$verification_tmp/restored-counts.tsv"
diff -u "$counts" "$verification_tmp/restored-counts.tsv"
echo 'Backup SQL restaurado y conteos de todas las tablas verificados.'

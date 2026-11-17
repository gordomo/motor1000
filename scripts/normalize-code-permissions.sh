#!/bin/sh
# Solo código y assets de la imagen. No cambiar .env, secretos ni volúmenes.
set -eu
cd /var/www
for source_path in app bootstrap config database lang public resources routes scripts; do
    [ -d "$source_path" ] || continue
    find "$source_path" -type d ! -perm -005 -exec chmod a+rx {} +
    find "$source_path" -type f ! -perm -004 -exec chmod a+r {} +
done
for source_file in artisan composer.json composer.lock package.json package-lock.json; do
    [ ! -f "$source_file" ] || chmod a+r "$source_file"
done

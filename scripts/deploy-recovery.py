#!/usr/bin/env python3
"""Conserva las imágenes previas y genera una recuperación sin restaurar datos."""
import json
import pathlib
import shlex
import subprocess
import sys

backup, root, run_id = pathlib.Path(sys.argv[1]), sys.argv[2], sys.argv[3]
config = json.loads((backup / 'previous-compose.json').read_text())
containers = json.loads((backup / 'previous-containers.json').read_text())
override = {'services': {}}
for container in containers:
    service = container['Config']['Labels']['com.docker.compose.service']
    if service not in ('app', 'nginx', 'horizon', 'scheduler'):
        continue
    tag = f'motor1000-rollback:{run_id}-{service}'
    subprocess.run(['docker', 'image', 'tag', container['Image'], tag], check=True)
    environment = dict(item.split('=', 1) for item in container['Config']['Env'] if '=' in item)
    override['services'][service] = {'image': tag, 'environment': environment}
    if service == 'app':
        (backup / 'public').mkdir()
        subprocess.run(['docker', 'cp', container['Id'] + ':/var/www/public/.', str(backup / 'public')], check=True)
    if service == 'nginx':
        subprocess.run(['docker', 'cp', container['Id'] + ':/etc/nginx/conf.d/default.conf', str(backup / 'nginx.conf')], check=True)
        override['services'][service]['volumes'] = [
            {'type': 'bind', 'source': str(backup / 'public'), 'target': '/var/www/public', 'read_only': True},
            {'type': 'bind', 'source': str(backup / 'nginx.conf'), 'target': '/etc/nginx/conf.d/default.conf', 'read_only': True},
        ]

# Compose vuelve a interpolar los archivos renderizados: escapar los $ literales.
def escape(value):
    if isinstance(value, str):
        return value.replace('$', '$$')
    if isinstance(value, list):
        return [escape(item) for item in value]
    if isinstance(value, dict):
        return {key: escape(item) for key, item in value.items()}
    return value

(backup / 'recovery-compose.json').write_text(json.dumps(escape(config)))
(backup / 'recovery-images.json').write_text(json.dumps(escape(override)))
q = shlex.quote
script = f'''#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
cd {q(root)}
backup={q(str(backup))}
[[ ! -f "$backup/RELEASED" ]] || {{ echo 'El sistema ya se reabrió. Revisar compatibilidad de datos antes de volver a código anterior.' >&2; exit 1; }}
exec 9>.deploy.lock
flock -n 9 || {{ echo 'Hay un despliegue en curso.' >&2; exit 1; }}
dc=(docker compose -p {q(config['name'])} --env-file "$backup/environment.env" -f "$backup/recovery-compose.json" -f "$backup/recovery-images.json")
"${{dc[@]}}" stop -t 300 nginx scheduler horizon
"${{dc[@]}}" up -d --no-deps --no-build --pull never --force-recreate app
"${{dc[@]}}" exec -T app php artisan optimize:clear
"${{dc[@]}}" exec -T app chown -R www-data:www-data storage bootstrap/cache
"${{dc[@]}}" up -d --no-deps --no-build --pull never --force-recreate nginx horizon scheduler
"${{dc[@]}}" exec -T app curl -fsS --retry 12 --retry-delay 2 --retry-connrefused http://nginx/up >/dev/null
"${{dc[@]}}" exec -T app php artisan up
echo 'Servicio anterior recuperado. No se restauró ni revirtió la base de datos.'
'''
(backup / 'recover.sh').write_text(script)
(backup / 'recover.sh').chmod(0o700)

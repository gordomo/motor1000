#!/usr/bin/env python3
"""Valida configuración y contenedores sin imprimir credenciales."""
import json
import sys

config = json.load(open(sys.argv[1]))
containers = json.load(open(sys.argv[2]))
services = {c['Config']['Labels']['com.docker.compose.service']: c for c in containers}
for service in ('app', 'nginx', 'mysql', 'redis', 'horizon', 'scheduler'):
    if service not in services or not services[service]['State']['Running']:
        sys.exit(f'El servicio {service} debe estar corriendo antes del despliegue.')
    if services[service]['Config']['Labels']['com.docker.compose.project'] != config['name']:
        sys.exit('El proyecto Compose no coincide con el que está en ejecución.')

def env(container):
    return dict(item.split('=', 1) for item in container['Config']['Env'] if '=' in item)

current = env(services['app'])
desired = config['services']['app']['environment']
if current.get('APP_ENV') != 'production' or desired.get('APP_ENV') != 'production':
    sys.exit('Este script solo despliega un entorno productivo ya existente.')
for key in ('APP_KEY', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'):
    if not current.get(key) or current[key] != desired.get(key):
        sys.exit(f'{key} difiere o está vacío. No se modificará producción.')
mysql = env(services['mysql'])
if mysql.get('MYSQL_DATABASE') != current['DB_DATABASE']:
    sys.exit('La base usada por la aplicación no coincide con la que se respaldaría.')
for service, target, volume in (
    ('app', '/var/www/storage', 'app_storage'),
    ('app', '/var/www/bootstrap/cache', 'app_cache'),
    ('mysql', '/var/lib/mysql', 'mysql_data'),
    ('redis', '/data', 'redis_data'),
):
    actual = next((m for m in services[service]['Mounts'] if m['Destination'] == target), {})
    expected = config.get('volumes', {}).get(volume, {}).get('name')
    if actual.get('Type') != 'volume' or not expected or actual.get('Name') != expected:
        sys.exit(f'El volumen {service}:{target} no coincide. No se recreará ningún servicio.')
print('Preflight correcto: entorno, clave, base y volúmenes existentes coinciden.')

<?php

// PHP estándar, sin Laravel ni dependencias externas. Se ejecuta en el app existente.
function deployFail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function deployEnvironment(array $container): array
{
    $environment = [];
    foreach ($container['Config']['Env'] ?? [] as $item) {
        if (str_contains($item, '=')) {
            [$key, $value] = explode('=', $item, 2);
            $environment[$key] = $value;
        }
    }
    return $environment;
}

function deployEscape(mixed $value): mixed
{
    if (is_string($value)) {
        return str_replace('$', '$$', $value);
    }
    if (is_array($value)) {
        return array_map('deployEscape', $value);
    }
    return $value;
}

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$operation = $argv[1] ?? '';
if ($operation === 'validate-migrations') {
    foreach ($input['pending'] as $name => $digest) {
        if (! isset($input['allowed'][$name]) || ! hash_equals($input['allowed'][$name], $digest)) {
            deployFail('Hay migraciones pendientes no autorizadas. Se mantiene el mantenimiento.');
        }
    }
    exit(0);
}
$config = $input['config'];
$services = [];
foreach ($input['containers'] as $container) {
    $services[$container['Config']['Labels']['com.docker.compose.service']] = $container;
}

if ($operation === 'preflight') {
    foreach (['app', 'nginx', 'mysql', 'redis', 'horizon', 'scheduler'] as $service) {
        if (empty($services[$service]['State']['Running'])) {
            deployFail("El servicio {$service} debe estar corriendo antes del despliegue.");
        }
        if ($services[$service]['Config']['Labels']['com.docker.compose.project'] !== $config['name']) {
            deployFail('El proyecto Compose no coincide con el que está en ejecución.');
        }
    }
    $current = deployEnvironment($services['app']);
    $desired = $config['services']['app']['environment'];
    if (($current['APP_ENV'] ?? '') !== 'production' || ($desired['APP_ENV'] ?? '') !== 'production') {
        deployFail('Este script solo despliega un entorno productivo ya existente.');
    }
    foreach (['APP_KEY', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
        if (empty($current[$key]) || $current[$key] !== ($desired[$key] ?? null)) {
            deployFail("{$key} difiere o está vacío. No se modificará producción.");
        }
    }
    if ((deployEnvironment($services['mysql'])['MYSQL_DATABASE'] ?? '') !== $current['DB_DATABASE']) {
        deployFail('La base usada por la aplicación no coincide con la que se respaldaría.');
    }
    foreach ([
        ['app', '/var/www/storage', 'app_storage'],
        ['app', '/var/www/bootstrap/cache', 'app_cache'],
        ['mysql', '/var/lib/mysql', 'mysql_data'],
        ['redis', '/data', 'redis_data'],
    ] as [$service, $target, $volume]) {
        $actual = [];
        foreach ($services[$service]['Mounts'] as $mount) {
            if ($mount['Destination'] === $target) {
                $actual = $mount;
            }
        }
        $expected = $config['volumes'][$volume]['name'] ?? null;
        if (($actual['Type'] ?? '') !== 'volume' || ! $expected || ($actual['Name'] ?? '') !== $expected) {
            deployFail("El volumen {$service}:{$target} no coincide. No se recreará ningún servicio.");
        }
    }
    echo 'Preflight correcto: entorno, clave, base y volúmenes existentes coinciden.', PHP_EOL;
    exit(0);
}

$backup = $argv[2] ?? '';
$root = $argv[3] ?? '';
$runId = $argv[4] ?? '';
$override = ['services' => []];
foreach (['app', 'nginx', 'horizon', 'scheduler'] as $service) {
    $container = $services[$service];
    $tag = "motor1000-rollback:{$runId}-{$service}";
    if ($operation === 'recovery-plan') {
        echo implode("\t", [$service, $container['Id'], $container['Image'], $tag]), PHP_EOL;
    }
    $override['services'][$service] = ['image' => $tag, 'environment' => deployEnvironment($container)];
}
$override['services']['nginx']['volumes'] = [
    ['type' => 'bind', 'source' => "{$backup}/public", 'target' => '/var/www/public', 'read_only' => true],
    ['type' => 'bind', 'source' => "{$backup}/nginx.conf", 'target' => '/etc/nginx/conf.d/default.conf', 'read_only' => true],
];
if ($operation === 'recovery-plan') {
    exit(0);
}
if ($operation === 'recovery-compose' || $operation === 'recovery-images') {
    echo json_encode(deployEscape($operation === 'recovery-compose' ? $config : $override), JSON_THROW_ON_ERROR), PHP_EOL;
    exit(0);
}
if ($operation !== 'recovery-script') {
    deployFail('Operación de despliegue desconocida.');
}
echo "#!/usr/bin/env bash\nset -Eeuo pipefail\numask 077\ncd " . escapeshellarg($root) . "\nbackup=" . escapeshellarg($backup) . "\n";
echo 'project=' . escapeshellarg($config['name']) . "\n";
echo <<<'BASH'
[[ ! -f "$backup/RELEASED" ]] || { echo 'El sistema ya se reabrió. Revisar compatibilidad de datos antes de volver a código anterior.' >&2; exit 1; }
exec 9>.deploy.lock
flock -n 9 || { echo 'Hay un despliegue en curso.' >&2; exit 1; }
dc=(docker compose -p "$project" --env-file "$backup/environment.env" -f "$backup/recovery-compose.json" -f "$backup/recovery-images.json")
"${dc[@]}" stop -t 300 nginx scheduler horizon
"${dc[@]}" up -d --no-deps --no-build --pull never --force-recreate app
"${dc[@]}" exec -T app php artisan optimize:clear
"${dc[@]}" exec -T app chown -R www-data:www-data storage bootstrap/cache
"${dc[@]}" up -d --no-deps --no-build --pull never --force-recreate nginx horizon scheduler
"${dc[@]}" exec -T app curl -fsS --retry 12 --retry-delay 2 --retry-connrefused http://nginx/up >/dev/null
"${dc[@]}" exec -T app php artisan up
echo 'Servicio anterior recuperado. No se restauró ni revirtió la base de datos.'
BASH;
echo PHP_EOL;

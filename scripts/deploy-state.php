<?php

// Solo lectura: migraciones pendientes y su huella exacta.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$ran = Illuminate\Support\Facades\DB::table('migrations')->pluck('migration')->all();
$pending = [];
foreach (glob(__DIR__.'/../database/migrations/*.php') as $file) {
    if (! in_array(basename($file, '.php'), $ran, true)) {
        $pending[basename($file, '.php')] = hash_file('sha256', $file);
    }
}
echo json_encode((object) $pending, JSON_THROW_ON_ERROR), PHP_EOL;

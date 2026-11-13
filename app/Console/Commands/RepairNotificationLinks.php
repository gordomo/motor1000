<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairNotificationLinks extends Command
{
    protected $signature = 'notifications:repair-links {--dry-run : Solo contar, sin modificar notificaciones}';

    protected $description = 'Corrige enlaces históricos a localhost usando APP_URL del entorno';

    public function handle(): int
    {
        $base = rtrim((string) config('app.url'), '/');
        $parts = parse_url($base);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            $this->error('APP_URL debe ser una URL http o https válida.');

            return self::FAILURE;
        }

        $changed = 0;
        DB::table('notifications')->orderBy('id')->chunkById(100, function ($rows) use ($base, &$changed): void {
            foreach ($rows as $row) {
                $data = json_decode($row->data, true);
                if (! is_array($data)) {
                    continue;
                }

                $updated = $data;
                array_walk_recursive($updated, function (&$value, $key) use ($base): void {
                    if ($key !== 'url' || ! is_string($value)) {
                        return;
                    }
                    $url = parse_url($value);
                    if (! is_array($url) || ! in_array($url['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)) {
                        return;
                    }
                    $value = $base . '/' . ltrim($url['path'] ?? '', '/')
                        . (isset($url['query']) ? '?' . $url['query'] : '')
                        . (isset($url['fragment']) ? '#' . $url['fragment'] : '');
                });

                if ($updated === $data) {
                    continue;
                }
                $changed++;
                if (! $this->option('dry-run')) {
                    DB::table('notifications')->where('id', $row->id)->update([
                        'data' => json_encode($updated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            }
        });

        $this->info($this->option('dry-run')
            ? "Notificaciones que se corregirían: {$changed}"
            : "Notificaciones corregidas: {$changed}");

        return self::SUCCESS;
    }
}

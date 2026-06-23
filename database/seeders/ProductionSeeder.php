<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Seeder seguro para producción: NO usa Faker ni crea datos de demostración.
 * Solo crea los roles del taller y un super-admin para gestionar la plataforma.
 *
 * Uso:  php artisan db:seed --class=ProductionSeeder --force
 *
 * Credenciales del super-admin (configurables por env, con defaults):
 *   SUPER_ADMIN_EMAIL     (default: morimartin@gmail.com)
 *   SUPER_ADMIN_PASSWORD  (si está vacío, se genera una y se imprime una sola vez)
 *   SUPER_ADMIN_NAME      (default: Super Admin)
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Roles del taller (necesarios para asignar permisos a los usuarios).
        foreach (['admin', 'manager', 'receptionist', 'mechanic'] as $role) {
            Role::findOrCreate($role);
        }

        $email = env('SUPER_ADMIN_EMAIL', 'morimartin@gmail.com');
        $name  = env('SUPER_ADMIN_NAME', 'Super Admin');

        $password = env('SUPER_ADMIN_PASSWORD');
        $generated = false;
        if (blank($password)) {
            $password = Str::password(16);
            $generated = true;
        }

        $user = User::withoutGlobalScopes()->updateOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'password'          => Hash::make($password),
                'is_active'         => true,
                'is_super_admin'    => true,
                'tenant_id'         => null,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Roles creados y super-admin listo.');
        $this->command->info("🛡️  Super Admin (/admin): {$user->email}");

        if ($generated) {
            $this->command->warn("🔑 Contraseña generada (guardala, no se vuelve a mostrar): {$password}");
        } else {
            $this->command->info('🔑 Contraseña tomada de SUPER_ADMIN_PASSWORD.');
        }
    }
}

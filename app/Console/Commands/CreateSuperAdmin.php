<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super-admin
                            {--name= : Nombre del super admin}
                            {--email= : Correo electrónico}
                            {--password= : Contraseña}';

    protected $description = 'Crea un usuario super administrador para el panel /admin';

    public function handle(): int
    {
        $name     = $this->option('name')     ?? $this->ask('Nombre');
        $email    = $this->option('email')    ?? $this->ask('Correo electrónico');
        $password = $this->option('password') ?? $this->secret('Contraseña');

        if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
            $this->error("Ya existe un usuario con el correo: {$email}");
            return self::FAILURE;
        }

        $user = User::withoutGlobalScopes()->create([
            'tenant_id'      => null,
            'name'           => $name,
            'email'          => $email,
            'password'       => Hash::make($password),
            'is_active'      => true,
            'is_super_admin' => true,
        ]);

        $this->info("Super admin creado: {$user->name} <{$user->email}>");
        $this->line("  → Accede en: /admin");

        return self::SUCCESS;
    }
}

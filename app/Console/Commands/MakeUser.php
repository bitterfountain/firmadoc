<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Alta/actualizacion de usuarios (registro cerrado: no hay registro publico).
 *   php artisan firmadoc:user juan@empresa.com --name="Juan" --password=secreto
 *   php artisan firmadoc:user juan@empresa.com   (genera contrasena aleatoria)
 */
class MakeUser extends Command
{
    protected $signature = 'firmadoc:user
                            {email : Email del usuario}
                            {--name= : Nombre (por defecto, la parte local del email)}
                            {--password= : Contrasena (si se omite, se genera una aleatoria)}';

    protected $description = 'Crea o actualiza un usuario de FirmaDoc';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $name = $this->option('name') ?: Str::of($email)->before('@')->ucfirst();
        $password = $this->option('password') ?: Str::password(14);

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );

        $this->info($user->wasRecentlyCreated ? "Usuario creado: {$email}" : "Usuario actualizado: {$email}");

        if (! $this->option('password')) {
            $this->line('Contrasena generada: ' . $password);
        }

        return self::SUCCESS;
    }
}

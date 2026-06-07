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
                            {--password= : Contrasena (si se omite, se genera una aleatoria)}
                            {--admin : Marca la cuenta como administrador (puede generar invitaciones)}';

    protected $description = 'Crea o actualiza un usuario de FirmaDoc';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $existing = User::where('email', $email)->first();

        // No sobrescribimos nombre/contraseña de un usuario existente salvo que se indiquen.
        $attrs = [];
        $generated = null;

        if ($this->option('name')) {
            $attrs['name'] = $this->option('name');
        } elseif (! $existing) {
            $attrs['name'] = (string) Str::of($email)->before('@')->ucfirst();
        }

        if ($this->option('password')) {
            $attrs['password'] = Hash::make($this->option('password'));
        } elseif (! $existing) {
            $generated = Str::password(14);
            $attrs['password'] = Hash::make($generated);
        }

        if ($this->option('admin')) {
            $attrs['is_admin'] = true;
        }

        $user = User::updateOrCreate(['email' => $email], $attrs);

        $this->info($user->wasRecentlyCreated ? "Usuario creado: {$email}" : "Usuario actualizado: {$email}");
        if ($this->option('admin')) {
            $this->line('Marcado como administrador.');
        }
        if ($generated !== null) {
            $this->line('Contrasena generada: ' . $generated);
        }

        return self::SUCCESS;
    }
}

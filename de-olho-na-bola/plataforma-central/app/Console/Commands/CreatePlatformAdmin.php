<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:create-admin';

    protected $description = 'Cria o primeiro proprietário e espaço de trabalho da plataforma.';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error('Já existe um usuário. Por segurança, este comando só cria a conta inicial.');

            return self::FAILURE;
        }

        $name = trim($this->ask('Nome do proprietário'));
        $email = strtolower(trim($this->ask('E-mail')));
        $password = $this->secret('Senha (mínimo de 12 caracteres)');
        $passwordConfirmation = $this->secret('Confirme a senha');

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Informe um nome e um e-mail válido.');

            return self::FAILURE;
        }

        if (! is_string($password) || strlen($password) < 12 || $password !== $passwordConfirmation) {
            $this->error('As senhas precisam coincidir e ter pelo menos 12 caracteres.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password): void {
            $organization = Organization::query()->create([
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            ]);

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $organization->users()->attach($user, [
                'role' => 'owner',
                'status' => 'active',
            ]);
        });

        $this->info('Proprietário criado. Acesse a plataforma e entre com o e-mail cadastrado.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email} {--name=مدیر سامانه}';

    protected $description = 'Create an administrator using an interactive hidden password prompt';

    public function handle(): int
    {
        $email = $this->argument('email');
        $validator = Validator::make(['email' => $email, 'name' => $this->option('name')], [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('An interactive terminal is required to enter the password securely.');

            return self::FAILURE;
        }
        $password = $this->secret('Password (at least 12 characters)');
        $validator = Validator::make(['password' => $password], ['password' => ['required', Password::min(12)]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        if ($password !== $this->secret('Confirm password')) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }
        $user = new User(['name' => $this->option('name'), 'email' => $email, 'password' => $password]);
        $user->is_admin = true;
        $user->save();
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}

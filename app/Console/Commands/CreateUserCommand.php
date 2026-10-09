<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates an account.
 *
 * There is no self-service signup, because the ledger is shared: anybody who
 * can sign in can see and edit every item.
 */
class CreateUserCommand extends Command
{
    protected $signature = 'stockly:user
                            {--name= : The person\'s name}
                            {--email= : Their email address}
                            {--password= : Their password (prompted for if omitted)}';

    protected $description = 'Create an account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password');

        if ($password === null) {
            // Asked twice, so a typo is caught before the account exists.
            $password = $this->secret('Password');
            $confirmation = $this->secret('Confirm password');
        } else {
            // Passed in non-interactively, so there is nothing to compare with.
            $confirmation = $password;
        }

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Created {$name} <{$email}>.");

        return self::SUCCESS;
    }
}

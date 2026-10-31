<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first system administrator from ADMIN_NAME / ADMIN_EMAIL /
 * ADMIN_PASSWORD. Without ADMIN_PASSWORD a random password is generated and
 * printed once. Running it again leaves an existing admin untouched.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@jobauko.test');

        if (User::where('email', $email)->exists()) {
            $this->command?->info("Admin account {$email} already exists; left unchanged.");

            return;
        }

        $password = env('ADMIN_PASSWORD') ?: Str::password(16);

        $admin = new User([
            'name' => env('ADMIN_NAME', 'JoBauko Administrator'),
            'email' => $email,
            'password' => $password,
        ]);
        $admin->account_type = AccountType::Admin;
        $admin->email_verified_at = now();
        $admin->save();

        $this->command?->info("Admin account created: {$email}");

        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn("Generated password (shown once): {$password}");
        }
    }
}

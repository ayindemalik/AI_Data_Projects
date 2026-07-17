<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Creates one Administrator account so there is a way to log in before
     * any other users exist.
     *
     * IMPORTANT: change this password immediately after your first login,
     * and never leave this account with a placeholder password in production.
     */
    public function run(): void
    {
        $adminRole = Role::where('slug', 'administrator')->first();

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('ChangeMe123!'),
                'role_id' => $adminRole?->id,
                'email_verified_at' => now(),
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Module 1 — first accounts so someone can log in after `php artisan migrate --seed`.
 * CHANGE THESE PASSWORDS before the shop uses the system for real.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'owner'],
            [
                'name' => 'Shop Owner',
                'password' => 'Owner@12345',
                'role' => User::ROLE_OWNER,
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => true, // the default password is public: pick your own on first login
            ],
        );

        User::updateOrCreate(
            ['username' => 'cashier'],
            [
                'name' => 'Demo Cashier',
                'password' => 'Cashier@12345',
                'role' => User::ROLE_CASHIER,
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => true,
            ],
        );
    }
}

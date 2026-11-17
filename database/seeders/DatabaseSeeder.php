<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,      // Module 1
            ProductSeeder::class,   // Module 2
            // Module 3 adds its Walk-In Customer (ID = 1) seeder here.
        ]);
    }
}

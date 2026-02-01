<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'donia.a5ra2019@gmail.com'],
            [
                'name' => 'المدير',
                'password' => bcrypt('123456789'),
                'phone' => '0501234567',
                'is_active' => true,
                'is_hidden' => true,
            ]
        );
        $admin->assignRole('مدير');
    }
}

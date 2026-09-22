<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Treasury;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create main treasury
        Treasury::firstOrCreate(
            ['name' => 'الخزينة الرئيسية'],
            [
                'balance' => 0,
                'notes' => 'الخزينة الرئيسية للمؤسسة',
            ]
        );

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'donia.a5ra2019@gmail.com'],
            [
                'name' => 'محمد طارق',
                'password' => bcrypt('123456789'),
                'phone' => '01099446903',
                'is_active' => true,
                'is_hidden' => true,
            ]
        );
        $admin->assignRole('مدير','محاسب', 'مندوب' ,'باحث اجتماعي');
    }
}

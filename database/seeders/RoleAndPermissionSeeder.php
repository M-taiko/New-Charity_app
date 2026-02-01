<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'manage_treasury',
            'create_custody',
            'approve_custody',
            'receive_custody',
            'spend_money',
            'create_social_case',
            'review_social_case',
            'view_reports',
            'manage_users',
            'manage_settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles
        $managerRole = Role::firstOrCreate(['name' => 'مدير', 'guard_name' => 'web']);
        $accountantRole = Role::firstOrCreate(['name' => 'محاسب', 'guard_name' => 'web']);
        $agentRole = Role::firstOrCreate(['name' => 'مندوب', 'guard_name' => 'web']);
        $researcherRole = Role::firstOrCreate(['name' => 'باحث اجتماعي', 'guard_name' => 'web']);

        // Assign permissions to manager
        $managerRole->syncPermissions([
            'manage_treasury',
            'create_custody',
            'approve_custody',
            'spend_money',
            'review_social_case',
            'view_reports',
            'manage_users',
            'manage_settings',
        ]);

        // Assign permissions to accountant
        $accountantRole->syncPermissions([
            'manage_treasury',
            'create_custody',
            'approve_custody',
            'spend_money',
            'review_social_case',
            'view_reports',
        ]);

        // Assign permissions to agent
        $agentRole->syncPermissions([
            'receive_custody',
            'spend_money',
        ]);

        // Assign permissions to researcher
        $researcherRole->syncPermissions([
            'create_social_case',
            'view_reports',
        ]);
    }
}

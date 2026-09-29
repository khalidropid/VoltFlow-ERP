<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'stations.view',
            'stations.manage',
            'customers.view',
            'customers.manage',
            'billing.view',
            'billing.manage',
            'collections.view',
            'collections.post',
            'collections.void',
            'generation.view',
            'generation.manage',
            'fuel.view',
            'fuel.manage',
            'inventory.view',
            'inventory.manage',
            'procurement.view',
            'procurement.manage',
            'maintenance.view',
            'maintenance.manage',
            'employees.view',
            'employees.manage',
            'attendance.manage',
            'payroll.view',
            'payroll.process',
            'payroll.post',
            'payroll.pay',
            'payroll.void',
            'accounting.view',
            'accounting.post',
            'banking.manage',
            'reports.view',
            'audit.view',
            'integration.view',
            'integration.manage',
            'access.manage',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $roles = [
            'admin' => $permissions,
            'station_manager' => [
                'dashboard.view', 'stations.view', 'customers.view', 'customers.manage',
                'billing.view', 'billing.manage', 'collections.view', 'collections.post', 'collections.void',
                'generation.view', 'generation.manage', 'fuel.view', 'fuel.manage',
                'inventory.view', 'inventory.manage', 'procurement.view', 'procurement.manage',
                'maintenance.view', 'maintenance.manage', 'employees.view', 'employees.manage',
                'attendance.manage', 'payroll.view', 'payroll.process', 'payroll.post', 'payroll.pay',
                'reports.view', 'audit.view',
            ],
            'accountant' => [
                'dashboard.view', 'billing.view', 'collections.view', 'accounting.view',
                'accounting.post', 'banking.manage', 'payroll.view', 'payroll.post', 'reports.view', 'audit.view',
            ],
            'collector' => [
                'dashboard.view', 'customers.view', 'billing.view', 'collections.view',
                'collections.post', 'collections.void',
            ],
            'hr_manager' => [
                'dashboard.view', 'employees.view', 'employees.manage', 'attendance.manage',
                'payroll.view', 'payroll.process', 'payroll.post', 'payroll.pay', 'payroll.void',
                'reports.view', 'audit.view',
            ],
            'storekeeper' => [
                'dashboard.view', 'inventory.view', 'inventory.manage',
                'procurement.view', 'procurement.manage', 'fuel.view', 'fuel.manage', 'reports.view',
            ],
            'maintenance_manager' => [
                'dashboard.view', 'generation.view', 'fuel.view', 'fuel.manage',
                'inventory.view', 'maintenance.view', 'maintenance.manage', 'reports.view',
            ],
        ];

        foreach ($roles as $name => $rolePermissions) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($rolePermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

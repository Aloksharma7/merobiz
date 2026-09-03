<?php

namespace App\Enums;

enum BusinessRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Employee = 'employee';

    /** @return array<int, string> */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => ['*'],
            self::Admin => [
                'business.view', 'business.update', 'dashboard.financial',
                'customers.manage', 'products.manage', 'sales.manage',
                'payments.manage', 'expenses.manage', 'expenses.approve',
                'team.manage', 'reports.view',
            ],
            self::Employee => [
                'business.view', 'dashboard.personal',
                'customers.manage', 'products.view',
                'sales.create', 'sales.view_own',
                'payments.view', 'payments.record_own',
            ],
        };
    }

    public function allows(string $permission): bool
    {
        $permissions = $this->permissions();

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

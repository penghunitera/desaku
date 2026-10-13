<?php

namespace App\Support;

use App\Models\User;

class AdminAccess
{
    private const PERMISSIONS = [
        'admin_desa' => ['*'],
        'operator' => ['residents.view', 'residents.manage', 'families.view', 'families.manage', 'products.manage', 'potentials.manage'],
        'operator_kependudukan' => ['residents.view', 'residents.manage', 'families.view', 'families.manage'],
        'petugas_pelayanan' => ['residents.view', 'services.manage'],
        'operator_pbb' => ['residents.view', 'tax.manage'],
        'operator_bumdes' => ['products.manage', 'potentials.manage'],
        'kader_tb' => ['residents.view', 'health.view', 'health.manage'],
        'petugas_puskesmas' => ['residents.view', 'health.view', 'health.manage'],
        'kepala_desa' => ['dashboard.view', 'residents.view', 'reports.view', 'services.approve', 'health.summary'],
        'auditor' => ['dashboard.view', 'residents.view', 'reports.view'],
    ];

    public static function allows(?User $user, string $permission): bool
    {
        if (! $user || ! $user->active) {
            return false;
        }

        $permissions = self::PERMISSIONS[$user->role] ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public static function canViewDptStatistics(?User $user): bool
    {
        return self::allows($user, 'residents.view')
            || self::allows($user, 'reports.view')
            || self::allows($user, 'potentials.manage');
    }
}

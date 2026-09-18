<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

/**
 * RBAC roles (TOR §4 Stakeholders & User Classes, §6.10 Roles & Permissions).
 * Phase 1 fully gates Super Admin + Inventory Officer; Marketing Admin and
 * Support Agent roles are created now (schema-ready per the Phase 2
 * scaffolding decision) even though most Phase-2 screens aren't built yet -
 * Support Agent already gets read-only order visibility in Filament.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Laravel-permission caches role/permission lookups; clear it first
        // so a re-run of this seeder (e.g. db:seed --class=...) doesn't see
        // stale data from a previous database.
        Cache::forget(config('permission.cache.key', 'spatie.permission.cache'));

        foreach (config('cymarket.roles') as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}

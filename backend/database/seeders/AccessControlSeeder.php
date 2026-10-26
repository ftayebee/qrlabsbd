<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string}> slug => [name, group] */
    public const PERMISSIONS = [
        'dashboard.view' => ['View admin overview', 'general'],
        'customers.view' => ['View customers', 'customers'],
        'customers.manage' => ['Suspend customers and set quotas', 'customers'],
        'subscriptions.view' => ['View subscriptions', 'billing'],
        'subscriptions.manage' => ['Cancel, grant and extend subscriptions', 'billing'],
        'plans.manage' => ['Manage plans and pricing', 'billing'],
        'coupons.manage' => ['Manage coupons', 'billing'],
        'payments.view' => ['View payments, invoices and webhooks', 'billing'],
        'payments.manage' => ['Issue refunds and retry webhooks', 'billing'],
        'moderation.manage' => ['Moderate QR codes and abuse reports', 'trust'],
        'content.manage' => ['Manage FAQ, pages and email templates', 'content'],
        'settings.manage' => ['Manage global settings', 'system'],
        'roles.manage' => ['Manage roles and permissions', 'system'],
        'audit.view' => ['View audit logs', 'system'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $slug => [$name, $group]) {
            Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
        }

        $roles = [
            'super-admin' => ['Super admin', 'Full access to everything.', true, array_keys(self::PERMISSIONS)],
            'admin' => ['Admin', 'Day-to-day administration without role management.', true, array_values(array_diff(array_keys(self::PERMISSIONS), ['roles.manage']))],
            'support' => ['Support', 'Customer support and moderation.', true, ['dashboard.view', 'customers.view', 'subscriptions.view', 'payments.view', 'moderation.manage', 'content.manage']],
            'billing' => ['Billing', 'Billing operations.', true, ['dashboard.view', 'customers.view', 'subscriptions.view', 'subscriptions.manage', 'plans.manage', 'coupons.manage', 'payments.view', 'payments.manage']],
            'customer' => ['Customer', 'Default role for customers.', false, []],
        ];

        foreach ($roles as $slug => [$name, $description, $isAdmin, $permissions]) {
            $role = Role::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'is_admin' => $isAdmin]);
            $role->permissions()->sync(Permission::query()->whereIn('slug', $permissions)->pluck('id'));
        }
    }
}

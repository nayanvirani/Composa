<?php

namespace App\Support;

/** Super admin roles and what each may do (seeded into spatie/laravel-permission). */
final class AdminRoles
{
    public const PERMISSIONS = [
        'view_shops' => 'View shops & usage',
        'impersonate_read' => 'Impersonate (read-only)',
        'impersonate_write' => 'Impersonate (write)',
        'manage_shops' => 'Edit shops, limit overrides & notes',
        'edit_cms' => 'Edit website & app content',
        'publish_cms' => 'Publish website & templates',
        'manage_plans' => 'Plans, limits & pricing page',
        'manage_flags' => 'Feature flags',
        'view_monitoring' => 'Monitoring',
        'manage_support' => 'Support inbox',
        'gdpr_actions' => 'GDPR actions',
        'view_finance' => 'Revenue & subscriptions',
        'manage_team' => 'Team & roles',
        'view_audit' => 'Audit log',
    ];

    /** @return array<string,list<string>> */
    public static function roles(): array
    {
        return [
            'Owner' => array_keys(self::PERMISSIONS),
            'Support' => ['view_shops', 'impersonate_read', 'manage_shops', 'edit_cms', 'view_monitoring', 'manage_support', 'gdpr_actions', 'view_audit'],
            'Developer' => ['view_shops', 'impersonate_read', 'publish_cms', 'manage_flags', 'view_monitoring', 'view_audit'],
            'Finance' => ['view_shops', 'manage_plans', 'view_finance', 'view_audit'],
        ];
    }
}

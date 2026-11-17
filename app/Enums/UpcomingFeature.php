<?php

namespace App\Enums;

/**
 * Sidebar screens that are not built yet. Each value is its URL path; the
 * page shows what is coming. Remove a case when its real routes ship.
 */
enum UpcomingFeature: string
{
    case AuditLog = 'audit-log';

    public function title(): string
    {
        return match ($this) {
            self::AuditLog => __('Audit log'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AuditLog => __('A read-only history of every financial and access change.'),
        };
    }

    /**
     * What the finished screen will let people do.
     *
     * @return list<string>
     */
    public function highlights(): array
    {
        return match ($this) {
            self::AuditLog => [
                __('Before and after values for every financial change'),
                __('Who made each change, when and from which IP address'),
                __('Filter by person, action and date'),
            ],
        };
    }

    /**
     * The permissions that show this screen; holding any one is enough.
     * Mirrors the sidebar in resources/js/lib/navigation.ts.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::AuditLog => [Permission::AuditView],
        };
    }
}

import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

/**
 * Checks the shared `auth.permissions` list. This only mirrors access in the
 * UI (hiding a nav item or button); every route is still guarded by a Policy.
 */
export function useCan(): (...permissions: string[]) => boolean {
    const { permissions } = usePage().props.auth;

    return useCallback(
        (...required: string[]) =>
            required.length === 0 ||
            required.some((permission) => permissions.includes(permission)),
        [permissions],
    );
}

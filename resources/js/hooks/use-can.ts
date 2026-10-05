import { usePage } from '@inertiajs/react';

/**
 * Check the signed-in user's permissions (shared by HandleInertiaRequests).
 * Display only: the server enforces every permission on its routes.
 */
export function useCan() {
    const { permissions } = usePage().props.auth;

    return (permission: string) => permissions.includes(permission);
}

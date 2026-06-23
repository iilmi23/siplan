import type { PageProps as InertiaPageProps } from '@inertiajs/core';
import '@inertiajs/core';

export type UserRole = 'admin' | 'ppc' | string;

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role?: UserRole;
    permissions?: string[];
}

export interface SharedPageProps extends InertiaPageProps {
    [key: string]: any;
    auth?: {
        user?: AuthUser | null;
    };
    csrf_token?: string;
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        errorValueType: string;
        flashDataType: SharedPageProps['flash'];
        sharedPageProps: SharedPageProps;
    }
}

export type RouteParams =
    | string
    | number
    | Array<string | number>
    | Record<string, string | number | undefined>;

export interface RouteHelper {
    (): RouteHelper;
    (name: string, params?: RouteParams, absolute?: boolean): string;
    current: (name?: string) => boolean;
}

declare global {
    const route: RouteHelper;

    interface Window {
        route: RouteHelper;
    }
}

export {};

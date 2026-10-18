import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    /** Shown in the mobile top bar; defaults to the last breadcrumb. */
    title?: string;
};

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type GuestLayoutProps = {
    children?: ReactNode;
    title?: string;
    description?: string;
    /** 640px card for the member sign-up form instead of 440px. */
    wide?: boolean;
};

/** A header or sticky-bar action: a link (`href`) or a button. */
export type PageAction = {
    label: string;
    icon?: LucideIcon;
    href?: NonNullable<InertiaLinkProps['href']>;
    onClick?: () => void;
    /** Submits the form with this id, from anywhere on the page. */
    form?: string;
    disabled?: boolean;
    destructive?: boolean;
};

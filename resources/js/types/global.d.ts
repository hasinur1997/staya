import type { Auth } from '@/types/auth';
import type { FundProps, MessProps, NavProps } from '@/types/navigation';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            locale: string;
            locales: Record<string, string>;
            translations: Record<string, string>;
            auth: Auth;
            nav: NavProps;
            mess: MessProps;
            fund: FundProps;
            [key: string]: unknown;
        };
    }
}

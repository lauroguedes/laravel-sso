import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: Auth;
            sidebarOpen: boolean;
            sso: { discoveryUrl: string };
            branding: {
                name: string;
                logo: string | null;
                sidebarVariant: 'sidebar' | 'floating' | 'inset';
                authLayout: 'simple' | 'card' | 'split';
                authBackground: string | null;
                documentationLinks: { label: string; url: string }[];
            };
            can: {
                viewApplications: boolean;
                viewUsers: boolean;
                viewAudit: boolean;
                manageSettings: boolean;
            };
            [key: string]: unknown;
        };
    }
}

/*
 * DataTable carries a column's alignment on the column itself, so a header and
 * its cells read it from one place. TanStack leaves ColumnMeta empty for
 * exactly this.
 */
declare module '@tanstack/vue-table' {
    interface ColumnMeta<TData extends RowData, TValue> {
        align?: 'left' | 'right';
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

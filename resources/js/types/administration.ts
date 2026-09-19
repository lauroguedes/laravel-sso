export type DataTableColumn = {
    /** The cell slot name, and the field the server orders by when sorting. */
    id: string;
    header: string;
    sortable?: boolean;
    align?: 'left' | 'right';
    /** Kept out of the column menu when a row would be unreadable without it. */
    alwaysVisible?: boolean;
};

export type DataTableSort = {
    column: string;
    direction: 'asc' | 'desc';
} | null;

/**
 * Shapes returned by the administration controllers.
 *
 * These mirror the arrays built in UserController::summarize() and
 * ApplicationController::summarize()/detail(); a client secret never appears
 * in any of them.
 */

export type UserSummary = {
    id: number;
    name: string;
    email: string;
    email_verified: boolean;
    disabled: boolean;
    last_login_at: string | null;
    created_at: string | null;
    roles: string[];
};

/** One entry in an application's section rail, as the server allows it. */
export type ApplicationSection = {
    key: string;
    title: string;
};

/** Mirrors Application::toHeader(): what every page of one application shows. */
export type ApplicationHeader = {
    id: string;
    name: string;
    description: string | null;
    enabled: boolean;
    type: 'confidential' | 'public' | 'machine';
    type_label: string;
    type_description: string;
};

export type ApplicationSummary = ApplicationHeader & {
    created_at: string | null;
};

/**
 * One row of the applications listing.
 *
 * Carries its own answer to "may this reader edit it", because a steward may
 * edit the applications assigned to them and no others — which no page-level
 * answer can express.
 */
export type ApplicationListRow = ApplicationSummary & {
    can_manage: boolean;
};

export type ApplicationDetail = ApplicationSummary & {
    confidential: boolean;
    uses_redirect_uris: boolean;
    redirect_uris: string[];
    post_logout_redirect_uris: string[];
    scopes: string[];
    skips_authorization: boolean;
    restricts_access: boolean;
};

export type ApplicationTypeOption = {
    value: 'confidential' | 'public' | 'machine';
    label: string;
    description: string;
    uses_redirect_uris: boolean;
    default_scopes: string[];
};

export type ScopeOption = {
    id: string;
    description: string;
};

export type ApplicationRoleSummary = {
    id: number;
    name: string;
    description: string | null;
    permissions: { id: number; name: string }[];
    users_count: number;
};

export type ApplicationPermissionSummary = {
    id: number;
    name: string;
    description: string | null;
};

export type ApplicationGrantSummary = {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
        disabled: boolean;
    };
    role_id: number | null;
};

export type ApplicationRoleOption = {
    id: number;
    name: string;
};

export type UserCandidate = {
    id: number;
    name: string;
    email: string;
};

export type ApplicationAccessSummary = {
    application_id: string;
    application_name: string;
    application_enabled: boolean;
    role_name: string | null;
};

export type AuditEntry = {
    id: number;
    event: string | null;
    label: string;
    stream: string;
    causer: { name: string; email: string } | null;
    application: string | null;
    ip_address: string | null;
    created_at: string | null;
};

/**
 * One page of a listing, as Laravel's length-aware paginator serializes it.
 * Every listing here is counted, so every one can offer its pages by number.
 */
export type Paginator<TRow> = {
    data: TRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

export type BrowserSession = {
    id: string;
    user: { id: number; name: string; email: string };
    ip_address: string | null;
    user_agent: string | null;
    last_activity: number;
    current: boolean;
};

export type IssuedToken = {
    id: string;
    user: { id: number | null; name: string | null; email: string | null };
    application: string | null;
    scopes: string[];
    expires_at: string | null;
};

export type DashboardCounts = {
    applications: number;
    users: number;
    disabled_users: number;
    sessions: number;
    tokens: number;
};

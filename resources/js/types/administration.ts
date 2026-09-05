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

export type ApplicationSummary = {
    id: string;
    name: string;
    description: string | null;
    type: 'confidential' | 'public' | 'machine';
    type_label: string;
    enabled: boolean;
    created_at: string | null;
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

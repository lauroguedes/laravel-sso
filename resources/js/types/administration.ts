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
    scopes: string[];
    skips_authorization: boolean;
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

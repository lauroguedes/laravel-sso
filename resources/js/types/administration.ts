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

export type UserDetail = UserSummary & {
    updated_at: string | null;
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
    redirect_uris: string[];
    scopes: string[];
    grant_types: string[];
    skips_authorization: boolean;
    updated_at: string | null;
};

export type ApplicationTypeOption = {
    value: 'confidential' | 'public' | 'machine';
    label: string;
    description: string;
    confidential: boolean;
    uses_redirect_uris: boolean;
};

export type ScopeOption = {
    id: string;
    description: string;
};

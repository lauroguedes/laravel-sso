/**
 * What the consent page says, as the server words it for the application
 * asking.
 */
export type ConsentWording = {
    heading: string;
    message: string;
    switchAccountUrl: string | null;
    privacyUrl: string | null;
    termsUrl: string | null;
};

/**
 * Put an application's name wherever the administrator wrote {application},
 * as the server does, so the preview can follow the typing.
 */
export function nameApplication(text: string, applicationName: string): string {
    return text.replaceAll('{application}', applicationName);
}

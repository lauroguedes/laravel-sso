<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\Application;
use App\Models\AuditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes the audit trail.
 *
 * Every record goes through here rather than calling activity() directly, for
 * two reasons: the request context — who, from where, with what client — is
 * attached in one place, and so is the rule that sensitive values never reach
 * the log.
 *
 * Callers are listeners on domain events, so the trail records what happened
 * rather than what a particular controller remembered to write down.
 */
class AuditLogger
{
    /**
     * Keys that must never be written to the audit trail, at any depth.
     *
     * An audit record is long-lived, widely readable and frequently exported,
     * which makes it exactly the wrong place for a credential. Redacting here
     * means a careless caller cannot leak one.
     *
     * Written without separators and compared the same way, so that
     * "client_secret", "clientSecret" and "ClientSecret" all match.
     */
    private const REDACTED_KEYS = [
        'password',
        'passwordconfirmation',
        'secret',
        'clientsecret',
        'plainsecret',
        'token',
        'accesstoken',
        'refreshtoken',
        'idtoken',
        'code',
        'codeverifier',
        'twofactorsecret',
        'twofactorrecoverycodes',
        'remembertoken',
    ];

    public function __construct(private readonly Request $request) {}

    /**
     * Record that something happened.
     *
     * Returns null when the activity log is switched off, which is what
     * ACTIVITYLOG_ENABLED does: an operator can silence the trail, and nothing
     * that writes to it should break when they do.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(
        AuditEvent $event,
        ?Model $subject = null,
        ?Application $application = null,
        array $properties = [],
        ?Model $causer = null,
    ): ?AuditRecord {
        $activity = activity($event->log())
            ->event($event->value)
            ->withProperties($this->redact($properties));

        if ($subject !== null) {
            $activity->performedOn($subject);
        }

        /*
         * Left to the package when not given: it resolves the signed-in user
         * through the guard named by "activitylog.default_auth_driver". An
         * event nobody caused — a failed sign-in — has no signed-in user, so
         * the record correctly has none either.
         */
        if ($causer !== null) {
            $activity->causedBy($causer);
        }

        /*
         * tap() hands over the unsaved record, so the columns this project
         * adds are part of the same insert. Setting them after log() would
         * cost a second write, and would be dropped entirely under the
         * package's bulk-insert buffer.
         */
        $activity->tap(fn (AuditRecord $record) => $record->forceFill([
            'application_id' => $application?->id,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 1000),
        ]));

        /*
         * The description is the stable event identifier rather than the
         * label. A record is never rewritten, so freezing an English UI string
         * into one would leave history reading in whatever words were current
         * the day it happened; the interface derives the label from the event.
         */
        $record = $activity->log($event->value);

        return $record instanceof AuditRecord ? $record : null;
    }

    /**
     * Replace the value of any sensitive key with a marker.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function redact(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (is_array($value)) {
                $properties[$key] = $this->redact($value);

                continue;
            }

            if (in_array($this->normalize((string) $key), self::REDACTED_KEYS, true)) {
                $properties[$key] = '[redacted]';
            }
        }

        return $properties;
    }

    /**
     * Reduce a key to the form the redaction list is written in.
     */
    private function normalize(string $key): string
    {
        return str_replace(['_', '-', ' '], '', strtolower($key));
    }
}

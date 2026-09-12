<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity;

/**
 * One entry in the audit trail.
 *
 * Extends the activity log's own model — pointed at by
 * "config/activitylog.php" — rather than replacing it, so the package's
 * causer and subject relations, query scopes and cleanup command keep working
 * while this adds the columns an Identity Provider needs.
 *
 * Records are written, never edited. Nothing in this application updates or
 * deletes one; retention is the "activitylog:clean" command's job.
 *
 * @property string|null $application_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class AuditRecord extends Activity
{
    /**
     * The application an entry concerns, when it concerns one.
     *
     * Not a foreign key, and deliberately so: history has to survive the
     * application it describes.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * The event as this application understands it.
     *
     * Returns null for a record written by something other than AuditLogger,
     * so an unknown value never breaks a listing.
     */
    public function auditEvent(): ?AuditEvent
    {
        return $this->event === null ? null : AuditEvent::tryFrom($this->event);
    }

    /**
     * The person who caused this entry, when it was a user of this server.
     *
     * The relation is polymorphic, so the type has to be established before
     * the name can be read. Both listings ask for it the same way.
     *
     * @return array{name: string, email: string}|null
     */
    public function causerSummary(): ?array
    {
        $causer = $this->causer;

        return $causer instanceof User
            ? ['name' => $causer->name, 'email' => $causer->email]
            : null;
    }

    /**
     * The shape this entry takes in the administration interface.
     *
     * Lives here rather than in each controller so that the fallback for an
     * event this application does not recognise — a record written by
     * something other than AuditLogger — is decided once.
     *
     * Deliberately excludes the stored properties: they are metadata for
     * investigation, not for a listing, and shipping them to every browser
     * would undo the point of redacting them on the way in.
     *
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'label' => $this->auditEvent()?->label() ?? $this->description,
            'stream' => $this->log_name,
            'causer' => $this->causerSummary(),
            'application' => $this->application?->name,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * The columns a listing of audit entries may be ordered by.
     *
     * Only time and the event name. Ordering by actor or address would turn
     * "what happened" into "what exists", which is a different question and
     * not the one a trail answers.
     *
     * @return array<int, string>
     */
    public static function sortableColumns(): array
    {
        return ['created_at', 'event'];
    }

    /**
     * Scope the query to entries concerning one application.
     *
     * @param  Builder<AuditRecord>  $query
     * @return Builder<AuditRecord>
     */
    public function scopeForApplication(Builder $query, Application $application): Builder
    {
        return $query->where('application_id', $application->id);
    }

    /**
     * Scope the query to entries matching a search term.
     *
     * @param  Builder<AuditRecord>  $query
     * @return Builder<AuditRecord>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || $term === '') {
            return $query;
        }

        /*
         * An address is matched exactly, which the index on "ip_address" serves.
         * Anything else matches an event by prefix or the description anywhere,
         * ignoring letter case, and the description match scans the table.
         */
        if (filter_var($term, FILTER_VALIDATE_IP) !== false) {
            return $query->where('ip_address', $term);
        }

        return $query->where(fn (Builder $query) => $query
            ->whereLike('event', "{$term}%")
            ->orWhereLike('description', "%{$term}%")
        );
    }
}

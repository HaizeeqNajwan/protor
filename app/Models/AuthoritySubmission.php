<?php

namespace App\Models;

use App\Enums\AuthorityName;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AuthoritySubmission extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const DUE_SOON_DAYS = 7;

    protected $fillable = [
        'project_id',
        'submitted_by',
        'authority',
        'submission_type',
        'reference_no',
        'description',
        'status',
        'submitted_at',
        'resubmitted_at',
        'sla_days',
        'expected_response_date',
        'approved_at',
        'approval_ref',
        'query_logs',
        'documents',
        'remarks',
    ];

    protected $attributes = [
        'query_logs' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'authority' => AuthorityName::class,
            'status' => SubmissionStatus::class,
            'submitted_at' => 'date',
            'resubmitted_at' => 'date',
            'expected_response_date' => 'date',
            'approved_at' => 'date',
            'sla_days' => 'integer',
            'query_logs' => 'array',
            'documents' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AuthoritySubmission $s) {
            $s->submitted_by ??= auth()->id();
        });

        // "saving" fires before "creating", so defaults are applied here.
        static::saving(function (AuthoritySubmission $s) {
            $s->status ??= SubmissionStatus::Draft;

            if (! $s->sla_days && $s->authority) {
                $s->sla_days = $s->authority->defaultSlaDays();
            }

            // Moving out of Draft with a submission date implies "Submitted".
            if ($s->status === SubmissionStatus::Draft && $s->submitted_at) {
                $s->status = SubmissionStatus::Submitted;
            }

            $clockStart = $s->resubmitted_at ?? $s->submitted_at;

            $s->expected_response_date = $clockStart
                ? Carbon::parse($clockStart)->addDays((int) $s->sla_days)
                : null;

            if ($s->status === SubmissionStatus::Approved && ! $s->approved_at) {
                $s->approved_at = today();
            }
        });
    }

    /* ---------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------- */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /* ---------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------- */

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isManager()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('submitted_by', $user->id)
                ->orWhereHas('project', fn (Builder $p) => $p->visibleTo($user));
        });
    }

    public function scopeAwaitingAuthority(Builder $query): Builder
    {
        return $query->whereIn('status', SubmissionStatus::awaitingAuthority());
    }

    public function scopeSlaBreached(Builder $query): Builder
    {
        return $query->awaitingAuthority()->whereDate('expected_response_date', '<', today());
    }

    public function scopeSlaDueSoon(Builder $query): Builder
    {
        return $query->awaitingAuthority()
            ->whereBetween('expected_response_date', [today(), today()->addDays(self::DUE_SOON_DAYS)]);
    }

    /* ---------------------------------------------------------------------
     | SLA accessors (used by the badges)
     |--------------------------------------------------------------------- */

    protected function slaDaysRemaining(): Attribute
    {
        return Attribute::get(function (): ?int {
            if (! $this->expected_response_date || ! in_array($this->status, SubmissionStatus::awaitingAuthority(), true)) {
                return null;
            }

            return (int) today()->diffInDays($this->expected_response_date, false);
        });
    }

    protected function slaState(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->status?->isClosed()) {
                return 'Closed';
            }

            if ($this->status === SubmissionStatus::Draft) {
                return 'Not Submitted';
            }

            if ($this->status === SubmissionStatus::QueryRaised) {
                return 'Awaiting Our Reply';
            }

            $days = $this->sla_days_remaining;

            return match (true) {
                $days === null => 'Not Submitted',
                $days < 0 => 'Overdue',
                $days <= self::DUE_SOON_DAYS => 'Due Soon',
                default => 'On Track',
            };
        });
    }

    public static function slaStateColor(string $state): string
    {
        return match ($state) {
            'Overdue' => 'danger',
            'Due Soon', 'Awaiting Our Reply' => 'warning',
            'On Track' => 'success',
            'Closed' => 'gray',
            default => 'gray',
        };
    }

    /* ---------------------------------------------------------------------
     | Domain actions
     |--------------------------------------------------------------------- */

    /** Append an authority query to the query_logs jsonb column. */
    public function logQuery(array $data, User $by): void
    {
        $logs = $this->query_logs ?? [];

        $logs[] = [
            'id' => (string) Str::uuid(),
            'query_date' => Carbon::parse($data['query_date'])->toDateString(),
            'officer' => $data['officer'] ?? null,
            'reference' => $data['reference'] ?? null,
            'details' => $data['details'],
            'reply_due' => filled($data['reply_due'] ?? null) ? Carbon::parse($data['reply_due'])->toDateString() : null,
            'status' => 'open',
            'logged_by' => $by->name,
            'logged_at' => now()->toIso8601String(),
        ];

        $this->query_logs = $logs;
        $this->status = SubmissionStatus::QueryRaised;
        $this->save();
    }

    /** Close open queries and restart the SLA clock from the resubmission date. */
    public function recordResubmission(string $date, ?string $note, User $by): void
    {
        $logs = collect($this->query_logs ?? [])
            ->map(function (array $q) use ($date, $note, $by) {
                if (($q['status'] ?? 'open') === 'open') {
                    $q['status'] = 'replied';
                    $q['replied_at'] = $date;
                    $q['reply_note'] = $note;
                    $q['replied_by'] = $by->name;
                }

                return $q;
            })
            ->all();

        $this->query_logs = $logs;
        $this->resubmitted_at = $date;
        $this->status = SubmissionStatus::Resubmitted;
        $this->save();
    }

    public function openQueriesCount(): int
    {
        return collect($this->query_logs ?? [])->where('status', 'open')->count();
    }
}

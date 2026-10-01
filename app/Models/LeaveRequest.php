<?php

namespace App\Models;

use App\Enums\LeaveDayPart;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\ProjectStatus;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LeaveRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'approver_id',
        'leave_type',
        'start_date',
        'end_date',
        'half_day',
        'day_part',
        'days',
        'reason',
        'handover_notes',
        'status',
        'decided_at',
        'decision_remarks',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'leave_type' => LeaveType::class,
            'status' => LeaveStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'half_day' => 'boolean',
            'day_part' => LeaveDayPart::class,
            'days' => 'decimal:1',
            'decided_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LeaveRequest $leave) {
            $leave->user_id ??= auth()->id();
            $leave->status ??= LeaveStatus::Pending;
        });

        static::saving(function (LeaveRequest $leave) {
            $leave->status ??= LeaveStatus::Pending;
            $leave->day_part ??= LeaveDayPart::Full;

            // A half day is always a single date.
            $leave->half_day = $leave->day_part->isHalfDay();

            if ($leave->half_day) {
                $leave->end_date = $leave->start_date;
            }

            $leave->days = static::countWorkingDays($leave->start_date, $leave->end_date, (bool) $leave->half_day);
        });
    }

    /** Mon–Fri count. Public holidays are not excluded — adjust manually or plug in a holiday table. */
    public static function countWorkingDays($start, $end, bool $halfDay = false): float
    {
        if (! $start || ! $end) {
            return 0;
        }

        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        $days = collect(CarbonPeriod::create($start, $end))
            ->reject(fn (Carbon $d) => $d->isWeekend())
            ->count();

        return $halfDay ? min($days, 1) * 0.5 : (float) $days;
    }

    /* ---------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /* ---------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------- */

    /** Staff only see their own leave; managers see everyone's. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $user->isManager() ? $query : $query->where('user_id', $user->id);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Pending);
    }

    public function scopeCovering(Builder $query, $date): Builder
    {
        return $query->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date);
    }

    /* ---------------------------------------------------------------------
     | Domain
     |--------------------------------------------------------------------- */

    /** A PM cannot approve their own leave; only super admins can. */
    public function canBeDecidedBy(?User $user): bool
    {
        if (! $user || $this->status !== LeaveStatus::Pending) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isManager() && (int) $this->user_id !== (int) $user->id;
    }

    /** Live projects the applicant is assigned to whose target date falls in the leave window. */
    public function clashingProjects(): Collection
    {
        return Project::query()
            ->where('status', '!=', ProjectStatus::Completed)
            ->whereHas('members', fn (Builder $m) => $m->where('users.id', $this->user_id))
            ->whereBetween('target_completion_date', [$this->start_date, $this->end_date])
            ->get(['id', 'project_code', 'title', 'target_completion_date']);
    }

    public function approve(User $by, ?string $remarks = null): void
    {
        $this->update([
            'status' => LeaveStatus::Approved,
            'approver_id' => $by->id,
            'decided_at' => now(),
            'decision_remarks' => $remarks,
        ]);
    }

    public function reject(User $by, ?string $remarks = null): void
    {
        $this->update([
            'status' => LeaveStatus::Rejected,
            'approver_id' => $by->id,
            'decided_at' => now(),
            'decision_remarks' => $remarks,
        ]);
    }
}

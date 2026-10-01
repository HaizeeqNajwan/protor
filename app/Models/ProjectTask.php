<?php

namespace App\Models;

use App\Enums\EngineeringDiscipline;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ProjectTask extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'project_id',
        'assigned_to',
        'created_by',
        'title',
        'description',
        'discipline',
        'stage',
        'sort_order',
        'priority',
        'status',
        'progress_percentage',
        'start_date',
        'due_date',
        'completed_at',
        'notes',
        'progress_logs',
        'attachments',
    ];

    protected $attributes = [
        'progress_percentage' => 0,
        'stage' => 'design',
        'progress_logs' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'discipline' => EngineeringDiscipline::class,
            'stage' => ProjectStage::class,
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'progress_percentage' => 'integer',
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress_logs' => 'array',
            'attachments' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProjectTask $task) {
            $task->created_by ??= auth()->id();
        });

        // Progress changed via the normal edit form (not recordProgress()) → still audit it.
        static::updating(function (ProjectTask $task) {
            if ($task->isDirty('progress_percentage') && ! $task->isDirty('progress_logs') && auth()->check()) {
                $logs = $task->progress_logs ?? [];
                $logs[] = [
                    'at' => now()->toIso8601String(),
                    'by' => auth()->id(),
                    'by_name' => auth()->user()->name,
                    'from' => (int) $task->getOriginal('progress_percentage'),
                    'to' => (int) $task->progress_percentage,
                    'note' => null,
                ];
                $task->progress_logs = $logs;
            }
        });

        // Status always follows progress: 0% not started, 1–99% in progress, 100% completed.
        // (Note: "saving" fires BEFORE "creating", so defaults live here.)
        static::saving(function (ProjectTask $task) {
            $task->priority ??= TaskPriority::Medium;
            $task->progress_percentage = max(0, min(100, (int) $task->progress_percentage));

            $task->status = match (true) {
                $task->progress_percentage >= 100 => TaskStatus::Completed,
                $task->progress_percentage > 0 => TaskStatus::InProgress,
                default => TaskStatus::NotStarted,
            };

            $task->completed_at = $task->status === TaskStatus::Completed
                ? ($task->completed_at ?? now())
                : null;
        });
    }

    /* ---------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------- */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ---------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------- */

    /** Works are visible to anyone who can see their project. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->whereHas('project', fn (Builder $project) => $project->visibleTo($user));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', TaskStatus::open());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', '<', today());
    }

    /** Pre-Design → Design → Post-Design, then template order. */
    public function scopeInWorkOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw("case stage when 'pre_design' then 1 when 'design' then 2 else 3 end")
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function scopeInStage(Builder $query, ProjectStage $stage): Builder
    {
        return $query->where('stage', $stage);
    }

    /* ---------------------------------------------------------------------
     | Domain
     |--------------------------------------------------------------------- */

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->status !== TaskStatus::Completed
            && $this->due_date->isBefore(today());
    }

    public function isAssignedTo(?User $user): bool
    {
        return $user && (int) $this->assigned_to === (int) $user->id;
    }

    /**
     * Progress is entered by the staff assigned to the project — never by managers —
     * at any stage, until the project is completed.
     */
    public function canUpdateProgress(?User $user): bool
    {
        return $user !== null
            && ! $user->isManager()
            && $this->project !== null
            && $this->project->status !== ProjectStatus::Completed
            && $this->project->isMember($user);
    }

    /** @return array{by_name: string, at: Carbon}|null */
    public function lastProgressUpdate(): ?array
    {
        $last = collect($this->progress_logs ?? [])->last();

        return $last ? ['by_name' => $last['by_name'] ?? '—', 'at' => Carbon::parse($last['at'])] : null;
    }

    /** Update progress and append an audit entry to the progress_logs jsonb column. */
    public function recordProgress(User $by, int $percentage, ?string $note = null): void
    {
        $from = (int) $this->progress_percentage;

        $logs = $this->progress_logs ?? [];
        $logs[] = [
            'at' => now()->toIso8601String(),
            'by' => $by->id,
            'by_name' => $by->name,
            'from' => $from,
            'to' => $percentage,
            'note' => $note,
        ];

        $this->progress_percentage = $percentage;
        $this->progress_logs = $logs;

        if (filled($note)) {
            $this->notes = trim(($this->notes ? $this->notes."\n" : '').'['.now()->format('d/m/Y')."] {$by->name}: {$note}");
        }

        $this->save();
    }
}

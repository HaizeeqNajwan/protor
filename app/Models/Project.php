<?php

namespace App\Models;

use App\Enums\EngineeringDiscipline;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Project extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /** Memoised per instance; the dashboard asks for it several times per render. */
    protected ?array $stageBreakdownCache = null;

    protected $fillable = [
        'project_code',
        'title',
        'client_name',
        'client_contact',
        'location',
        'discipline',
        'status',
        'current_stage',
        'contract_sum',
        'consultancy_fee',
        'start_date',
        'target_completion_date',
        'actual_completion_date',
        'project_manager_id',
        'created_by',
        'description',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'active',
        'current_stage' => 'pre_design',
    ];

    protected function casts(): array
    {
        return [
            'discipline' => EngineeringDiscipline::class,
            'status' => ProjectStatus::class,
            'current_stage' => ProjectStage::class,
            'contract_sum' => 'decimal:2',
            'consultancy_fee' => 'decimal:2',
            'start_date' => 'date',
            'target_completion_date' => 'date',
            'actual_completion_date' => 'date',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->created_by ??= auth()->id();
        });

        // Every new project starts with the standard Pre-Design / Design / Post-Design works.
        static::created(function (Project $project) {
            $project->applyWorkTemplates();
        });

        static::saving(function (Project $project) {
            if ($project->status === ProjectStatus::Completed && ! $project->actual_completion_date) {
                $project->actual_completion_date = now()->toDateString();
            }
        });
    }

    /* ---------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------- */

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function authoritySubmissions(): HasMany
    {
        return $this->hasMany(AuthoritySubmission::class);
    }

    /* ---------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------- */

    /**
     * Managers see every project. Staff see projects where they are a team
     * member, or where they are PIC of at least one task.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isManager()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->whereHas('members', fn (Builder $m) => $m->where('users.id', $user->id))
                ->orWhereHas('tasks', fn (Builder $t) => $t->where('assigned_to', $user->id));
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ProjectStatus::active());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', '!=', ProjectStatus::Completed)
            ->whereDate('target_completion_date', '<', today());
    }

    /** Eager-load just enough task data for stageBreakdown() / scheduleHealth(). */
    public function scopeWithStageData(Builder $query): Builder
    {
        return $query->with([
            'projectManager:id,name',
            'members:id,name',
            'tasks' => fn ($tasks) => $tasks->select(['id', 'project_id', 'stage', 'status', 'progress_percentage', 'due_date', 'updated_at']),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Stage progress (Pre-Design → Design → Post-Design)
     |
     | These read the loaded `tasks` relation, so eager-load
     | tasks:id,project_id,stage,status,progress_percentage,due_date
     | when rendering many projects at once.
     |--------------------------------------------------------------------- */

    /**
     * @return array<string, array{stage: ProjectStage, total: int, completed: int, overdue: int, progress: float|null}>
     */
    public function stageBreakdown(): array
    {
        return $this->stageBreakdownCache ??= $this->buildStageBreakdown();
    }

    /**
     * @return array<string, array{stage: ProjectStage, total: int, completed: int, overdue: int, progress: float|null}>
     */
    protected function buildStageBreakdown(): array
    {
        $grouped = $this->tasks->groupBy(fn (ProjectTask $task) => $task->stage?->value ?? ProjectStage::Design->value);

        return collect(ProjectStage::cases())
            ->mapWithKeys(function (ProjectStage $stage) use ($grouped) {
                $tasks = $grouped->get($stage->value, collect());

                return [$stage->value => [
                    'stage' => $stage,
                    'total' => $tasks->count(),
                    'completed' => $tasks->where('status', TaskStatus::Completed)->count(),
                    'overdue' => $tasks->filter(fn (ProjectTask $task) => $task->isOverdue())->count(),
                    'progress' => $tasks->isEmpty() ? null : round((float) $tasks->avg('progress_percentage'), 1),
                ]];
            })
            ->all();
    }

    /** Mean progress of every task on the project (0–100). */
    public function overallProgress(): float
    {
        return $this->tasks->isEmpty()
            ? 0.0
            : round((float) $this->tasks->avg('progress_percentage'), 1);
    }

    /** The stage the project is in. Managers can switch it freely until the project is completed. */
    public function currentStage(): ProjectStage
    {
        return $this->current_stage ?? ProjectStage::PreDesign;
    }

    /** Managers and the staff assigned to the project can move it to any stage, until it is completed. */
    public function canSwitchStage(?User $user): bool
    {
        return $user !== null
            && $this->status !== ProjectStatus::Completed
            && ($user->isManager() || $this->isMember($user));
    }

    /** Copy the active work templates into this project (skips titles it already has). */
    public function applyWorkTemplates(): int
    {
        $existing = $this->tasks()->pluck('title')->map(fn ($t) => mb_strtolower($t))->all();
        $created = 0;

        foreach (WorkTemplate::query()->active()->ordered()->get() as $template) {
            if (in_array(mb_strtolower($template->title), $existing, true)) {
                continue;
            }

            $this->tasks()->create([
                'title' => $template->title,
                'description' => $template->description,
                'stage' => $template->stage,
                'sort_order' => $template->sort_order,
                'discipline' => $this->discipline,
                'progress_percentage' => 0,
            ]);

            $created++;
        }

        $this->unsetRelation('tasks');

        return $created;
    }

    /** When any work on the project last changed (null when there are no works). */
    public function lastActivityAt(): ?Carbon
    {
        return $this->tasks->max('updated_at');
    }

    /** Live project with no work updated for $days days (ignores brand-new projects). */
    public function isStalled(int $days = 14): bool
    {
        return $this->status === ProjectStatus::Active
            && $this->hasWorks()
            && $this->remainingWorksCount() > 0
            && $this->created_at?->lt(now()->subDays($days))
            && ($this->lastActivityAt()?->lt(now()->subDays($days)) ?? true);
    }

    /** Progress of the stage the project is currently in. */
    public function currentStageProgress(): ?float
    {
        return $this->stageBreakdown()[$this->currentStage()->value]['progress'];
    }

    public function remainingWorksCount(): int
    {
        return $this->tasks->where('progress_percentage', '<', 100)->count();
    }

    /** All works at 100% → the team (or a manager) can mark the project completed. */
    public function canComplete(?User $user): bool
    {
        return $user !== null
            && $this->status !== ProjectStatus::Completed
            && $this->hasWorks()
            && $this->remainingWorksCount() === 0
            && ($user->isManager() || $this->isMember($user));
    }

    public function markCompleted(User $by): void
    {
        $this->update([
            'status' => ProjectStatus::Completed,
            'actual_completion_date' => today(),
        ]);

        if ($this->projectManager && $this->projectManager->isNot($by)) {
            Notification::make()
                ->title("{$this->project_code} completed")
                ->body("{$by->name} marked “{$this->title}” as completed.")
                ->success()
                ->sendToDatabase($this->projectManager);
        }
    }

    public function canReopen(?User $user): bool
    {
        return (bool) $user?->isManager() && $this->status === ProjectStatus::Completed;
    }

    public function reopen(): void
    {
        $this->update(['status' => ProjectStatus::Active, 'actual_completion_date' => null]);
    }

    public function switchStage(ProjectStage $stage): void
    {
        $this->update(['current_stage' => $stage]);
    }

    public function hasWorks(): bool
    {
        return $this->tasks->isNotEmpty();
    }

    /** % of the planned duration (start → target) that has elapsed. Null if unscheduled. */
    public function timeElapsedPercentage(): ?float
    {
        if (! $this->start_date || ! $this->target_completion_date) {
            return null;
        }

        $planned = max(1, $this->start_date->diffInDays($this->target_completion_date));
        $elapsed = $this->start_date->diffInDays(today(), false);

        return round(max(0, min(100, ($elapsed / $planned) * 100)), 1);
    }

    /**
     * Progress vs. time elapsed: positive = ahead of plan, negative = behind.
     */
    public function scheduleVariance(): ?float
    {
        $elapsed = $this->timeElapsedPercentage();

        return $elapsed === null ? null : round($this->overallProgress() - $elapsed, 1);
    }

    /**
     * @return 'completed'|'overdue'|'behind'|'at_risk'|'on_track'|'unscheduled'
     */
    public function scheduleHealth(): string
    {
        if ($this->status === ProjectStatus::Completed) {
            return 'completed';
        }

        if ($this->target_completion_date?->isBefore(today())) {
            return 'overdue';
        }

        $variance = $this->scheduleVariance();

        return match (true) {
            $variance === null => 'unscheduled',
            $variance <= -20 => 'behind',
            $variance <= -8 => 'at_risk',
            default => 'on_track',
        };
    }

    public function isMember(User $user): bool
    {
        if ((int) $this->project_manager_id === (int) $user->id) {
            return true;
        }

        return $this->relationLoaded('members')
            ? $this->members->contains('id', $user->id)
            : $this->members()->where('users.id', $user->id)->exists();
    }
}

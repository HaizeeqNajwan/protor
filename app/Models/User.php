<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\TaskStatus;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Replace your existing app/Models/User.php with this, or merge the marked parts
 * (HasRoles, FilamentUser, role constants, helpers, relationships) into it.
 */
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    // Role names — must match the roles created in Shield / RolesAndPermissionsSeeder.
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_PROJECT_MANAGER = 'project_manager';

    public const ROLE_STAFF_ENGINEER = 'staff_engineer';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole([
            self::ROLE_SUPER_ADMIN,
            self::ROLE_PROJECT_MANAGER,
            self::ROLE_STAFF_ENGINEER,
        ]);
    }

    /* ---------------------------------------------------------------------
     | Role helpers
     |--------------------------------------------------------------------- */

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    /** PMs and super admins see firm-wide data and can approve things. */
    public function isManager(): bool
    {
        return $this->hasAnyRole([self::ROLE_SUPER_ADMIN, self::ROLE_PROJECT_MANAGER]);
    }

    public function isStaff(): bool
    {
        return ! $this->isManager();
    }

    /* ---------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------- */

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_manager_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'assigned_to');
    }

    public function openTasks(): HasMany
    {
        return $this->assignedTasks()->whereIn('status', TaskStatus::open());
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function isOnLeave(?\DateTimeInterface $date = null): bool
    {
        $date = $date ?? now();

        return $this->leaveRequests()
            ->where('status', LeaveStatus::Approved)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }
}

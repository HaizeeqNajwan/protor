<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates ProTor roles and maps Shield-generated permissions onto them.
 *
 * Run AFTER `php artisan shield:generate --all --panel=admin`.
 * Works with both Shield naming styles ("view_any_project::task" and
 * "ViewAny:ProjectTask") by comparing normalised names.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** Subject => actions a Staff Engineer gets. */
    protected array $staffMatrix = [
        'Project' => ['view', 'viewAny'],
        'ProjectTask' => ['view', 'viewAny', 'update'],
        'AuthoritySubmission' => ['view', 'viewAny', 'create', 'update'],
        'LeaveRequest' => ['view', 'viewAny', 'create', 'update'],
    ];

    /** Subjects a Project Manager gets FULL access to (every generated action). */
    protected array $managerSubjects = [
        'Project',
        'ProjectTask',
        'AuthoritySubmission',
        'LeaveRequest',
        'KeyNumbersWidget',
        'TeamWidget',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        Role::findOrCreate(User::ROLE_SUPER_ADMIN, $guard);
        $pm = Role::findOrCreate(User::ROLE_PROJECT_MANAGER, $guard);
        $staff = Role::findOrCreate(User::ROLE_STAFF_ENGINEER, $guard);

        $all = Permission::query()->where('guard_name', $guard)->get();

        if ($all->isEmpty()) {
            $this->command?->warn('No permissions found. Run `php artisan shield:generate --all --panel=admin` first, then re-run this seeder.');

            return;
        }

        // Project Manager: every permission whose subject is one of ours.
        $pmPermissions = $all->filter(function (Permission $p) {
            $name = $this->normalise($p->name);

            foreach ($this->managerSubjects as $subject) {
                if (str_ends_with($name, $this->normalise($subject))) {
                    // Require the remainder to be a known action, so "project" never swallows "projecttask".
                    $prefix = substr($name, 0, -strlen($this->normalise($subject)));

                    if ($this->isKnownActionPrefix($prefix)) {
                        return true;
                    }
                }
            }

            return false;
        });

        $pm->syncPermissions($pmPermissions);

        // Staff Engineer: exact action + subject matches only.
        $wanted = collect($this->staffMatrix)
            ->flatMap(fn (array $actions, string $subject) => collect($actions)
                ->map(fn (string $action) => $this->normalise($action.$subject)))
            ->all();

        $staffPermissions = $all->filter(fn (Permission $p) => in_array($this->normalise($p->name), $wanted, true));
        $staff->syncPermissions($staffPermissions);

        $this->report('project_manager', $pmPermissions);
        $this->report('staff_engineer', $staffPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function normalise(string $value): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $value));
    }

    /** Known Shield resource action prefixes, normalised. */
    protected function isKnownActionPrefix(string $prefix): bool
    {
        return in_array($prefix, [
            'view', 'viewany', 'create', 'update', 'delete', 'deleteany',
            'restore', 'restoreany', 'forcedelete', 'forcedeleteany',
            'replicate', 'reorder',
            'widget', // v3 widget permissions look like "widget_StaffWorkloadWidget"
        ], true);
    }

    protected function report(string $role, Collection $permissions): void
    {
        $this->command?->info(sprintf('%s: %d permission(s)', $role, $permissions->count()));
        $this->command?->line('  '.$permissions->pluck('name')->sort()->implode(', '));
    }
}

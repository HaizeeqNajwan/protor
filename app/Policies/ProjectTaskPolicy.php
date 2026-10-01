<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProjectTask;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProjectTaskPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProjectTask');
    }

    public function view(AuthUser $authUser, ProjectTask $projectTask): bool
    {
        return $authUser->can('View:ProjectTask');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProjectTask');
    }

    public function update(AuthUser $authUser, ProjectTask $projectTask): bool
    {
        return $authUser->can('Update:ProjectTask');
    }

    public function delete(AuthUser $authUser, ProjectTask $projectTask): bool
    {
        return $authUser->can('Delete:ProjectTask');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ProjectTask');
    }

    public function restore(AuthUser $authUser, ProjectTask $projectTask): bool
    {
        return $authUser->can('Restore:ProjectTask');
    }

    public function forceDelete(AuthUser $authUser, ProjectTask $projectTask): bool
    {
        return $authUser->can('ForceDelete:ProjectTask');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProjectTask');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProjectTask');
    }

    public function replicate(AuthUser $authUser, ProjectTask $projectTask): bool
    {
        return $authUser->can('Replicate:ProjectTask');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProjectTask');
    }
}

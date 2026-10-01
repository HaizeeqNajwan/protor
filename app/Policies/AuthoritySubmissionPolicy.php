<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuthoritySubmission;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AuthoritySubmissionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AuthoritySubmission');
    }

    public function view(AuthUser $authUser, AuthoritySubmission $authoritySubmission): bool
    {
        return $authUser->can('View:AuthoritySubmission');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AuthoritySubmission');
    }

    public function update(AuthUser $authUser, AuthoritySubmission $authoritySubmission): bool
    {
        return $authUser->can('Update:AuthoritySubmission');
    }

    public function delete(AuthUser $authUser, AuthoritySubmission $authoritySubmission): bool
    {
        return $authUser->can('Delete:AuthoritySubmission');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AuthoritySubmission');
    }

    public function restore(AuthUser $authUser, AuthoritySubmission $authoritySubmission): bool
    {
        return $authUser->can('Restore:AuthoritySubmission');
    }

    public function forceDelete(AuthUser $authUser, AuthoritySubmission $authoritySubmission): bool
    {
        return $authUser->can('ForceDelete:AuthoritySubmission');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AuthoritySubmission');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AuthoritySubmission');
    }

    public function replicate(AuthUser $authUser, AuthoritySubmission $authoritySubmission): bool
    {
        return $authUser->can('Replicate:AuthoritySubmission');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AuthoritySubmission');
    }
}

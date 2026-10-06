@php
    $user = auth()->user();
    $initials = str($user?->name ?? '?')->explode(' ')->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
    $role = $user?->isSuperAdmin() ? 'Super admin' : ($user?->isManager() ? 'Project manager' : 'Staff engineer');
@endphp

<div class="pt-sidebar-footer">
    <span class="pt-me-avatar">{{ $initials }}</span>
    <span class="pt-me-text">
        <span class="pt-me-name">{{ $user?->name }}</span>
        <span class="pt-me-role">{{ $role }}</span>
    </span>
</div>

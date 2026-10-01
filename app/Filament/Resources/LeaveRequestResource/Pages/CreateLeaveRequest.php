<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Enums\LeaveStatus;
use App\Filament\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected static ?string $title = 'Apply for Leave';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Staff can only ever apply for themselves.
        if (! auth()->user()?->isManager() || blank($data['user_id'] ?? null)) {
            $data['user_id'] = auth()->id();
        }

        $data['status'] = LeaveStatus::Pending;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var LeaveRequest $leave */
        $leave = $this->record;

        // Alert all PMs / super admins (except the applicant).
        $approvers = User::query()
            ->whereHas('roles', fn ($r) => $r->whereIn('name', [User::ROLE_PROJECT_MANAGER, User::ROLE_SUPER_ADMIN]))
            ->whereKeyNot($leave->user_id)
            ->get();

        if ($approvers->isNotEmpty()) {
            Notification::make()
                ->title("Leave request from {$leave->user->name}")
                ->body("{$leave->leave_type->getLabel()}: {$leave->start_date->format('d M')} – {$leave->end_date->format('d M Y')} ({$leave->days} day(s))")
                ->warning()
                ->sendToDatabase($approvers);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\EngineeringDiscipline;
use App\Enums\LeaveDayPart;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\SubmissionStatus;
use App\Enums\TaskStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\AuthoritySubmissionResource\Pages\CreateAuthoritySubmission;
use App\Filament\Resources\AuthoritySubmissionResource\Pages\ListAuthoritySubmissions;
use App\Filament\Resources\LeaveRequestResource\Pages\CreateLeaveRequest;
use App\Filament\Resources\LeaveRequestResource\Pages\ListLeaveRequests;
use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Resources\ProjectResource\Pages\ViewProject;
use App\Filament\Resources\ProjectResource\RelationManagers\WorksRelationManager;
use App\Filament\Resources\WorkTemplateResource;
use App\Filament\Widgets\ProjectBoardWidget;
use App\Models\AuthoritySubmission;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * End-to-end role flows, driven through the real Filament pages with the real
 * Shield permissions. Requires Postgres (see README of the test report).
 */
class RoleFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $staff;

    protected User $otherStaff;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        // Same permission names `shield:generate` produces ("ViewAny:Project", …).
        foreach (['Project', 'ProjectTask', 'AuthoritySubmission', 'LeaveRequest', 'KeyNumbersWidget', 'TeamWidget'] as $subject) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'RestoreAny', 'ForceDelete', 'ForceDeleteAny', 'Replicate', 'Reorder'] as $action) {
                Permission::findOrCreate("{$action}:{$subject}", 'web');
            }
        }

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = $this->makeUser(User::ROLE_PROJECT_MANAGER, 'Maya Manager');
        $this->staff = $this->makeUser(User::ROLE_STAFF_ENGINEER, 'Sam Staff');
        $this->otherStaff = $this->makeUser(User::ROLE_STAFF_ENGINEER, 'Olive Other');
    }

    protected function makeUser(string $role, string $name): User
    {
        return tap(User::factory()->create(['name' => $name]), fn (User $u) => $u->assignRole($role));
    }

    protected function project(array $attrs = []): Project
    {
        return Project::query()->create([
            'project_code' => 'PRT-'.fake()->unique()->numberBetween(1000, 9999),
            'title' => 'Sample project',
            'client_name' => 'Client Sdn Bhd',
            'discipline' => EngineeringDiscipline::Structural,
            'project_manager_id' => $this->manager->id,
            ...$attrs,
        ]);
    }

    /* ======================================================================
     | MANAGER FLOW
     |====================================================================== */

    public function test_manager_01_can_open_dashboard_and_lists(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(ListProjects::class)->assertOk();
        Livewire::test(ListLeaveRequests::class)->assertOk();
        Livewire::test(ListAuthoritySubmissions::class)->assertOk();
        $this->get('/')->assertOk();
        $this->assertTrue(WorkTemplateResource::canAccess());
    }

    public function test_manager_02_creates_project_with_staff_and_standard_works(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(CreateProject::class)
            ->fillForm([
                'project_code' => 'PRT-2026-001',
                'title' => 'Riverside Drainage',
                'client_name' => 'Client Sdn Bhd',
                'discipline' => EngineeringDiscipline::Civil->value,
                'project_manager_id' => $this->manager->id,
                'members' => [$this->staff->id],
                'start_date' => today()->toDateString(),
                'target_completion_date' => today()->addMonths(3)->toDateString(),
                'metadata' => ['Lot No' => '1234', 'SPA file ref' => 'SPA/2026/01'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::where('project_code', 'PRT-2026-001')->firstOrFail();

        $this->assertSame(16, $project->tasks()->count(), '5 pre-design + 6 design + 5 post-design works are copied in');
        $this->assertTrue($project->members->contains($this->staff));
        $this->assertSame('1234', $project->metadata['Lot No']);
        $this->assertSame(ProjectStage::PreDesign, $project->currentStage());
    }

    public function test_manager_03_create_form_has_no_commercial_section(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(CreateProject::class)
            ->assertFormFieldDoesNotExist('contract_sum')
            ->assertFormFieldDoesNotExist('consultancy_fee')
            ->assertFormFieldExists('metadata');
    }

    public function test_manager_04_project_code_must_be_unique_and_required_fields_enforced(): void
    {
        $this->actingAs($this->manager);
        $this->project(['project_code' => 'DUP-1']);

        Livewire::test(CreateProject::class)
            ->fillForm(['project_code' => 'DUP-1', 'title' => 'X', 'client_name' => 'Y', 'discipline' => 'civil', 'project_manager_id' => $this->manager->id])
            ->call('create')
            ->assertHasFormErrors(['project_code' => 'unique']);

        Livewire::test(CreateProject::class)
            ->fillForm(['project_code' => '', 'title' => ''])
            ->call('create')
            ->assertHasFormErrors(['project_code' => 'required', 'title' => 'required']);
    }

    public function test_manager_05_edits_project_and_manages_works_list(): void
    {
        $this->actingAs($this->manager);
        $project = $this->project();

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->fillForm(['title' => 'Renamed project'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Renamed project', $project->fresh()->title);

        $rm = Livewire::test(WorksRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class]);
        $rm->assertActionVisible(TestAction::make('addWork')->table())
            ->assertActionVisible(TestAction::make('applyTemplates')->table());

        $rm->callAction(TestAction::make('addWork')->table(), ['stage' => ProjectStage::Design->value, 'title' => 'Extra geotech review'])
            ->assertHasNoFormErrors();
        $this->assertSame(17, $project->tasks()->count());
    }

    public function test_manager_06_cannot_update_work_progress(): void
    {
        $this->actingAs($this->manager);
        $project = $this->project();
        $work = $project->tasks()->first();

        Livewire::test(WorksRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->assertTableActionHidden('updateProgress', $work);
    }

    public function test_manager_07_sees_every_project_regardless_of_membership(): void
    {
        $this->actingAs($this->manager);
        $a = $this->project();
        $b = $this->project();
        $b->members()->attach($this->staff);

        Livewire::test(ListProjects::class)->assertCanSeeTableRecords([$a, $b]);
    }

    public function test_manager_08_approves_and_rejects_staff_leave(): void
    {
        $leaveA = LeaveRequest::create(['user_id' => $this->staff->id, 'leave_type' => LeaveType::Annual, 'start_date' => today()->addWeek(), 'end_date' => today()->addWeek()->addDays(2)]);
        $leaveB = LeaveRequest::create(['user_id' => $this->otherStaff->id, 'leave_type' => LeaveType::Medical, 'start_date' => today()->addWeeks(2), 'end_date' => today()->addWeeks(2)]);
        $this->actingAs($this->manager);

        $list = Livewire::test(ListLeaveRequests::class)->assertCanSeeTableRecords([$leaveA, $leaveB]);

        $list->callTableAction('approve', $leaveA);
        $list->callTableAction('reject', $leaveB, ['remarks' => 'Team short-staffed']);

        $this->assertSame(LeaveStatus::Approved, $leaveA->fresh()->status);
        $this->assertSame(LeaveStatus::Rejected, $leaveB->fresh()->status);
        $this->assertSame('Team short-staffed', $leaveB->fresh()->decision_remarks);
        $this->assertSame($this->manager->id, $leaveA->fresh()->approver_id);
    }

    public function test_manager_09_cannot_approve_own_leave(): void
    {
        $own = LeaveRequest::create(['user_id' => $this->manager->id, 'leave_type' => LeaveType::Annual, 'start_date' => today()->addWeek(), 'end_date' => today()->addWeek()]);
        $this->assertFalse($own->canBeDecidedBy($this->manager));

        $this->actingAs($this->manager);
        Livewire::test(ListLeaveRequests::class)->assertTableActionHidden('approve', $own);
    }

    public function test_manager_10_can_switch_stage_complete_and_reopen_project(): void
    {
        $this->actingAs($this->manager);
        $project = $this->project();
        $project->members()->attach($this->staff);

        $project->switchStage(ProjectStage::PostDesign);
        $this->assertSame(ProjectStage::PostDesign, $project->fresh()->currentStage());

        $this->assertFalse($project->fresh()->canComplete($this->manager), 'cannot complete with open works');
        $project->tasks->each(fn ($w) => $w->recordProgress($this->staff, 100));

        $project = $project->fresh();
        $this->assertTrue($project->canComplete($this->manager));
        $project->markCompleted($this->manager);
        $this->assertSame(ProjectStatus::Completed, $project->fresh()->status);

        $this->assertFalse($project->fresh()->canSwitchStage($this->manager), 'stage locked once completed');
        $this->assertTrue($project->fresh()->canReopen($this->manager));
        $project->fresh()->reopen();
        $this->assertSame(ProjectStatus::Active, $project->fresh()->status);
        $this->assertNull($project->fresh()->actual_completion_date);
    }

    public function test_manager_11_manages_authority_submission_to_approval_and_rejection(): void
    {
        $this->actingAs($this->manager);
        $project = $this->project();

        Livewire::test(CreateAuthoritySubmission::class)
            ->fillForm(['project_id' => $project->id, 'authority' => 'DBKU', 'submission_type' => 'Building plan', 'status' => 'draft', 'submitted_at' => today()->toDateString(), 'sla_days' => 30])
            ->call('create')
            ->assertHasNoFormErrors();

        $sub = AuthoritySubmission::firstOrFail();
        $this->assertSame(SubmissionStatus::Submitted, $sub->status, 'a submitted date moves Draft → Submitted');
        $this->assertSame(today()->addDays(30)->toDateString(), $sub->expected_response_date->toDateString());

        $list = Livewire::test(ListAuthoritySubmissions::class);
        $list->callTableAction('logQuery', $sub, ['query_date' => today()->toDateString(), 'details' => 'Revise setbacks', 'reply_due' => today()->addDays(14)->toDateString()]);
        $this->assertSame(SubmissionStatus::QueryRaised, $sub->fresh()->status);

        $list->callTableAction('recordResubmission', $sub, ['resubmitted_at' => today()->toDateString()]);
        $this->assertSame(SubmissionStatus::Resubmitted, $sub->fresh()->status);
        $this->assertSame('replied', $sub->fresh()->query_logs[0]['status']);

        $list->callTableAction('markApproved', $sub, ['approved_at' => today()->toDateString(), 'approval_ref' => 'APP-1']);
        $this->assertSame(SubmissionStatus::Approved, $sub->fresh()->status);

        $second = AuthoritySubmission::create(['project_id' => $project->id, 'authority' => 'SPA', 'submission_type' => 'Drainage', 'submitted_at' => today()]);
        $list->callTableAction('markRejected', $second, ['remarks' => 'Incomplete']);
        $this->assertSame(SubmissionStatus::Rejected, $second->fresh()->status);
    }

    public function test_manager_12_work_templates_are_manageable(): void
    {
        $this->actingAs($this->manager);
        $this->get(WorkTemplateResource::getUrl())->assertOk();
    }

    /* ======================================================================
     | STAFF FLOW
     |====================================================================== */

    public function test_staff_01_dashboard_loads_but_no_project_create_or_settings(): void
    {
        $this->actingAs($this->staff);

        $this->get('/')->assertOk();
        $this->assertFalse(WorkTemplateResource::canAccess());
        $this->get(WorkTemplateResource::getUrl())->assertForbidden();
        $this->get(route('filament.admin.resources.projects.create'))->assertForbidden();
    }

    public function test_staff_02_only_sees_assigned_projects(): void
    {
        $mine = $this->project(['title' => 'Mine']);
        $mine->members()->attach($this->staff);
        $theirs = $this->project(['title' => 'Theirs']);
        $this->actingAs($this->staff);

        Livewire::test(ListProjects::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->get(route('filament.admin.resources.projects.view', $theirs))->assertNotFound();
    }

    public function test_staff_03_updates_progress_with_note_and_audit_log(): void
    {
        $project = $this->project();
        $project->members()->attach($this->staff);
        $work = $project->tasks()->first();
        $this->actingAs($this->staff);

        $rm = Livewire::test(WorksRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class]);
        $rm->assertTableActionVisible('updateProgress', $work)
            ->assertActionHidden(TestAction::make('addWork')->table())
            ->callTableAction('updateProgress', $work, ['progress_percentage' => 45, 'note' => 'Survey done']);

        $work = $work->fresh();
        $this->assertSame(45, $work->progress_percentage);
        $this->assertSame(TaskStatus::InProgress, $work->status);
        $this->assertSame('Sam Staff', $work->progress_logs[0]['by_name']);
        $this->assertStringContainsString('Survey done', $work->notes);

        $rm->callTableAction('updateProgress', $work, ['progress_percentage' => 100]);
        $this->assertSame(TaskStatus::Completed, $work->fresh()->status);
        $this->assertNotNull($work->fresh()->completed_at);
    }

    public function test_staff_04_progress_input_is_validated(): void
    {
        $project = $this->project();
        $project->members()->attach($this->staff);
        $work = $project->tasks()->first();
        $this->actingAs($this->staff);

        Livewire::test(WorksRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewProject::class])
            ->callTableAction('updateProgress', $work, ['progress_percentage' => 140])
            ->assertHasTableActionErrors(['progress_percentage' => 'max']);

        $this->assertSame(0, $work->fresh()->progress_percentage);
    }

    public function test_staff_05_cannot_update_progress_on_unassigned_project(): void
    {
        $project = $this->project();
        $project->members()->attach($this->otherStaff);
        $work = $project->tasks()->first();

        $this->assertFalse($work->canUpdateProgress($this->staff));

        $this->actingAs($this->staff);
        Livewire::test(ProjectBoardWidget::class)->call('saveProgress', $work->id, 70);
        $this->assertSame(0, $work->fresh()->progress_percentage);
    }

    public function test_staff_06_switches_stage_and_completes_project_from_board(): void
    {
        $project = $this->project();
        $project->members()->attach($this->staff);
        $this->actingAs($this->staff);

        $board = Livewire::test(ProjectBoardWidget::class)->call('select', $project->id)->call('switchStage', ProjectStage::PostDesign->value);
        $this->assertSame(ProjectStage::PostDesign, $project->fresh()->currentStage());

        $board->call('completeProject');
        $this->assertSame(ProjectStatus::Active, $project->fresh()->status, 'blocked while works are open');

        $project->tasks->each(fn ($w) => $w->recordProgress($this->staff, 100));
        $board->call('completeProject');
        $this->assertSame(ProjectStatus::Completed, $project->fresh()->status);
    }

    public function test_staff_07_cannot_progress_or_switch_a_completed_project(): void
    {
        $project = $this->project();
        $project->members()->attach($this->staff);
        $project->update(['status' => ProjectStatus::Completed]);
        $work = $project->tasks()->first();

        $this->assertFalse($work->canUpdateProgress($this->staff));
        $this->assertFalse($project->fresh()->canSwitchStage($this->staff));
        $this->assertFalse($project->fresh()->canReopen($this->staff));
    }

    public function test_staff_08_cannot_edit_or_delete_projects(): void
    {
        $project = $this->project();
        $project->members()->attach($this->staff);
        $this->actingAs($this->staff);

        $this->get(route('filament.admin.resources.projects.edit', $project))->assertForbidden();
    }

    public function test_staff_09_files_own_leave_and_sees_only_own(): void
    {
        $this->actingAs($this->staff);
        $others = LeaveRequest::create(['user_id' => $this->otherStaff->id, 'leave_type' => LeaveType::Annual, 'start_date' => today()->addWeek(), 'end_date' => today()->addWeek()]);

        Livewire::test(CreateLeaveRequest::class)
            ->fillForm(['leave_type' => LeaveType::Annual->value, 'day_part' => LeaveDayPart::Full->value, 'start_date' => today()->next('Monday')->toDateString(), 'end_date' => today()->next('Monday')->addDays(2)->toDateString(), 'reason' => 'Family'])
            ->call('create')
            ->assertHasNoFormErrors();

        $mine = LeaveRequest::where('user_id', $this->staff->id)->firstOrFail();
        $this->assertSame(LeaveStatus::Pending, $mine->status);
        $this->assertEquals(3.0, (float) $mine->days);

        Livewire::test(ListLeaveRequests::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$others])
            ->assertTableActionHidden('approve', $mine)
            ->assertTableActionHidden('reject', $mine);
    }

    public function test_staff_10_can_cancel_own_pending_leave(): void
    {
        $this->actingAs($this->staff);
        $mine = LeaveRequest::create(['user_id' => $this->staff->id, 'leave_type' => LeaveType::Annual, 'start_date' => today()->addWeek(), 'end_date' => today()->addWeek()]);

        Livewire::test(ListLeaveRequests::class)->callTableAction('cancel', $mine);
        $this->assertSame(LeaveStatus::Cancelled, $mine->fresh()->status);
    }

    public function test_staff_11_logs_authority_submission_on_assigned_project_but_cannot_reject(): void
    {
        $project = $this->project();
        $project->members()->attach($this->staff);
        $this->actingAs($this->staff);

        Livewire::test(CreateAuthoritySubmission::class)
            ->fillForm(['project_id' => $project->id, 'authority' => 'MBKS', 'submission_type' => 'Road layout', 'status' => 'draft', 'submitted_at' => today()->toDateString(), 'sla_days' => 21])
            ->call('create')
            ->assertHasNoFormErrors();

        $sub = AuthoritySubmission::firstOrFail();
        $list = Livewire::test(ListAuthoritySubmissions::class)->assertCanSeeTableRecords([$sub]);
        $list->assertTableActionHidden('markRejected', $sub);
        $list->callTableAction('logQuery', $sub, ['query_date' => today()->toDateString(), 'details' => 'Need levels']);
        $this->assertSame(SubmissionStatus::QueryRaised, $sub->fresh()->status);
    }

    public function test_staff_12_cannot_see_submissions_of_unassigned_projects(): void
    {
        $other = $this->project();
        $sub = AuthoritySubmission::create(['project_id' => $other->id, 'authority' => 'SPA', 'submission_type' => 'X', 'submitted_at' => today(), 'submitted_by' => $this->manager->id]);
        $this->actingAs($this->staff);

        Livewire::test(ListAuthoritySubmissions::class)->assertCanNotSeeTableRecords([$sub]);
    }
}

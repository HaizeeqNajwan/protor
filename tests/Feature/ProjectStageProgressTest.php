<?php

namespace Tests\Feature;

use App\Enums\EngineeringDiscipline;
use App\Enums\LeaveDayPart;
use App\Enums\LeaveType;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Filament\Widgets\AttentionWidget;
use App\Filament\Widgets\KeyNumbersWidget;
use App\Filament\Widgets\ProjectBoardWidget;
use App\Filament\Widgets\TeamWidget;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectStageProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProject(array $attributes = []): Project
    {
        return Project::query()->create([
            'project_code' => 'PRT-TEST-'.fake()->unique()->numberBetween(100, 999),
            'title' => 'Test project',
            'client_name' => 'Client',
            'discipline' => EngineeringDiscipline::Structural,
            'status' => ProjectStatus::Active,
            ...$attributes,
        ]);
    }

    protected function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return tap(User::factory()->create(), fn (User $user) => $user->assignRole($role));
    }

    public function test_new_projects_start_with_the_standard_works_for_every_stage(): void
    {
        $project = $this->makeProject();

        $expected = collect(WorkTemplate::DEFAULTS)->map(fn (array $titles) => count($titles));

        foreach (ProjectStage::cases() as $stage) {
            $this->assertSame($expected[$stage->value], $project->tasks()->where('stage', $stage)->count());
        }

        $this->assertSame(0, $project->applyWorkTemplates(), 'Re-applying must not duplicate works.');
    }

    public function test_assigned_staff_update_progress_and_managers_cannot(): void
    {
        $manager = $this->userWithRole(User::ROLE_PROJECT_MANAGER);
        $staff = $this->userWithRole(User::ROLE_STAFF_ENGINEER);
        $outsider = $this->userWithRole(User::ROLE_STAFF_ENGINEER);

        $project = $this->makeProject();
        $project->members()->attach($staff);
        $work = $project->tasks()->first();

        $this->assertTrue($work->canUpdateProgress($staff));
        $this->assertFalse($work->canUpdateProgress($outsider));
        $this->assertFalse($work->canUpdateProgress($manager));

        $work->recordProgress($staff, 40);
        $this->assertSame(TaskStatus::InProgress, $work->fresh()->status);

        $work->recordProgress($staff, 100);
        $this->assertSame(TaskStatus::Completed, $work->fresh()->status);
    }

    public function test_staff_and_managers_can_switch_stage_in_any_order_until_completed(): void
    {
        $manager = $this->userWithRole(User::ROLE_PROJECT_MANAGER);
        $staff = $this->userWithRole(User::ROLE_STAFF_ENGINEER);
        $project = $this->makeProject();
        $project->members()->attach($staff);

        $this->assertTrue($project->canSwitchStage($staff));
        $this->assertTrue($project->canSwitchStage($manager));

        $project->switchStage(ProjectStage::PostDesign);
        $project->switchStage(ProjectStage::PreDesign);
        $this->assertSame(ProjectStage::PreDesign, $project->fresh()->currentStage());

        $project->update(['status' => ProjectStatus::Completed]);
        $this->assertFalse($project->fresh()->canSwitchStage($staff));
    }

    public function test_project_can_be_completed_once_every_work_is_done(): void
    {
        $staff = $this->userWithRole(User::ROLE_STAFF_ENGINEER);
        $project = $this->makeProject();
        $project->members()->attach($staff);

        $this->assertFalse($project->fresh()->canComplete($staff));

        $project->tasks->each(fn ($work) => $work->recordProgress($staff, 100));

        $project = $project->fresh();
        $this->assertTrue($project->canComplete($staff));

        $project->markCompleted($staff);
        $this->assertSame(ProjectStatus::Completed, $project->fresh()->status);
        $this->assertTrue($project->fresh()->actual_completion_date->isToday());
    }

    public function test_board_lets_staff_update_progress_and_switch_stage(): void
    {
        $staff = $this->userWithRole(User::ROLE_STAFF_ENGINEER);
        $project = $this->makeProject(['title' => 'Riverside drainage']);
        $project->members()->attach($staff);
        $work = $project->tasks()->where('stage', ProjectStage::PreDesign)->first();

        $this->actingAs($staff);

        Livewire::test(ProjectBoardWidget::class)
            ->assertSee('Riverside drainage')
            ->call('select', $project->id)
            ->assertSee($work->title)
            ->call('saveProgress', $work->id, 55)
            ->call('switchStage', ProjectStage::Design->value);

        $this->assertSame(55, $work->fresh()->progress_percentage);
        $this->assertSame(ProjectStage::Design, $project->fresh()->currentStage());
    }

    public function test_board_completes_a_project_only_when_all_works_are_done(): void
    {
        $staff = $this->userWithRole(User::ROLE_STAFF_ENGINEER);
        $project = $this->makeProject();
        $project->members()->attach($staff);
        $this->actingAs($staff);

        $board = Livewire::test(ProjectBoardWidget::class)->call('select', $project->id)->call('completeProject');
        $this->assertSame(ProjectStatus::Active, $project->fresh()->status);

        $project->tasks->each(fn ($work) => $work->recordProgress($staff, 100));
        $board->call('completeProject');
        $this->assertSame(ProjectStatus::Completed, $project->fresh()->status);
    }

    public function test_board_ignores_progress_from_managers(): void
    {
        $manager = $this->userWithRole(User::ROLE_PROJECT_MANAGER);
        $project = $this->makeProject();
        $work = $project->tasks()->first();

        $this->actingAs($manager);

        Livewire::test(ProjectBoardWidget::class)
            ->call('select', $project->id)
            ->call('saveProgress', $work->id, 80);

        $this->assertSame(0, $work->fresh()->progress_percentage);
    }

    public function test_dashboard_widgets_render(): void
    {
        $this->actingAs($this->userWithRole(User::ROLE_PROJECT_MANAGER));
        $this->makeProject(['title' => 'Factory extension', 'target_completion_date' => today()->subDay()]);

        Livewire::test(KeyNumbersWidget::class)->assertOk()->assertSee('Pre-Design');
        Livewire::test(AttentionWidget::class)->assertOk()->assertSee('past target date');
        Livewire::test(TeamWidget::class)->assertOk();
    }

    public function test_half_day_leave_counts_as_half_a_day_on_a_single_date(): void
    {
        $user = User::factory()->create();
        $monday = today()->next('Monday');

        $leave = LeaveRequest::query()->create([
            'user_id' => $user->id,
            'leave_type' => LeaveType::Annual,
            'day_part' => LeaveDayPart::Afternoon,
            'start_date' => $monday,
            'end_date' => $monday->copy()->addDays(3),
        ]);

        $this->assertTrue($leave->half_day);
        $this->assertTrue($leave->end_date->isSameDay($monday));
        $this->assertEquals(0.5, (float) $leave->days);
    }
}

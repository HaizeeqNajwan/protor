<?php

namespace Database\Seeders;

use App\Enums\EngineeringDiscipline;
use App\Enums\LeaveDayPart;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Realistic demo portfolio for previewing the dashboard.
 * Run on a scratch database only: `php artisan db:seed --class=DemoDataSeeder`.
 * Demo logins use the password "password".
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoDataSeeder refuses to run in production.');

            return;
        }

        $manager = $this->user('Aisyah Rahman', 'manager@protor.test', User::ROLE_PROJECT_MANAGER);
        $pm2 = $this->user('Daniel Lau', 'daniel@protor.test', User::ROLE_PROJECT_MANAGER);
        $engineers = collect([
            ['Hafiz Osman', 'hafiz@protor.test'],
            ['Mei Ling Tan', 'meiling@protor.test'],
            ['Rajesh Kumar', 'rajesh@protor.test'],
            ['Nurul Izzah', 'nurul@protor.test'],
            ['Jason Wong', 'jason@protor.test'],
        ])->map(fn (array $u) => $this->user($u[0], $u[1], User::ROLE_STAFF_ENGINEER));

        // [code, title, client, location, discipline, status, current stage, start (days ago), duration (days), stage progress [pre, design, post]]
        $projects = [
            ['PRT-2026-004', 'Double-storey shophouse block', 'Stutong Land Sdn Bhd', 'Jalan Stutong, Kuching', EngineeringDiscipline::Structural, ProjectStatus::Active, ProjectStage::Design, 120, 300, [100, 72, 0]],
            ['PRT-2026-007', 'Riverside drainage upgrade Ph. 2', 'Majlis Bandaraya Kuching Selatan', 'Kuching Waterfront', EngineeringDiscipline::Infrastructure, ProjectStatus::Active, ProjectStage::PostDesign, 260, 330, [100, 100, 48]],
            ['PRT-2026-009', 'Hospital chiller plant retrofit', 'Sibu Medical Holdings', 'Sibu, Sarawak', EngineeringDiscipline::Mechanical, ProjectStatus::Active, ProjectStage::Design, 150, 210, [100, 35, 0]],
            ['PRT-2026-011', '33kV substation & feeder works', 'SEB Distribution', 'Samarahan', EngineeringDiscipline::Electrical, ProjectStatus::Active, ProjectStage::Design, 90, 240, [100, 88, 0]],
            ['PRT-2026-012', 'Mixed development podium', 'Tabuan Heights Bhd', 'Tabuan Jaya, Kuching', EngineeringDiscipline::Multidisciplinary, ProjectStatus::Active, ProjectStage::PreDesign, 20, 420, [55, 0, 0]],
            ['PRT-2026-014', 'Rural road & bridge crossing', 'JKR Sarawak', 'Serian', EngineeringDiscipline::Civil, ProjectStatus::Active, ProjectStage::PostDesign, 400, 380, [100, 100, 100]],
            ['PRT-2026-015', 'Factory warehouse extension', 'Demak Laut Industries', 'Demak Laut Industrial Park', EngineeringDiscipline::Structural, ProjectStatus::Active, ProjectStage::Design, 60, 180, [100, 22, 0]],
            ['PRT-2026-016', 'School block M&E services', 'Kementerian Pendidikan', 'Bau, Sarawak', EngineeringDiscipline::Electrical, ProjectStatus::Active, ProjectStage::PreDesign, 10, 200, [20, 0, 0]],
            ['PRT-2025-031', 'Commercial office fit-out', 'Borneo Capital Tower', 'Jalan Tun Abdul Razak, Kuching', EngineeringDiscipline::Mechanical, ProjectStatus::Completed, ProjectStage::PostDesign, 420, 360, [100, 100, 100]],
        ];

        foreach ($projects as $i => [$code, $title, $client, $location, $discipline, $status, $stage, $startAgo, $duration, $stageProgress]) {
            $start = today()->subDays($startAgo);

            $project = Project::query()->updateOrCreate(['project_code' => $code], [
                'title' => $title,
                'client_name' => $client,
                'location' => $location,
                'discipline' => $discipline,
                'status' => $status,
                'current_stage' => $stage,
                'contract_sum' => fake()->numberBetween(2, 60) * 1_000_000,
                'consultancy_fee' => fake()->numberBetween(80, 900) * 1_000,
                'start_date' => $start,
                'target_completion_date' => $start->copy()->addDays($duration),
                'actual_completion_date' => $status === ProjectStatus::Completed ? $start->copy()->addDays($duration - 12) : null,
                'project_manager_id' => $i % 3 === 2 ? $pm2->id : $manager->id,
                'created_by' => $manager->id,
            ]);

            $team = $engineers->random(3);
            $project->members()->sync($team->pluck('id')->all());

            // Rebuild the works from the standard templates, then record staff progress on them.
            $project->tasks()->forceDelete();
            $project->applyWorkTemplates();

            $this->seedProgress($project, $stageProgress, $team->all());
        }

        $this->seedLeave($engineers->all());
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $stageProgress  target % per stage
     * @param  list<User>  $team
     */
    protected function seedProgress(Project $project, array $stageProgress, array $team): void
    {
        foreach (ProjectStage::cases() as $index => $stage) {
            $target = $stageProgress[$index];
            $works = $project->tasks()->where('stage', $stage)->inWorkOrder()->get();
            $count = max(1, $works->count() - 1);

            foreach ($works->values() as $n => $work) {
                // Earlier works in a stage finish first, so the stage fills up in order.
                $progress = $target >= 100 ? 100 : (int) max(0, min(100, round(($target * 1.6 - ($n / $count) * 100) / 5) * 5));

                if ($progress > 0) {
                    $work->recordProgress($team[array_rand($team)], $progress);
                }
            }
        }
    }

    /** @param  list<User>  $engineers */
    protected function seedLeave(array $engineers): void
    {
        LeaveRequest::query()->whereIn('user_id', collect($engineers)->pluck('id'))->delete();

        $rows = [
            [$engineers[0], LeaveType::Annual, LeaveDayPart::Full, today()->addDays(6), today()->addDays(8), LeaveStatus::Pending],
            [$engineers[1], LeaveType::Annual, LeaveDayPart::Morning, today()->addWeekdays(2), null, LeaveStatus::Pending],
            [$engineers[2], LeaveType::Medical, LeaveDayPart::Full, today()->isWeekend() ? today()->nextWeekday() : today(), today()->isWeekend() ? today()->nextWeekday() : today(), LeaveStatus::Approved],
            [$engineers[3], LeaveType::Emergency, LeaveDayPart::Afternoon, today()->subWeekdays(3), null, LeaveStatus::Approved],
        ];

        foreach ($rows as [$user, $type, $dayPart, $start, $end, $status]) {
            LeaveRequest::query()->create([
                'user_id' => $user->id,
                'leave_type' => $type,
                'day_part' => $dayPart,
                'start_date' => $start,
                'end_date' => $end ?? $start,
                'reason' => $type === LeaveType::Annual ? null : 'Family matter',
                'status' => $status,
            ]);
        }
    }

    protected function user(string $name, string $email, string $role): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => 'password',
        ]);

        $user->syncRoles([$role]);

        return $user;
    }
}
